<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Enums\CatalogImportStatus;
use App\Models\CatalogImport;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Imports a catalog file (see resources/catalog/catalog.template.json) into
 * the shop and records the outcome on the CatalogImport row.
 *
 * Products are matched on SKU. A new SKU creates a product; a known one has
 * the fields the catalog owns overwritten (name, excerpt, description in both
 * languages, price, EAN, image URL, category, manufacturer). What the shop
 * owns is never touched: the stock quantity, is_active, is_featured, an
 * uploaded image, and the slug. A product the import creates starts switched off, with the
 * file's stock as its quantity, so nothing reaches the shop before someone
 * has looked at it; from then on the shop manages both. A product missing from
 * the file is left alone, as is a product that was deleted in the admin
 * panel (it is updated but stays deleted).
 *
 * A file the reader rejects writes nothing. Otherwise rows are written in
 * chunks, one transaction each, and a row that cannot be used is skipped and
 * reported without stopping the rest. Running the same file again is safe: it
 * finds everything unchanged.
 */
final class CatalogImporter
{
    private const int CHUNK_SIZE = 200;

    private const string LOCK_KEY = 'catalog-import';

    private const int LOCK_SECONDS = 3600;

    private const string CREATED = 'created';

    private const string UPDATED = 'updated';

    private const string UNCHANGED = 'unchanged';

    private const string SKIPPED = 'skipped';

    /**
     * @var array{created: int, updated: int, unchanged: int, skipped: int}
     */
    private array $counts;

    /**
     * @var list<array{position: int, sku: string|null, messages: list<string>}>
     */
    private array $errors;

    public function __construct(
        private readonly CatalogFileReader $reader,
        private readonly CatalogRowParser $parser,
        private readonly CatalogTaxonomy $taxonomy,
    ) {}

    public function import(CatalogImport $import): void
    {
        $import->refresh();

        // A job delivered twice must not run a finished import again.
        if ($import->status->isFinished()) {
            return;
        }

        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);

        if (! $lock->get()) {
            $this->fail($import, __('admin.catalog_import.errors.already_running'));

            return;
        }

        try {
            $this->run($import);
        } catch (InvalidCatalogFileException $exception) {
            $this->fail($import, $exception->getMessage());
        } catch (Throwable $exception) {
            $this->fail($import, __('admin.catalog_import.errors.unexpected'));

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    /**
     * @throws InvalidCatalogFileException
     */
    private function run(CatalogImport $import): void
    {
        $import->update([
            'status' => CatalogImportStatus::Processing,
            'started_at' => now(),
            'failure_message' => null,
        ]);

        $rows = $this->reader->read(Storage::disk('local')->path($import->file_path));

        $import->update(['total_count' => count($rows)]);

        $this->counts = [self::CREATED => 0, self::UPDATED => 0, self::UNCHANGED => 0, self::SKIPPED => 0];
        $this->errors = [];

        $lastPositionOfSku = $this->lastPositionOfEachSku($rows);

        foreach (array_chunk($rows, self::CHUNK_SIZE, preserve_keys: true) as $chunk) {
            $this->importChunk($chunk, $lastPositionOfSku);

            $import->update($this->progress());
        }

        $import->update(['status' => CatalogImportStatus::Completed, 'finished_at' => now()]);
    }

    /**
     * @param  array<int, mixed>  $chunk  Raw rows keyed by their zero-based place in the file.
     * @param  array<string, int>  $lastPositionOfSku
     */
    private function importChunk(array $chunk, array $lastPositionOfSku): void
    {
        $parsed = [];

        foreach ($chunk as $index => $raw) {
            $position = $index + 1;

            try {
                $row = $this->parser->parse($raw);
            } catch (InvalidCatalogRowException $exception) {
                $this->skip($position, $this->skuOf($raw), $exception->messages);

                continue;
            }

            if ($lastPositionOfSku[$row->sku] !== $position) {
                $this->skip($position, $row->sku, [__('admin.catalog_import.errors.duplicate_sku')]);

                continue;
            }

            $parsed[$position] = $row;
        }

        if ($parsed === []) {
            return;
        }

        DB::transaction(function () use ($parsed): void {
            $this->taxonomy->refresh();

            $existing = Product::query()
                ->withTrashed()
                ->whereIn('sku', array_map(fn (CatalogRow $row): string => $row->sku, $parsed))
                ->get()
                ->keyBy(fn (Product $product): string => mb_strtolower((string) $product->sku));

            foreach ($parsed as $position => $row) {
                try {
                    $this->counts[$this->upsert($row, $existing->get(mb_strtolower($row->sku)))]++;
                } catch (QueryException $exception) {
                    report($exception);

                    $this->skip($position, $row->sku, [__('admin.catalog_import.errors.save_failed')]);
                }
            }
        });
    }

    /**
     * @return 'created'|'updated'|'unchanged'
     */
    private function upsert(CatalogRow $row, ?Product $product): string
    {
        $isNew = $product === null;
        $product ??= new Product;

        $product->fill([
            'sku' => $row->sku,
            'ean' => $row->ean,
            'image_url' => $row->imageUrl,
            'price_excl_vat_cents' => $row->priceExclVatCents,
            'product_category_id' => $this->taxonomy->categoryId($row->categoryPath),
            'manufacturer_id' => $this->taxonomy->manufacturerId($row->manufacturer),
        ]);

        // The only place an import sets what the shop owns: a new product
        // starts with what the file says for stock (0 when it says nothing)
        // and switched off, until an admin activates it. The shop owns both
        // from then on.
        if ($isNew) {
            $product->stock_quantity = $row->stock ?? 0;
            $product->is_active = false;
        }

        $this->fillText($product, 'name', $row->name);
        $this->fillText($product, 'excerpt', $row->excerpt);
        $this->fillText($product, 'description', $row->description);

        if (! $isNew && ! $product->isDirty()) {
            return self::UNCHANGED;
        }

        $product->save();

        return $isNew ? self::CREATED : self::UPDATED;
    }

    /**
     * The catalog is Lithuanian only, and the English site must not 404 on
     * these products, so the same text goes into both locales. Only assigned
     * when it differs: translations are compared as arrays, because the
     * database hands JSON back with its keys reordered and a raw comparison
     * would call every product changed.
     */
    private function fillText(Product $product, string $attribute, ?string $text): void
    {
        $wanted = $text === null ? [] : ['lt' => $text, 'en' => $text];

        if ($product->getTranslations($attribute) == $wanted) {
            return;
        }

        $product->setTranslations($attribute, $wanted);
    }

    /**
     * @param  list<string>  $messages
     */
    private function skip(int $position, ?string $sku, array $messages): void
    {
        $this->counts[self::SKIPPED]++;

        if (count($this->errors) < CatalogImport::MAX_STORED_ERRORS) {
            $this->errors[] = ['position' => $position, 'sku' => $sku, 'messages' => $messages];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function progress(): array
    {
        return [
            'created_count' => $this->counts[self::CREATED],
            'updated_count' => $this->counts[self::UPDATED],
            'unchanged_count' => $this->counts[self::UNCHANGED],
            'skipped_count' => $this->counts[self::SKIPPED],
            'errors' => $this->errors === [] ? null : $this->errors,
        ];
    }

    /**
     * When a SKU appears more than once the last row wins, as if the rows
     * had been imported in order.
     *
     * @param  list<mixed>  $rows
     * @return array<string, int>
     */
    private function lastPositionOfEachSku(array $rows): array
    {
        $last = [];

        foreach ($rows as $index => $raw) {
            $sku = $this->skuOf($raw);

            if ($sku !== null) {
                $last[$sku] = $index + 1;
            }
        }

        return $last;
    }

    private function skuOf(mixed $raw): ?string
    {
        if (! is_array($raw) || ! isset($raw['sku']) || ! is_string($raw['sku'])) {
            return null;
        }

        $sku = trim($raw['sku']);

        return $sku === '' ? null : $sku;
    }

    private function fail(CatalogImport $import, string $message): void
    {
        $import->update([
            'status' => CatalogImportStatus::Failed,
            'failure_message' => $message,
            'finished_at' => now(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CatalogImportStatus;
use App\Models\CatalogImport;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Catalog\CatalogImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogImporterTest extends TestCase
{
    use RefreshDatabase;

    private int $nextSku = 1;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function product(array $overrides = []): array
    {
        $sku = 'SKU-'.$this->nextSku++;

        return array_merge([
            'sku' => $sku,
            'name' => 'Product '.$sku,
            'category' => ['Tinklo įranga', 'Komutatoriai'],
            'excerpt' => 'Short text',
            'description' => "Line one\nLine two",
            'price' => '20.00',
            'stock' => 3,
            'image_url' => 'http://bkgrupe.lt/media/'.$sku.'.jpg',
            'manufacturer' => 'Cudy',
            'ean' => null,
        ], $overrides);
    }

    /**
     * @param  list<mixed>  $products
     */
    private function importProducts(array $products): CatalogImport
    {
        return $this->importJson(json_encode(['schema_version' => 1, 'products' => $products], JSON_THROW_ON_ERROR));
    }

    private function importJson(string $json): CatalogImport
    {
        Storage::disk('local')->put($path = 'imports/'.uniqid().'.json', $json);

        $import = CatalogImport::query()->create(['original_filename' => 'catalog.json', 'file_path' => $path]);

        app(CatalogImporter::class)->import($import);

        return $import->fresh();
    }

    public function test_new_products_are_created_with_every_field_mapped(): void
    {
        $import = $this->importProducts([$this->product([
            'sku' => 'KRDS1663ZJ',
            'name' => 'Hikvision kronšteinas',
            'price' => '19.99',
            'stock' => 2,
            'ean' => '6954273658830',
            'manufacturer' => 'Hikvision',
            'category' => ['Vaizdo stebėjimo sistemos', 'IP kameros'],
        ])]);

        $this->assertSame(CatalogImportStatus::Completed, $import->status);
        $this->assertSame(1, $import->total_count);
        $this->assertSame(1, $import->created_count);
        $this->assertNotNull($import->finished_at);
        $this->assertNull($import->errors);

        $product = Product::query()->with(['category', 'manufacturer'])->where('sku', 'KRDS1663ZJ')->sole();

        $this->assertEquals(['lt' => 'Hikvision kronšteinas', 'en' => 'Hikvision kronšteinas'], $product->getTranslations('name'));
        $this->assertEquals(['lt' => 'Short text', 'en' => 'Short text'], $product->getTranslations('excerpt'));
        $this->assertEquals(['lt' => "Line one\nLine two", 'en' => "Line one\nLine two"], $product->getTranslations('description'));
        $this->assertSame(1999, $product->price_cents);
        $this->assertSame(2, $product->stock_quantity);
        $this->assertSame('6954273658830', $product->ean);
        $this->assertSame('http://bkgrupe.lt/media/SKU-1.jpg', $product->image_url);
        $this->assertSame('IP kameros', $product->category->name);
        $this->assertSame('Hikvision', $product->manufacturer->name);
        $this->assertTrue($product->is_active);
        $this->assertSame(0, $product->order);
        $this->assertSame('hikvision-kronsteinas', $product->getTranslation('slug', 'lt'));
        $this->assertSame('hikvision-kronsteinas', $product->getTranslation('slug', 'en'));
    }

    public function test_the_shipped_template_imports_cleanly(): void
    {
        $import = $this->importJson((string) file_get_contents(resource_path('catalog/catalog.template.json')));

        $this->assertSame(CatalogImportStatus::Completed, $import->status);
        $this->assertSame(2, $import->created_count);
        $this->assertSame(0, $import->skipped_count);
        $this->assertNull($import->errors);
    }

    public function test_products_without_optional_values_are_created(): void
    {
        $this->importProducts([[
            'sku' => 'MIN-1', 'name' => 'Minimal', 'price' => '0.99', 'stock' => 0,
        ]]);

        $product = Product::query()->where('sku', 'MIN-1')->sole();

        $this->assertNull($product->product_category_id);
        $this->assertNull($product->manufacturer_id);
        $this->assertNull($product->getRawOriginal('excerpt'));
        $this->assertSame(0, $product->stock_quantity);
        $this->assertFalse($product->isInStock());
    }

    public function test_categories_are_built_as_a_tree_and_reused(): void
    {
        $this->importProducts([
            $this->product(['category' => ['Tinklo įranga', 'Komutatoriai']]),
            $this->product(['category' => ['Tinklo įranga', 'Komutatoriai']]),
            $this->product(['category' => ['Tinklo įranga', 'Maršrutizatoriai']]),
            $this->product(['category' => ['tinklo įranga']]),
        ]);

        $this->assertSame(3, ProductCategory::query()->count());

        $root = ProductCategory::query()->whereNull('parent_id')->sole();

        $this->assertSame('Tinklo įranga', $root->name);
        $this->assertEqualsCanonicalizing(['Komutatoriai', 'Maršrutizatoriai'], $root->children->pluck('name')->all());
        $this->assertSame(4, Product::query()->whereNotNull('product_category_id')->count());
    }

    public function test_the_same_category_name_under_different_parents_stays_separate(): void
    {
        $this->importProducts([
            $this->product(['category' => ['IP sistemos', 'IP kameros']]),
            $this->product(['category' => ['Analoginės sistemos', 'IP kameros']]),
        ]);

        $this->assertSame(2, ProductCategory::query()->where('name->lt', 'IP kameros')->count());
    }

    public function test_a_later_import_reuses_existing_categories_and_manufacturers(): void
    {
        $this->importProducts([$this->product(['manufacturer' => 'Cudy'])]);
        $this->importProducts([$this->product(['manufacturer' => 'CUDY'])]);

        $this->assertSame(2, ProductCategory::query()->count());
        $this->assertSame(1, Manufacturer::query()->count());
        $this->assertSame(2, Product::query()->count());
    }

    public function test_importing_the_same_file_again_changes_nothing(): void
    {
        $products = [$this->product(), $this->product(['category' => null, 'excerpt' => null, 'manufacturer' => null])];

        $this->importProducts($products);
        $updatedAt = Product::query()->orderBy('id')->pluck('updated_at', 'id');

        $this->travel(1)->hour();

        $second = $this->importProducts($products);

        $this->assertSame(0, $second->created_count);
        $this->assertSame(0, $second->updated_count);
        $this->assertSame(2, $second->unchanged_count);
        $this->assertEquals($updatedAt, Product::query()->orderBy('id')->pluck('updated_at', 'id'));
    }

    public function test_known_skus_are_updated_in_place(): void
    {
        $this->importProducts([$this->product(['sku' => 'A1', 'name' => 'Old name', 'price' => '10.00', 'stock' => 5])]);
        $original = Product::query()->where('sku', 'A1')->sole();

        $import = $this->importProducts([$this->product([
            'sku' => 'A1', 'name' => 'New name', 'price' => '12.50', 'stock' => 0,
            'category' => ['Other'], 'manufacturer' => 'Acme', 'ean' => '12345678',
            'image_url' => 'http://bkgrupe.lt/new.jpg',
        ])]);

        $this->assertSame(0, $import->created_count);
        $this->assertSame(1, $import->updated_count);

        $product = Product::query()->where('sku', 'A1')->sole();

        $this->assertSame($original->id, $product->id);
        $this->assertEquals(['lt' => 'New name', 'en' => 'New name'], $product->getTranslations('name'));
        $this->assertSame(1250, $product->price_cents);
        $this->assertSame(0, $product->stock_quantity);
        $this->assertSame('Other', $product->category->name);
        $this->assertSame('Acme', $product->manufacturer->name);
        $this->assertSame('12345678', $product->ean);
        $this->assertSame('http://bkgrupe.lt/new.jpg', $product->image_url);
    }

    public function test_texts_and_links_that_disappear_from_the_file_are_cleared(): void
    {
        $this->importProducts([$this->product(['sku' => 'A1', 'ean' => '12345678'])]);

        $this->importProducts([$this->product([
            'sku' => 'A1', 'excerpt' => null, 'description' => null, 'image_url' => null,
            'category' => null, 'manufacturer' => null, 'ean' => null,
        ])]);

        $product = Product::query()->where('sku', 'A1')->sole();

        $this->assertSame('', $product->excerpt);
        $this->assertSame('', $product->description);
        $this->assertNull($product->image_url);
        $this->assertNull($product->product_category_id);
        $this->assertNull($product->manufacturer_id);
        $this->assertNull($product->ean);
    }

    public function test_what_the_admin_owns_survives_a_re_import(): void
    {
        $this->importProducts([$this->product(['sku' => 'A1', 'name' => 'Old name'])]);
        $product = Product::query()->where('sku', 'A1')->sole();
        $product->update(['is_active' => false, 'order' => 7]);

        $this->importProducts([$this->product(['sku' => 'A1', 'name' => 'New name'])]);

        $product->refresh();

        $this->assertFalse($product->is_active);
        $this->assertSame(7, $product->order);
    }

    public function test_the_slug_of_a_product_that_has_been_on_sale_does_not_change(): void
    {
        $this->importProducts([$this->product(['sku' => 'A1', 'name' => 'Old name'])]);

        $this->importProducts([$this->product(['sku' => 'A1', 'name' => 'New name'])]);

        $product = Product::query()->where('sku', 'A1')->sole();

        $this->assertSame('New name', $product->name);
        $this->assertSame('old-name', $product->getTranslation('slug', 'lt'));
    }

    public function test_products_missing_from_the_file_are_left_alone(): void
    {
        $this->importProducts([$this->product(['sku' => 'A1']), $this->product(['sku' => 'A2'])]);

        $this->importProducts([$this->product(['sku' => 'A2'])]);

        $product = Product::query()->where('sku', 'A1')->sole();

        $this->assertTrue($product->is_active);
        $this->assertSame(3, $product->stock_quantity);
    }

    public function test_products_made_by_hand_are_never_touched(): void
    {
        $manual = Product::factory()->create(['name' => ['lt' => 'Paslauga', 'en' => 'Service']]);

        $this->importProducts([$this->product()]);

        $this->assertSame('Paslauga', $manual->fresh()->getTranslation('name', 'lt'));
        $this->assertNull($manual->fresh()->sku);
        $this->assertNull($manual->fresh()->stock_quantity);
    }

    public function test_a_product_deleted_in_the_admin_panel_is_updated_but_stays_deleted(): void
    {
        $this->importProducts([$this->product(['sku' => 'A1', 'price' => '10.00'])]);
        Product::query()->where('sku', 'A1')->sole()->delete();

        $import = $this->importProducts([$this->product(['sku' => 'A1', 'price' => '15.00'])]);

        $this->assertSame(1, $import->updated_count);
        $this->assertSame(0, $import->created_count);

        $product = Product::query()->withTrashed()->where('sku', 'A1')->sole();

        $this->assertTrue($product->trashed());
        $this->assertSame(1500, $product->price_cents);
    }

    public function test_the_last_row_wins_when_a_sku_repeats(): void
    {
        $import = $this->importProducts([
            $this->product(['sku' => 'A1', 'name' => 'Inactive']),
            $this->product(['sku' => 'A2']),
            $this->product(['sku' => 'A1', 'name' => 'The real name']),
        ]);

        $this->assertSame(2, $import->created_count);
        $this->assertSame(1, $import->skipped_count);
        $this->assertSame('The real name', Product::query()->where('sku', 'A1')->sole()->name);
        $this->assertSame(1, $import->errors[0]['position']);
        $this->assertSame('A1', $import->errors[0]['sku']);
        $this->assertSame([__('admin.catalog_import.errors.duplicate_sku')], $import->errors[0]['messages']);
    }

    public function test_files_larger_than_one_chunk_are_imported_whole(): void
    {
        $products = [];

        for ($i = 1; $i <= 201; $i++) {
            $products[] = $this->product(['sku' => 'BULK-'.$i, 'name' => 'Bulk '.$i]);
        }

        // A repeat that lands in a later chunk than its first occurrence.
        $products[] = $this->product(['sku' => 'BULK-1', 'name' => 'Bulk 1 again']);

        $import = $this->importProducts($products);

        $this->assertSame(202, $import->total_count);
        $this->assertSame(201, $import->created_count);
        $this->assertSame(1, $import->skipped_count);
        $this->assertSame(201, Product::query()->count());
        $this->assertSame('Bulk 1 again', Product::query()->where('sku', 'BULK-1')->sole()->name);
        $this->assertSame(2, ProductCategory::query()->count());
        $this->assertSame(1, Manufacturer::query()->count());
    }

    public function test_bad_rows_are_skipped_and_reported_while_the_rest_import(): void
    {
        $import = $this->importProducts([
            $this->product(['sku' => 'GOOD-1']),
            $this->product(['sku' => 'BAD-PRICE', 'price' => '19,99']),
            'not a product',
            $this->product(['sku' => 'GOOD-2']),
            ['name' => 'No sku', 'price' => '1.00', 'stock' => 1],
        ]);

        $this->assertSame(CatalogImportStatus::Completed, $import->status);
        $this->assertSame(5, $import->total_count);
        $this->assertSame(2, $import->created_count);
        $this->assertSame(3, $import->skipped_count);
        $this->assertEqualsCanonicalizing(['GOOD-1', 'GOOD-2'], Product::query()->pluck('sku')->all());

        $this->assertSame([2, 3, 5], array_column($import->errors, 'position'));
        $this->assertSame('BAD-PRICE', $import->errors[0]['sku']);
        $this->assertSame([__('admin.catalog_import.errors.price_format')], $import->errors[0]['messages']);
        $this->assertNull($import->errors[1]['sku']);
        $this->assertNull($import->errors[2]['sku']);
    }

    public function test_stored_errors_are_capped_but_every_skipped_row_is_counted(): void
    {
        $bad = array_map(fn (int $i): array => $this->product(['sku' => 'BAD-'.$i, 'price' => 'free']), range(1, CatalogImport::MAX_STORED_ERRORS + 5));

        $import = $this->importProducts($bad);

        $this->assertSame(CatalogImport::MAX_STORED_ERRORS + 5, $import->skipped_count);
        $this->assertCount(CatalogImport::MAX_STORED_ERRORS, $import->errors);
        $this->assertSame(0, Product::query()->count());
    }

    public function test_a_row_the_database_refuses_is_skipped_without_losing_the_others(): void
    {
        // The unique index ignores accents, but the lookup by SKU does not,
        // so this looks like a new product and the insert is refused.
        Product::factory()->create(['sku' => 'cafe']);

        $import = $this->importProducts([
            $this->product(['sku' => 'FIRST']),
            $this->product(['sku' => 'café']),
            $this->product(['sku' => 'LAST']),
        ]);

        $this->assertSame(CatalogImportStatus::Completed, $import->status);
        $this->assertSame(2, $import->created_count);
        $this->assertSame(1, $import->skipped_count);
        $this->assertSame('café', $import->errors[0]['sku']);
        $this->assertSame([__('admin.catalog_import.errors.save_failed')], $import->errors[0]['messages']);
        $this->assertNotNull(Product::query()->where('sku', 'LAST')->first());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unusableFiles(): array
    {
        return [
            'not JSON' => ['this is not json'],
            'truncated JSON' => ['{"schema_version": 1, "products": [{"sku": "A'],
            'a list' => ['[]'],
            'a number' => ['5'],
            'no schema version' => ['{"products": [{"sku": "A1"}]}'],
            'other schema version' => ['{"schema_version": 2, "products": [{"sku": "A1"}]}'],
            'schema version as text' => ['{"schema_version": "1", "products": [{"sku": "A1"}]}'],
            'no products key' => ['{"schema_version": 1}'],
            'products not a list' => ['{"schema_version": 1, "products": {"sku": "A1"}}'],
            'no products' => ['{"schema_version": 1, "products": []}'],
        ];
    }

    #[DataProvider('unusableFiles')]
    public function test_a_file_that_cannot_be_used_fails_the_run_and_writes_nothing(string $json): void
    {
        Product::factory()->create(['sku' => 'EXISTING']);

        $import = $this->importJson($json);

        $this->assertSame(CatalogImportStatus::Failed, $import->status);
        $this->assertNotEmpty($import->failure_message);
        $this->assertNotNull($import->finished_at);
        $this->assertSame(0, $import->created_count);
        $this->assertSame(1, Product::query()->count());
    }

    public function test_a_byte_order_mark_is_tolerated(): void
    {
        $import = $this->importJson("\xEF\xBB\xBF".json_encode([
            'schema_version' => 1,
            'products' => [$this->product()],
        ]));

        $this->assertSame(CatalogImportStatus::Completed, $import->status);
        $this->assertSame(1, $import->created_count);
    }

    public function test_a_missing_file_fails_the_run(): void
    {
        $import = CatalogImport::query()->create(['original_filename' => 'gone.json', 'file_path' => 'imports/gone.json']);

        app(CatalogImporter::class)->import($import);

        $this->assertSame(CatalogImportStatus::Failed, $import->fresh()->status);
        $this->assertSame(__('admin.catalog_import.errors.file_missing'), $import->fresh()->failure_message);
    }

    public function test_a_finished_import_is_not_run_again(): void
    {
        $import = $this->importProducts([$this->product()]);
        $this->assertSame(1, Product::query()->count());

        Product::query()->forceDelete();

        app(CatalogImporter::class)->import($import);

        $this->assertSame(0, Product::query()->count());
        $this->assertSame(CatalogImportStatus::Completed, $import->fresh()->status);
    }

    public function test_only_one_import_runs_at_a_time(): void
    {
        $lock = Cache::lock('catalog-import', 60);
        $this->assertTrue($lock->get());

        $import = $this->importProducts([$this->product()]);

        $this->assertSame(CatalogImportStatus::Failed, $import->status);
        $this->assertSame(__('admin.catalog_import.errors.already_running'), $import->failure_message);
        $this->assertSame(0, Product::query()->count());

        $lock->release();

        $this->assertSame(CatalogImportStatus::Completed, $this->importProducts([$this->product()])->status);
    }

    public function test_the_lock_is_released_after_a_failed_run(): void
    {
        $this->importJson('not json');

        $this->assertSame(CatalogImportStatus::Completed, $this->importProducts([$this->product()])->status);
    }
}

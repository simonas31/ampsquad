<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CatalogImportStatus;
use App\Jobs\ImportCatalogJob;
use App\Models\CatalogImport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('app:import-catalog {file : Path to a catalog JSON file} {--queue : Run on the queue instead of right away}')]
#[Description('Import products from a catalog JSON file (template: resources/catalog/catalog.template.json).')]
class ImportCatalog extends Command
{
    private const int ERRORS_SHOWN = 10;

    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path) || ! is_readable($path)) {
            $this->components->error("Cannot read {$path}.");

            return self::FAILURE;
        }

        $import = CatalogImport::query()->create([
            'original_filename' => basename($path),
            'file_path' => Storage::disk('local')->putFileAs('imports', new File($path), Str::uuid().'.json'),
        ]);

        if ($this->option('queue')) {
            ImportCatalogJob::dispatch($import);
            $this->components->info("Import #{$import->id} queued.");

            return self::SUCCESS;
        }

        ImportCatalogJob::dispatchSync($import);

        return $this->report($import->refresh());
    }

    private function report(CatalogImport $import): int
    {
        if ($import->status === CatalogImportStatus::Failed) {
            $this->components->error($import->failure_message ?? 'The import failed.');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Products in file', (string) $import->total_count);
        $this->components->twoColumnDetail('Created', (string) $import->created_count);
        $this->components->twoColumnDetail('Updated', (string) $import->updated_count);
        $this->components->twoColumnDetail('Unchanged', (string) $import->unchanged_count);
        $this->components->twoColumnDetail('Skipped', (string) $import->skipped_count);

        foreach (array_slice($import->errors ?? [], 0, self::ERRORS_SHOWN) as $error) {
            $this->components->warn("Row {$error['position']} ({$error['sku']}): ".implode(' ', $error['messages']));
        }

        $this->components->info("Import #{$import->id} finished.");

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\CatalogImportStatus;
use App\Models\CatalogImport;
use App\Services\Catalog\CatalogImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Runs a catalog import on the queue: saving ten thousand products one by
 * one (each generates its slugs) takes minutes, far longer than a web
 * request. Needs QUEUE retry_after above $timeout, or the queue hands the
 * job to a second worker while the first is still busy; see
 * DB_QUEUE_RETRY_AFTER in the deploy notes.
 */
class ImportCatalogJob implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * An import is safe to run again, but a failed one is a human's call:
     * the admin fixes the file and uploads it again.
     */
    public int $tries = 1;

    public int $timeout = 1800;

    /**
     * The run row is pruned after a while; a job still waiting then has
     * nothing left to do.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public CatalogImport $import) {}

    public function handle(CatalogImporter $importer): void
    {
        $importer->import($this->import);
    }

    /**
     * Covers the ways a run can end without the importer getting to say so:
     * a worker timeout or kill. A run that already finished stays as it is.
     */
    public function failed(?Throwable $exception): void
    {
        $import = $this->import->fresh();

        if ($import === null || $import->status->isFinished()) {
            return;
        }

        $import->update([
            'status' => CatalogImportStatus::Failed,
            'failure_message' => __('admin.catalog_import.errors.unexpected'),
            'finished_at' => now(),
        ]);
    }
}

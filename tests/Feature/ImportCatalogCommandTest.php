<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CatalogImportStatus;
use App\Jobs\ImportCatalogJob;
use App\Models\CatalogImport;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportCatalogCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('source');
    }

    /**
     * @param  list<array<string, mixed>>  $products
     */
    private function catalogFile(array $products): string
    {
        Storage::disk('source')->put('catalog.json', json_encode(['schema_version' => 1, 'products' => $products]));

        return Storage::disk('source')->path('catalog.json');
    }

    /**
     * @return array<string, mixed>
     */
    private function product(string $sku = 'A1'): array
    {
        return ['sku' => $sku, 'name' => 'Product '.$sku, 'price' => '9.90', 'stock' => 4];
    }

    public function test_it_imports_a_file_right_away_and_keeps_a_copy(): void
    {
        $path = $this->catalogFile([$this->product('A1'), $this->product('A2')]);

        $this->artisan('app:import-catalog', ['file' => $path])->assertSuccessful();

        $import = CatalogImport::query()->sole();

        $this->assertSame(CatalogImportStatus::Completed, $import->status);
        $this->assertSame('catalog.json', $import->original_filename);
        $this->assertSame(2, $import->created_count);
        $this->assertNull($import->user_id);
        Storage::disk('local')->assertExists($import->file_path);
        $this->assertSame(2, Product::query()->count());
    }

    public function test_it_reports_what_happened(): void
    {
        $path = $this->catalogFile([$this->product('A1'), ['sku' => 'BAD', 'name' => 'Bad', 'price' => 'free', 'stock' => 1]]);

        $this->artisan('app:import-catalog', ['file' => $path])
            ->expectsOutputToContain('Created')
            ->expectsOutputToContain('Skipped')
            ->expectsOutputToContain('Row 2 (BAD)')
            ->assertSuccessful();
    }

    public function test_it_fails_for_a_file_that_does_not_exist(): void
    {
        $this->artisan('app:import-catalog', ['file' => '/no/such/catalog.json'])->assertFailed();

        $this->assertSame(0, CatalogImport::query()->count());
    }

    public function test_it_fails_for_a_file_that_is_not_a_catalog(): void
    {
        Storage::disk('source')->put('catalog.json', 'not json');

        $this->artisan('app:import-catalog', ['file' => Storage::disk('source')->path('catalog.json')])->assertFailed();

        $this->assertSame(CatalogImportStatus::Failed, CatalogImport::query()->sole()->status);
        $this->assertSame(0, Product::query()->count());
    }

    public function test_with_the_queue_option_it_only_queues_the_import(): void
    {
        Queue::fake();

        $this->artisan('app:import-catalog', ['file' => $this->catalogFile([$this->product()]), '--queue' => true])
            ->assertSuccessful();

        $import = CatalogImport::query()->sole();

        $this->assertSame(CatalogImportStatus::Pending, $import->status);
        $this->assertSame(0, Product::query()->count());
        Queue::assertPushed(ImportCatalogJob::class, fn (ImportCatalogJob $job): bool => $job->import->is($import));
    }

    public function test_the_job_marks_a_run_that_never_finished_as_failed(): void
    {
        $import = CatalogImport::factory()->create(['status' => CatalogImportStatus::Processing]);

        (new ImportCatalogJob($import))->failed(new \RuntimeException('Worker timed out'));

        $this->assertSame(CatalogImportStatus::Failed, $import->fresh()->status);
        $this->assertSame(__('admin.catalog_import.errors.unexpected'), $import->fresh()->failure_message);
        $this->assertNotNull($import->fresh()->finished_at);
    }

    public function test_the_job_leaves_a_finished_run_alone(): void
    {
        $import = CatalogImport::factory()->completed()->create();

        (new ImportCatalogJob($import))->failed(null);

        $this->assertSame(CatalogImportStatus::Completed, $import->fresh()->status);
        $this->assertNull($import->fresh()->failure_message);
    }

    public function test_old_runs_are_pruned_together_with_their_file(): void
    {
        Storage::disk('local')->put('imports/old.json', '{}');
        Storage::disk('local')->put('imports/recent.json', '{}');

        $old = CatalogImport::factory()->create(['file_path' => 'imports/old.json']);
        $recent = CatalogImport::factory()->create(['file_path' => 'imports/recent.json']);
        $old->forceFill(['created_at' => now()->subDays(CatalogImport::RETENTION_DAYS + 1)])->saveQuietly();

        Artisan::call('model:prune', ['--model' => [CatalogImport::class]]);

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
        Storage::disk('local')->assertMissing('imports/old.json');
        Storage::disk('local')->assertExists('imports/recent.json');
    }
}

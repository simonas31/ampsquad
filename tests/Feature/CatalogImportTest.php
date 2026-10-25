<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CatalogImportStatus;
use App\Models\CatalogImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_import_is_pending_with_zeroed_counters(): void
    {
        $import = CatalogImport::query()->create([
            'original_filename' => 'catalog.json',
            'file_path' => 'imports/catalog.json',
        ])->fresh();

        $this->assertSame(CatalogImportStatus::Pending, $import->status);
        $this->assertSame(0, $import->total_count);
        $this->assertSame(0, $import->created_count);
        $this->assertSame(0, $import->skipped_count);
        $this->assertNull($import->errors);
        $this->assertNull($import->finished_at);
    }

    public function test_row_errors_round_trip_as_an_array(): void
    {
        $errors = [['position' => 3, 'sku' => 'ABC', 'messages' => ['The price field is required.']]];

        $import = CatalogImport::factory()->create(['errors' => $errors])->fresh();

        // MySQL's JSON type reorders object keys, so compare without order.
        $this->assertEquals($errors, $import->errors);
    }

    public function test_import_remembers_who_started_it_but_survives_the_user(): void
    {
        $user = User::factory()->create();
        $import = CatalogImport::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($import->user->is($user));

        $user->delete();

        $this->assertNull($import->fresh()->user_id);
    }

    public function test_only_completed_and_failed_runs_are_finished(): void
    {
        $this->assertFalse(CatalogImportStatus::Pending->isFinished());
        $this->assertFalse(CatalogImportStatus::Processing->isFinished());
        $this->assertTrue(CatalogImportStatus::Completed->isFinished());
        $this->assertTrue(CatalogImportStatus::Failed->isFinished());
    }

    public function test_factory_states(): void
    {
        $completed = CatalogImport::factory()->completed()->create();
        $failed = CatalogImport::factory()->failed('Bad file')->create();

        $this->assertSame(CatalogImportStatus::Completed, $completed->status);
        $this->assertNotNull($completed->finished_at);
        $this->assertSame(CatalogImportStatus::Failed, $failed->status);
        $this->assertSame('Bad file', $failed->failure_message);
    }
}

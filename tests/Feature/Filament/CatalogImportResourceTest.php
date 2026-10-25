<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\CatalogImportStatus;
use App\Filament\Resources\CatalogImports\CatalogImportResource;
use App\Filament\Resources\CatalogImports\Pages\ListCatalogImports;
use App\Filament\Resources\CatalogImports\Pages\ViewCatalogImport;
use App\Models\CatalogImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogImportResourceTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): void
    {
        $this->actingAs(User::factory()->create());
    }

    public function test_guests_cannot_reach_the_import_history(): void
    {
        $this->get('/admin/catalog-imports')->assertRedirect();
    }

    public function test_the_list_shows_the_newest_runs_first(): void
    {
        $this->signIn();
        $old = CatalogImport::factory()->completed()->create(['created_at' => now()->subDay()]);
        $new = CatalogImport::factory()->failed()->create(['created_at' => now()]);

        Livewire::test(ListCatalogImports::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$new, $old], inOrder: true);
    }

    public function test_the_list_can_be_filtered_by_status(): void
    {
        $this->signIn();
        $completed = CatalogImport::factory()->completed()->create();
        $failed = CatalogImport::factory()->failed()->create();

        Livewire::test(ListCatalogImports::class)
            ->filterTable('status', CatalogImportStatus::Failed->value)
            ->assertCanSeeTableRecords([$failed])
            ->assertCanNotSeeTableRecords([$completed]);
    }

    public function test_a_run_shows_its_counts_and_the_problems_found_in_the_file(): void
    {
        $this->signIn();
        $import = CatalogImport::factory()->completed()->create([
            'original_filename' => 'spring-prices.json',
            'errors' => [['position' => 7, 'sku' => 'BAD-PRICE', 'messages' => ['The price must be a decimal string such as "19.99".']]],
        ]);

        Livewire::test(ViewCatalogImport::class, ['record' => $import->id])
            ->assertOk()
            ->assertSee('spring-prices.json')
            ->assertSee('BAD-PRICE')
            ->assertSee('The price must be a decimal string');
    }

    public function test_a_failed_run_says_why(): void
    {
        $this->signIn();
        $import = CatalogImport::factory()->failed('The file is not valid JSON: Syntax error')->create();

        Livewire::test(ViewCatalogImport::class, ['record' => $import->id])
            ->assertOk()
            ->assertSee('The file is not valid JSON: Syntax error');
    }

    public function test_a_run_with_more_problems_than_stored_says_so(): void
    {
        $this->signIn();
        $import = CatalogImport::factory()->completed()->create([
            'skipped_count' => 500,
            'errors' => [['position' => 1, 'sku' => 'A1', 'messages' => ['Nope.']]],
        ]);

        Livewire::test(ViewCatalogImport::class, ['record' => $import->id])
            ->assertSee(__('admin.catalog_import.errors_capped', ['shown' => 1, 'total' => 500]));
    }

    public function test_runs_are_read_only(): void
    {
        $this->signIn();
        $import = CatalogImport::factory()->completed()->create();

        $this->assertTrue(Gate::allows('viewAny', CatalogImport::class));
        $this->assertTrue(Gate::allows('view', $import));
        $this->assertFalse(Gate::allows('create', CatalogImport::class));
        $this->assertFalse(Gate::allows('update', $import));
        $this->assertFalse(Gate::allows('delete', $import));
        $this->assertFalse(CatalogImportResource::canCreate());
        $this->assertSame(['index', 'view'], array_keys(CatalogImportResource::getPages()));
    }
}

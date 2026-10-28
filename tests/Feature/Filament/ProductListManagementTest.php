<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\CatalogImports\Pages\ListCatalogImports;
use App\Filament\Resources\ContactRequests\Pages\ListContactRequests;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Number;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The product list as an admin uses it with a large catalog: finding things,
 * ordering them, and changing stock and status without opening each product.
 */
class ProductListManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function named(string $name, array $attributes = []): Product
    {
        return Product::factory()->create(['name' => ['lt' => $name, 'en' => $name], ...$attributes]);
    }

    // -- search ------------------------------------------------------------

    public function test_search_finds_products_by_name_sku_and_ean(): void
    {
        $switch = $this->named('Tinklo komutatorius', ['sku' => 'CUDY-1', 'ean' => '5901234123457']);
        $router = $this->named('Maršrutizatorius', ['sku' => 'TPL-2']);

        Livewire::test(ListProducts::class)
            ->searchTable('komutatorius')
            ->assertCanSeeTableRecords([$switch])
            ->assertCanNotSeeTableRecords([$router])
            ->searchTable('tpl-2')
            ->assertCanSeeTableRecords([$router])
            ->assertCanNotSeeTableRecords([$switch])
            ->searchTable('5901234123457')
            ->assertCanSeeTableRecords([$switch])
            ->assertCanNotSeeTableRecords([$router]);
    }

    public function test_search_ignores_case_and_accents_and_needs_every_word(): void
    {
        $match = $this->named('Hikvision kronšteinas');
        $other = $this->named('Hikvision kamera');

        Livewire::test(ListProducts::class)
            ->searchTable('KRONSTEINAS')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('hikvision kron')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_search_does_not_match_the_language_keys_of_the_stored_names(): void
    {
        $this->named('Komutatorius');

        Livewire::test(ListProducts::class)
            ->searchTable('lt')
            ->assertCountTableRecords(0)
            ->searchTable('en')
            ->assertCountTableRecords(0);
    }

    public function test_search_reads_the_language_the_list_is_showing(): void
    {
        $product = $this->named('Kabelis', ['name' => ['lt' => 'Kabelis', 'en' => 'Cable']]);

        Livewire::test(ListProducts::class)
            ->set('activeLocale', 'lt')
            ->searchTable('cable')
            ->assertCanNotSeeTableRecords([$product])
            ->set('activeLocale', 'en')
            ->searchTable('cable')
            ->assertCanSeeTableRecords([$product]);
    }

    // -- filters -----------------------------------------------------------

    public function test_filters_sit_above_the_table_and_apply_as_they_are_chosen(): void
    {
        $table = Livewire::test(ListProducts::class)->instance()->getTable();

        $this->assertSame(FiltersLayout::AboveContent, $table->getFiltersLayout());
        $this->assertFalse($table->hasDeferredFilters());
    }

    public function test_every_admin_table_gets_the_same_filter_layout(): void
    {
        foreach ([ListCatalogImports::class, ListContactRequests::class] as $page) {
            $table = Livewire::test($page)->instance()->getTable();

            $this->assertSame(FiltersLayout::AboveContent, $table->getFiltersLayout(), $page);
            $this->assertFalse($table->hasDeferredFilters(), $page);
        }
    }

    // -- order and paging --------------------------------------------------

    public function test_the_list_can_be_sorted_by_name_in_either_direction(): void
    {
        $charlie = $this->named('Charlie');
        $azuolas = $this->named('Ąžuolas');
        $bravo = $this->named('Bravo');

        Livewire::test(ListProducts::class)
            ->sortTable('name')
            ->assertCanSeeTableRecords([$azuolas, $bravo, $charlie], inOrder: true)
            ->sortTable('name', 'desc')
            ->assertCanSeeTableRecords([$charlie, $bravo, $azuolas], inOrder: true);
    }

    public function test_the_list_can_be_sorted_by_price_and_stock(): void
    {
        $cheap = Product::factory()->priced(100)->inStock(30)->create();
        $dear = Product::factory()->priced(9000)->inStock(2)->create();

        Livewire::test(ListProducts::class)
            ->sortTable('price_net_cents')
            ->assertCanSeeTableRecords([$cheap, $dear], inOrder: true)
            ->sortTable('stock_quantity')
            ->assertCanSeeTableRecords([$dear, $cheap], inOrder: true);
    }

    public function test_the_list_shows_the_price_without_and_with_vat(): void
    {
        $product = Product::factory()->priced(1999)->create();

        Livewire::test(ListProducts::class)
            ->assertTableColumnStateSet('price_net_cents', 1999, $product)
            ->assertTableColumnStateSet('price_gross', 2419, $product)
            ->assertTableColumnFormattedStateSet('price_net_cents', Number::currency(19.99, 'EUR', app()->getLocale()), $product)
            ->assertTableColumnFormattedStateSet('price_gross', Number::currency(24.19, 'EUR', app()->getLocale()), $product);
    }

    public function test_the_price_with_vat_sorts_in_the_same_order_as_the_price_without_it(): void
    {
        $cheap = Product::factory()->priced(100)->create();
        $dear = Product::factory()->priced(9000)->create();

        Livewire::test(ListProducts::class)
            ->sortTable('price_gross')
            ->assertCanSeeTableRecords([$cheap, $dear], inOrder: true)
            ->sortTable('price_gross', 'desc')
            ->assertCanSeeTableRecords([$dear, $cheap], inOrder: true);
    }

    public function test_the_list_never_offers_to_show_every_product_at_once(): void
    {
        $table = Livewire::test(ListProducts::class)->instance()->getTable();

        $this->assertSame([10, 25, 50, 100], $table->getPaginationPageOptions());
        $this->assertSame(25, $table->getDefaultPaginationPageOption());
    }

    public function test_the_list_pages_through_a_large_catalog(): void
    {
        Product::factory()->count(30)->create();

        $records = Livewire::test(ListProducts::class)->instance()->getTableRecords();

        $this->assertSame(25, $records->count());
        $this->assertSame(30, $records->total());
    }

    // -- stock in the list -------------------------------------------------

    public function test_stock_is_edited_right_in_the_list(): void
    {
        $product = Product::factory()->inStock(5)->create();

        Livewire::test(ListProducts::class)
            ->call('updateTableColumnState', 'stock_quantity', (string) $product->getKey(), '12');

        $this->assertSame(12, $product->fresh()->stock_quantity);
    }

    public function test_stock_in_the_list_can_be_set_to_zero(): void
    {
        $product = Product::factory()->inStock(5)->create();

        Livewire::test(ListProducts::class)
            ->call('updateTableColumnState', 'stock_quantity', (string) $product->getKey(), '0');

        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertFalse($product->fresh()->isInStock());
    }

    public function test_stock_in_the_list_rejects_negative_and_fractional_values(): void
    {
        $product = Product::factory()->inStock(5)->create();

        foreach (['-1', '1.5', 'abc', ''] as $value) {
            Livewire::test(ListProducts::class)
                ->call('updateTableColumnState', 'stock_quantity', (string) $product->getKey(), $value);
        }

        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    // -- bulk actions ------------------------------------------------------

    public function test_selected_products_can_be_deactivated_and_activated(): void
    {
        $selected = Product::factory()->count(2)->create();
        $untouched = Product::factory()->create();

        Livewire::test(ListProducts::class)
            ->selectTableRecords($selected->modelKeys())
            ->callAction(TestAction::make('deactivate')->table()->bulk())
            ->assertNotified(__('admin.products.bulk.deactivate_done'));

        $this->assertSame([false, false], $selected->fresh()->pluck('is_active')->all());
        $this->assertTrue($untouched->fresh()->is_active);

        Livewire::test(ListProducts::class)
            ->selectTableRecords($selected->modelKeys())
            ->callAction(TestAction::make('activate')->table()->bulk())
            ->assertNotified(__('admin.products.bulk.activate_done'));

        $this->assertSame([true, true], $selected->fresh()->pluck('is_active')->all());
    }

    public function test_a_bulk_action_reaches_every_selected_product_beyond_one_chunk(): void
    {
        $products = Product::factory()->count(205)->create();

        Livewire::test(ListProducts::class)
            ->selectTableRecords($products->modelKeys())
            ->callAction(TestAction::make('deactivate')->table()->bulk());

        $this->assertSame(0, Product::query()->where('is_active', true)->count());
    }

    public function test_a_bulk_status_change_does_not_touch_stock_or_the_slug(): void
    {
        $product = Product::factory()->inStock(7)->create();
        $slug = $product->getTranslations('slug');

        Livewire::test(ListProducts::class)
            ->selectTableRecords([$product->getKey()])
            ->callAction(TestAction::make('deactivate')->table()->bulk());

        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->assertEquals($slug, $product->fresh()->getTranslations('slug'));
    }

    public function test_stock_can_be_set_for_selected_products_only(): void
    {
        $selected = Product::factory()->inStock(9)->count(2)->create();
        $untouched = Product::factory()->inStock(9)->create();

        Livewire::test(ListProducts::class)
            ->selectTableRecords($selected->modelKeys())
            ->callAction(TestAction::make('setStock')->table()->bulk(), ['stock_quantity' => 0])
            ->assertHasNoActionErrors()
            ->assertNotified(__('admin.products.bulk.set_stock_done'));

        $this->assertSame([0, 0], $selected->fresh()->pluck('stock_quantity')->all());
        $this->assertSame(9, $untouched->fresh()->stock_quantity);
    }

    public function test_bulk_stock_must_be_a_non_negative_whole_number(): void
    {
        $product = Product::factory()->inStock(9)->create();

        foreach ([null, -1, 1.5] as $quantity) {
            Livewire::test(ListProducts::class)
                ->selectTableRecords([$product->getKey()])
                ->callAction(TestAction::make('setStock')->table()->bulk(), ['stock_quantity' => $quantity])
                ->assertHasActionErrors(['stock_quantity']);
        }

        $this->assertSame(9, $product->fresh()->stock_quantity);
    }

    // -- the form ----------------------------------------------------------

    public function test_saving_a_product_never_asks_for_a_manual_order(): void
    {
        $product = Product::factory()->create();

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->assertFormFieldDoesNotExist('order')
            ->assertFormFieldExists('stock_quantity')
            ->assertFormFieldExists('is_active');
    }
}

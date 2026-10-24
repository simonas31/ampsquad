<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Models\SlugRedirect;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductResourceTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): void
    {
        $this->actingAs(User::factory()->create());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validForm(array $overrides = []): array
    {
        return array_merge([
            'name' => 'EV charger consultation',
            'excerpt' => 'Help choosing a charger.',
            'description' => 'A longer description of the consultation.',
            'price_cents' => '19.99',
            'order' => 2,
            'is_active' => true,
        ], $overrides);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPrices(): array
    {
        return [
            'empty' => [''],
            'zero' => ['0'],
            'zero with decimals' => ['0.00'],
            'negative' => ['-5'],
            'text' => ['abc'],
            'sub-cent precision' => ['1.005'],
            'above the maximum' => ['100000.01'],
        ];
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function validPrices(): array
    {
        return [
            'whole euros' => ['5', 500],
            'two decimals' => ['19.99', 1999],
            'float-unsafe cents' => ['0.29', 29],
            'one decimal' => ['1.1', 110],
            'smallest' => ['0.01', 1],
            'largest' => ['100000', 10_000_000],
        ];
    }

    public function test_guests_cannot_reach_the_product_admin(): void
    {
        $this->get('/admin/products')->assertRedirect();
    }

    public function test_the_list_shows_products_and_hides_deleted_ones_by_default(): void
    {
        $this->signIn();
        $products = Product::factory()->count(2)->create();
        $deleted = Product::factory()->create();
        $deleted->delete();

        Livewire::test(ListProducts::class)
            ->assertOk()
            ->assertCanSeeTableRecords($products)
            ->assertCanNotSeeTableRecords([$deleted]);
    }

    public function test_the_list_is_ordered_by_the_order_column(): void
    {
        $this->signIn();
        $third = Product::factory()->create(['order' => 3]);
        $first = Product::factory()->create(['order' => 1]);
        $second = Product::factory()->create(['order' => 2]);

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$first, $second, $third], inOrder: true);
    }

    public function test_the_list_can_be_filtered_by_active_state(): void
    {
        $this->signIn();
        $active = Product::factory()->create();
        $inactive = Product::factory()->inactive()->create();

        Livewire::test(ListProducts::class)
            ->filterTable('is_active', true)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive])
            ->filterTable('is_active', false)
            ->assertCanSeeTableRecords([$inactive])
            ->assertCanNotSeeTableRecords([$active]);
    }

    public function test_deleted_products_can_be_listed_with_the_trashed_filter(): void
    {
        $this->signIn();
        $active = Product::factory()->create();
        $deleted = Product::factory()->create();
        $deleted->delete();

        Livewire::test(ListProducts::class)
            ->filterTable('trashed', false)
            ->assertCanSeeTableRecords([$deleted])
            ->assertCanNotSeeTableRecords([$active]);
    }

    public function test_a_product_can_be_enabled_and_disabled_from_the_list(): void
    {
        $this->signIn();
        $product = Product::factory()->create();

        Livewire::test(ListProducts::class)
            ->call('updateTableColumnState', 'is_active', (string) $product->getKey(), false);

        $this->assertFalse($product->fresh()->is_active);

        Livewire::test(ListProducts::class)
            ->call('updateTableColumnState', 'is_active', (string) $product->getKey(), true);

        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_products_can_be_reordered_from_the_list(): void
    {
        $this->signIn();
        $first = Product::factory()->create(['order' => 1]);
        $second = Product::factory()->create(['order' => 2]);

        Livewire::test(ListProducts::class)
            ->call('reorderTable', [$second->getKey(), $first->getKey()]);

        $this->assertLessThan($first->fresh()->order, $second->fresh()->order);
    }

    public function test_creating_a_product_stores_the_price_in_cents_and_generates_the_slug(): void
    {
        $this->signIn();

        Livewire::test(CreateProduct::class)
            ->assertFormFieldDoesNotExist('slug')
            ->assertFormSet(['is_active' => true, 'order' => 0])
            ->fillForm($this->validForm())
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->firstOrFail();
        $locale = app()->getLocale();

        $this->assertSame('EV charger consultation', $product->getTranslation('name', $locale));
        $this->assertSame('Help choosing a charger.', $product->getTranslation('excerpt', $locale));
        $this->assertSame('ev-charger-consultation', $product->getTranslation('slug', $locale));
        $this->assertSame(1999, $product->price_cents);
        $this->assertSame(2, $product->order);
        $this->assertTrue($product->is_active);
    }

    public function test_a_product_can_be_created_inactive(): void
    {
        $this->signIn();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->validForm(['is_active' => false]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertFalse(Product::query()->firstOrFail()->is_active);
    }

    public function test_name_and_price_are_required(): void
    {
        $this->signIn();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->validForm(['name' => '', 'price_cents' => null]))
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'price_cents' => 'required']);

        $this->assertSame(0, Product::query()->count());
    }

    #[DataProvider('invalidPrices')]
    public function test_invalid_prices_are_rejected(string $price): void
    {
        $this->signIn();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->validForm(['price_cents' => $price]))
            ->call('create')
            ->assertHasFormErrors(['price_cents']);

        $this->assertSame(0, Product::query()->count());
    }

    #[DataProvider('validPrices')]
    public function test_prices_are_converted_to_exact_cents(string $price, int $expectedCents): void
    {
        $this->signIn();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->validForm(['price_cents' => $price]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($expectedCents, Product::query()->firstOrFail()->price_cents);
    }

    public function test_the_edit_form_shows_the_price_in_euros(): void
    {
        $this->signIn();
        $product = Product::factory()->priced(1999)->create();

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->assertFormSet(['price_cents' => '19.99', 'is_active' => true]);
    }

    public function test_editing_a_product_updates_its_price_and_state_but_not_its_live_slug(): void
    {
        $this->signIn();
        $product = Product::factory()->priced(1999)->create();
        $slug = $product->getTranslation('slug', app()->getLocale());

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['name' => 'A new name', 'price_cents' => '25.50', 'is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $product->refresh();

        $this->assertSame('A new name', $product->getTranslation('name', app()->getLocale()));
        $this->assertSame(2550, $product->price_cents);
        $this->assertFalse($product->is_active);
        $this->assertSame($slug, $product->getTranslation('slug', app()->getLocale()));
    }

    public function test_the_slug_can_be_regenerated_with_a_redirect_from_the_old_one(): void
    {
        $this->signIn();
        $locale = app()->getLocale();
        $product = Product::factory()->create(['name' => [$locale => 'Old name']]);
        $oldSlug = $product->getTranslation('slug', $locale);

        $product->setTranslation('name', $locale, 'Brand new name')->save();
        $this->assertSame($oldSlug, $product->fresh()->getTranslation('slug', $locale));

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->callAction('regenerateSlug');

        $this->assertSame('brand-new-name', $product->fresh()->getTranslation('slug', $locale));
        $this->assertTrue(SlugRedirect::query()->where('old_slug', $oldSlug)->exists());
    }

    public function test_a_product_can_be_deleted_and_restored_from_its_edit_page(): void
    {
        $this->signIn();
        $product = Product::factory()->create();

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->callAction(DeleteAction::class);

        $this->assertSoftDeleted($product);

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->callAction(RestoreAction::class);

        $this->assertNotSoftDeleted($product);
    }

    public function test_products_can_never_be_force_deleted_from_the_admin(): void
    {
        $this->signIn();
        $product = Product::factory()->create();

        $this->assertFalse(Gate::allows('forceDelete', $product));
        $this->assertTrue(Gate::allows('delete', $product));
        $this->assertTrue(Gate::allows('restore', $product));
    }
}

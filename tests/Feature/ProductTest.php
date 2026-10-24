<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_is_created_with_translated_fields_and_an_integer_price(): void
    {
        $product = Product::query()->create([
            'name' => ['lt' => 'Elektros patikra', 'en' => 'Electrical inspection'],
            'price_cents' => 8900,
        ])->fresh();

        $this->assertSame('Elektros patikra', $product->getTranslation('name', 'lt'));
        $this->assertSame('Electrical inspection', $product->getTranslation('name', 'en'));
        $this->assertSame(8900, $product->price_cents);
    }

    public function test_new_products_are_active_and_unordered_by_default(): void
    {
        $product = Product::query()->create([
            'name' => ['lt' => 'Paslauga', 'en' => 'Service'],
            'price_cents' => 1000,
        ]);

        $this->assertTrue($product->is_active);
        $this->assertSame(0, $product->order);
        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_slug_is_generated_per_locale_from_the_name(): void
    {
        $product = Product::factory()->create([
            'name' => ['lt' => 'Įkroviklio konsultacija', 'en' => 'EV charger consultation'],
        ]);

        $this->assertSame('ikroviklio-konsultacija', $product->getTranslation('slug', 'lt'));
        $this->assertSame('ev-charger-consultation', $product->getTranslation('slug', 'en'));
    }

    public function test_products_with_the_same_name_get_distinct_slugs(): void
    {
        $name = ['lt' => 'Konsultacija', 'en' => 'Consultation'];

        $first = Product::factory()->create(['name' => $name]);
        $second = Product::factory()->create(['name' => $name]);

        $this->assertSame('consultation', $first->getTranslation('slug', 'en'));
        $this->assertSame('consultation-2', $second->getTranslation('slug', 'en'));
    }

    public function test_slug_cannot_be_mass_assigned(): void
    {
        $product = Product::query()->create([
            'name' => ['en' => 'Genuine'],
            'slug' => ['en' => 'hacked'],
            'price_cents' => 100,
        ]);

        $this->assertSame('genuine', $product->getTranslation('slug', 'en'));
    }

    public function test_database_enforces_unique_slugs_per_locale(): void
    {
        $first = Product::factory()->create();

        $this->expectException(QueryException::class);

        Product::factory()->create(['slug' => $first->getTranslations('slug')]);
    }

    public function test_slug_follows_the_name_until_the_product_has_been_on_sale(): void
    {
        $draft = Product::factory()->inactive()->create(['name' => ['en' => 'Old name']]);
        $draft->setTranslation('name', 'en', 'New name')->save();

        $this->assertSame('new-name', $draft->fresh()->getTranslation('slug', 'en'));

        $live = Product::factory()->create(['name' => ['en' => 'Live name']]);
        $live->setTranslation('name', 'en', 'Renamed')->save();

        $this->assertSame('live-name', $live->fresh()->getTranslation('slug', 'en'));
    }

    public function test_active_scope_excludes_inactive_and_soft_deleted_products(): void
    {
        $active = Product::factory()->create();
        Product::factory()->inactive()->create();
        Product::factory()->create()->delete();

        $this->assertSame([$active->id], Product::query()->active()->pluck('id')->all());
    }

    public function test_only_active_products_that_are_not_deleted_are_purchasable(): void
    {
        $this->assertTrue(Product::factory()->create()->isPurchasable());
        $this->assertFalse(Product::factory()->inactive()->create()->isPurchasable());

        $deleted = Product::factory()->create();
        $deleted->delete();

        $this->assertFalse($deleted->isPurchasable());
    }

    public function test_deleting_a_product_keeps_the_row(): void
    {
        $product = Product::factory()->create();

        $product->delete();

        $this->assertSoftDeleted($product);
        $this->assertNotNull(Product::query()->withTrashed()->find($product->id));
    }

    public function test_price_must_be_provided(): void
    {
        $this->expectException(QueryException::class);

        Product::query()->create(['name' => ['en' => 'Free?']]);
    }

    public function test_factory_states(): void
    {
        $this->assertFalse(Product::factory()->inactive()->create()->is_active);
        $this->assertSame(1234, Product::factory()->priced(1234)->create()->price_cents);
    }
}

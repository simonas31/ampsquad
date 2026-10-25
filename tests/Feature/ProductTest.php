<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductCategory;
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
        $this->assertSame(5, Product::factory()->inStock()->create()->stock_quantity);
        $this->assertSame(0, Product::factory()->outOfStock()->create()->stock_quantity);
    }

    public function test_sku_is_unique_but_products_without_one_are_allowed(): void
    {
        Product::factory()->count(2)->create();
        Product::factory()->create(['sku' => 'KRDS1663ZJ']);

        $this->assertSame(1, Product::query()->where('sku', 'KRDS1663ZJ')->count());

        $this->expectException(QueryException::class);

        Product::factory()->create(['sku' => 'KRDS1663ZJ']);
    }

    public function test_catalog_columns_are_mass_assignable_and_cast(): void
    {
        $product = Product::query()->create([
            'name' => ['lt' => 'Kronšteinas', 'en' => 'Kronšteinas'],
            'price_cents' => 2000,
            'sku' => 'KRDS1663ZJ',
            'ean' => '6954273658830',
            'stock_quantity' => 2,
            'image_url' => 'http://bkgrupe.lt/media/catalog/product/a.jpg',
        ])->fresh();

        $this->assertSame('KRDS1663ZJ', $product->sku);
        $this->assertSame('6954273658830', $product->ean);
        $this->assertSame(2, $product->stock_quantity);
        $this->assertSame('http://bkgrupe.lt/media/catalog/product/a.jpg', $product->image_url);
    }

    public function test_product_without_stock_tracking_counts_as_in_stock(): void
    {
        $this->assertNull(Product::factory()->create()->stock_quantity);
        $this->assertTrue(Product::factory()->create()->isInStock());
        $this->assertTrue(Product::factory()->inStock(1)->create()->isInStock());
        $this->assertFalse(Product::factory()->outOfStock()->create()->isInStock());
    }

    public function test_in_stock_scope_keeps_untracked_and_stocked_products(): void
    {
        $untracked = Product::factory()->create();
        $stocked = Product::factory()->inStock()->create();
        Product::factory()->outOfStock()->create();

        $this->assertEqualsCanonicalizing(
            [$untracked->id, $stocked->id],
            Product::query()->inStock()->pluck('id')->all(),
        );
    }

    public function test_product_belongs_to_a_category_and_a_manufacturer(): void
    {
        $category = ProductCategory::factory()->create();
        $manufacturer = Manufacturer::factory()->create();

        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'manufacturer_id' => $manufacturer->id,
        ]);

        $this->assertTrue($product->category->is($category));
        $this->assertTrue($product->manufacturer->is($manufacturer));
        $this->assertSame([$product->id], $category->products->pluck('id')->all());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Shop\ShopCatalog;
use App\Settings\ShopSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ShopPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setShopEnabled(true);
    }

    private function setShopEnabled(bool $enabled): void
    {
        $settings = app(ShopSettings::class);
        $settings->enabled = $enabled;
        $settings->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function homeProps(): array
    {
        $props = [];

        $this->get('/')->assertInertia(function (AssertableInertia $page) use (&$props) {
            $props = $page->toArray()['props'];
        });

        return $props;
    }

    // -- shop switched off -------------------------------------------------

    public function test_every_shop_page_is_not_found_while_the_shop_is_disabled(): void
    {
        $product = Product::factory()->create();
        $this->setShopEnabled(false);

        $this->get('/shop')->assertNotFound();
        $this->get('/shop/'.$product->getTranslation('slug', app()->getLocale()))->assertNotFound();
        $this->get('/cart')->assertNotFound();
    }

    public function test_a_disabled_shop_adds_no_nav_link_and_no_cart_link(): void
    {
        $this->setShopEnabled(false);

        $props = $this->homeProps();

        $this->assertNotContains('nav.shop', array_column($props['navigation'], 'labelKey'));
        $this->assertNull($props['cartSummary']);
        $this->assertArrayNotHasKey('cart', $props);
    }

    public function test_an_enabled_shop_adds_the_nav_link_before_contact_and_the_cart_summary(): void
    {
        $props = $this->homeProps();
        $labelKeys = array_column($props['navigation'], 'labelKey');

        $this->assertSame(['nav.home', 'nav.projects', 'nav.priceCalculator', 'nav.shop', 'nav.contact'], $labelKeys);
        $this->assertSame(route('shop.index'), array_column($props['navigation'], 'url', 'labelKey')['nav.shop']);
        $this->assertSame(
            ['url' => '/cart', 'itemsUrl' => '/cart/items', 'count' => 0],
            $props['cartSummary'],
        );
    }

    private function named(string $name): Product
    {
        return Product::factory()->create(['name' => ['lt' => $name, 'en' => $name]]);
    }

    // -- listing -----------------------------------------------------------

    public function test_adding_to_the_cart_can_refresh_the_header_without_rerunning_the_listing(): void
    {
        Product::factory()->count(3)->create();

        $this->get('/shop')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 3)
            ->has('categories')
            ->reloadOnly('cartSummary', fn (AssertableInertia $reload) => $reload
                ->has('cartSummary.count')
                ->missing('products')
                ->missing('categories')
                ->missing('manufacturers')
                ->missing('navigation')
                ->missing('pageLinks')
            )
        );
    }

    public function test_a_page_past_the_end_goes_to_the_last_page_that_exists(): void
    {
        Product::factory()->count(ShopCatalog::PER_PAGE * 2 + 1)->create();

        $this->get('/shop?page=9')->assertRedirect(route('shop.index', ['page' => 3]));
        $this->get('/shop?page=3')->assertOk();
    }

    public function test_a_page_past_the_end_keeps_the_filters(): void
    {
        Product::factory()->create(['sku' => 'KEEP']);

        $this->get('/shop?q=KEEP&page=5')->assertRedirect(route('shop.index', ['q' => 'KEEP']));
    }

    public function test_an_empty_shop_does_not_redirect_forever(): void
    {
        $this->get('/shop?page=2')->assertRedirect(route('shop.index'));
        $this->get('/shop')->assertOk();
    }

    public function test_a_product_says_how_many_are_left_only_when_few_are(): void
    {
        $this->assertSame(1, Product::factory()->inStock(1)->create()->lowStockCount());
        $this->assertSame(Product::LOW_STOCK_THRESHOLD, Product::factory()->inStock(Product::LOW_STOCK_THRESHOLD)->create()->lowStockCount());
        $this->assertNull(Product::factory()->inStock(Product::LOW_STOCK_THRESHOLD + 1)->create()->lowStockCount());
        $this->assertNull(Product::factory()->outOfStock()->create()->lowStockCount());

        $few = Product::factory()->inStock(3)->create();
        $many = Product::factory()->inStock(50)->create();

        $this->get(route('shop.show', $few->slug))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('product.lowStockCount', 3)
            ->where('product.inStock', true)
        );
        $this->get(route('shop.show', $many->slug))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('product.lowStockCount', null)
        );
    }

    public function test_the_listing_shows_active_products_alphabetically(): void
    {
        $third = $this->named('Charlie');
        $first = $this->named('Alfa');
        $second = $this->named('Bravo');
        Product::factory()->inactive()->create(['name' => ['lt' => 'Aaa', 'en' => 'Aaa']]);
        Product::factory()->create(['name' => ['lt' => 'Aab', 'en' => 'Aab']])->delete();

        $this->get('/shop')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Shop/Index')
            ->has('products.data', 3)
            ->where('products.data.0.id', $first->id)
            ->where('products.data.1.id', $second->id)
            ->where('products.data.2.id', $third->id)
        );
    }

    public function test_listing_products_carry_what_the_card_needs_and_no_description(): void
    {
        $product = Product::factory()->priced(1999)->create();

        $this->get('/shop')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('products.data.0.id', $product->id)
            ->where('products.data.0.name', $product->name)
            ->where('products.data.0.excerpt', $product->excerpt)
            ->where('products.data.0.priceNetCents', 1999)
            ->where('products.data.0.priceGrossCents', 2419)
            ->where('products.data.0.url', route('shop.show', $product->slug))
            ->where('products.data.0.imageUrl', null)
            ->missing('products.data.0.description')
        );
    }

    public function test_the_listing_is_paginated_by_twelve(): void
    {
        Product::factory()->count(13)->create();

        $this->get('/shop')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 12)
            ->where('products.meta.last_page', 2)
        );

        $this->get('/shop?page=2')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
        );
    }

    public function test_an_empty_shop_still_renders(): void
    {
        $this->get('/shop')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Shop/Index')
            ->has('products.data', 0)
        );
    }

    public function test_the_listing_has_breadcrumbs_and_seo(): void
    {
        $this->get('/shop')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('breadcrumbs.0.label', __('nav.home'))
            ->where('breadcrumbs.1.label', __('nav.shop'))
            ->where('breadcrumbs.1.url', null)
            ->where('seo.title', __('nav.shop').' - '.config('app.name'))
            ->where('seo.noindex', false)
        );
    }

    public function test_the_listing_loads_images_without_a_query_per_product(): void
    {
        Product::factory()->count(2)->create();
        $this->get('/shop');
        $queriesForTwo = $this->queriesForListing();

        Product::factory()->count(8)->create();
        $queriesForTen = $this->queriesForListing();

        $this->assertSame($queriesForTwo, $queriesForTen);
    }

    private function queriesForListing(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/shop')->assertOk();

        return count(DB::getQueryLog());
    }

    // -- product page ------------------------------------------------------

    public function test_a_product_page_shows_the_full_product(): void
    {
        $product = Product::factory()->priced(4900)->create();

        $this->get(route('shop.show', $product->slug))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Shop/Show')
            ->where('product.id', $product->id)
            ->where('product.name', $product->name)
            ->where('product.description', $product->description)
            ->where('product.priceNetCents', 4900)
            ->where('product.priceGrossCents', 5929)
            ->where('product.maxQuantity', 99)
            ->where('product.inStock', true)
            ->where('breadcrumbs.1.url', route('shop.index'))
            ->where('breadcrumbs.2.label', $product->name)
            ->where('seo.title', $product->name.' - '.config('app.name'))
        );
    }

    public function test_inactive_deleted_and_unknown_products_have_no_page(): void
    {
        $inactive = Product::factory()->inactive()->create();
        $deleted = Product::factory()->create();
        $deleted->delete();

        $this->get(route('shop.show', $inactive->slug))->assertNotFound();
        $this->get(route('shop.show', $deleted->slug))->assertNotFound();
        $this->get('/shop/no-such-product')->assertNotFound();
    }

    public function test_a_product_page_advertises_each_locales_own_slug(): void
    {
        Product::factory()->create(['slug' => ['lt' => 'lietuviskas-slug', 'en' => 'english-slug']]);

        $this->get('/shop/lietuviskas-slug')->assertInertia(function (AssertableInertia $page) {
            $props = $page->toArray()['props'];
            $alternates = collect($props['seo']['alternates'])->pluck('url', 'locale');
            $switcher = collect($props['locale']['available'])->pluck('url', 'code');

            $this->assertSame(url('/shop/lietuviskas-slug'), $props['seo']['canonical']);
            $this->assertSame(url('/en/shop/english-slug'), $alternates['en']);
            $this->assertSame(url('/en/shop/english-slug'), $switcher['en']);
        });
    }

    public function test_a_renamed_products_old_url_redirects_permanently(): void
    {
        $product = Product::factory()->create(['name' => ['lt' => 'Senas pavadinimas', 'en' => 'Old name']]);
        $oldSlug = $product->getTranslation('slug', 'lt');

        $product->setTranslation('name', 'lt', 'Naujas pavadinimas')->save();
        $product->regenerateSlug('lt');

        $this->get('/shop/'.$oldSlug)
            ->assertStatus(301)
            ->assertRedirect(url('/shop/naujas-pavadinimas'));
    }

    public function test_a_retired_url_of_a_product_no_longer_sold_is_not_found(): void
    {
        $product = Product::factory()->create(['name' => ['lt' => 'Senas pavadinimas', 'en' => 'Old name']]);
        $oldSlug = $product->getTranslation('slug', 'lt');
        $product->setTranslation('name', 'lt', 'Naujas pavadinimas')->save();
        $product->regenerateSlug('lt');
        $product->update(['is_active' => false]);

        $this->get('/shop/'.$oldSlug)->assertNotFound();
    }

    public function test_a_product_page_describes_the_offer_for_search_engines(): void
    {
        $product = Product::factory()->priced(1999)->create();

        $this->get(route('shop.show', $product->slug))->assertInertia(function (AssertableInertia $page) use ($product) {
            $schema = collect($page->toArray()['props']['seo']['jsonLd'])->firstWhere('@type', 'Product');

            $this->assertSame($product->name, $schema['name']);
            $this->assertSame('24.19', $schema['offers']['price'], 'the offer is the price with VAT');
            $this->assertSame('EUR', $schema['offers']['priceCurrency']);
        });
    }
}

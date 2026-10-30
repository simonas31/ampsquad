<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
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
            ->where('products.data.0.priceExclVatCents', 1999)
            ->where('products.data.0.priceInclVatCents', 2419)
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
            ->where('product.priceExclVatCents', 4900)
            ->where('product.priceInclVatCents', 5929)
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

    // -- breadcrumbs -------------------------------------------------------

    /**
     * @return array{ProductCategory, ProductCategory, ProductCategory}
     */
    private function networkCategories(): array
    {
        $network = ProductCategory::factory()->create(['name' => ['lt' => 'Tinklo įranga', 'en' => 'Network']]);
        $switches = ProductCategory::factory()->childOf($network)->create(['name' => ['lt' => 'Komutatoriai', 'en' => 'Switches']]);
        $poe = ProductCategory::factory()->childOf($switches)->create(['name' => ['lt' => 'PoE komutatoriai', 'en' => 'PoE switches']]);

        return [$network, $switches, $poe];
    }

    /**
     * @return array<string, mixed>
     */
    private function pageProps(string $url): array
    {
        $props = [];

        $this->get($url)->assertOk()->assertInertia(function (AssertableInertia $page) use (&$props): void {
            $props = $page->toArray()['props'];
        });

        return $props;
    }

    private function categoryUrl(string $slug): string
    {
        return route('shop.index', ['category' => $slug]);
    }

    public function test_a_chosen_category_adds_its_whole_path_to_the_listing_breadcrumbs(): void
    {
        $this->networkCategories();

        $this->assertSame([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.shop'), 'url' => route('shop.index')],
            ['label' => 'Tinklo įranga', 'url' => $this->categoryUrl('tinklo-iranga')],
            ['label' => 'Komutatoriai', 'url' => $this->categoryUrl('komutatoriai')],
            ['label' => 'PoE komutatoriai', 'url' => null],
        ], $this->pageProps('/shop?category=poe-komutatoriai')['breadcrumbs']);
    }

    public function test_a_top_level_category_is_the_last_crumb_without_a_link(): void
    {
        $this->networkCategories();

        $this->assertSame([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.shop'), 'url' => route('shop.index')],
            ['label' => 'Tinklo įranga', 'url' => null],
        ], $this->pageProps('/shop?category=tinklo-iranga')['breadcrumbs']);
    }

    public function test_an_unknown_category_leaves_the_plain_shop_breadcrumbs(): void
    {
        $crumbs = $this->pageProps('/shop?category=no-such-category')['breadcrumbs'];

        $this->assertSame([__('nav.home'), __('nav.shop')], array_column($crumbs, 'label'));
        $this->assertNull($crumbs[1]['url']);
    }

    public function test_search_and_other_filters_never_end_up_in_the_breadcrumb_links(): void
    {
        $this->networkCategories();

        $crumbs = $this->pageProps('/shop?category=poe-komutatoriai&q=switch&sort=newest&in_stock=1&min_price=5&page=1')['breadcrumbs'];

        $this->assertSame(
            [route('home'), route('shop.index'), $this->categoryUrl('tinklo-iranga'), $this->categoryUrl('komutatoriai'), null],
            array_column($crumbs, 'url'),
        );
    }

    public function test_the_listing_breadcrumbs_are_also_structured_data(): void
    {
        $this->networkCategories();

        $schema = collect($this->pageProps('/shop?category=komutatoriai')['seo']['jsonLd'])->firstWhere('@type', 'BreadcrumbList');

        $this->assertSame([1, 2, 3, 4], array_column($schema['itemListElement'], 'position'));
        $this->assertSame([__('nav.home'), __('nav.shop'), 'Tinklo įranga', 'Komutatoriai'], array_column($schema['itemListElement'], 'name'));
        $this->assertSame($this->categoryUrl('tinklo-iranga'), $schema['itemListElement'][2]['item']);
        $this->assertArrayNotHasKey('item', $schema['itemListElement'][3], 'the page itself carries no link');
    }

    public function test_the_category_path_is_named_in_the_visitors_language(): void
    {
        $this->networkCategories();

        // A real /en/ URL cannot be requested in tests; see HomeTest.
        app()->setLocale('en');

        $crumbs = $this->pageProps('/shop?category=poe-switches')['breadcrumbs'];

        $this->assertSame(['Network', 'Switches', 'PoE switches'], array_slice(array_column($crumbs, 'label'), 2));
        $this->assertSame($this->categoryUrl('network'), $crumbs[2]['url']);
        $this->assertSame($this->categoryUrl('switches'), $crumbs[3]['url']);
        $this->assertNull($crumbs[4]['url']);
    }

    public function test_a_product_page_shows_its_categorys_whole_path_with_every_step_linked(): void
    {
        [,, $poe] = $this->networkCategories();
        $product = Product::factory()->create(['product_category_id' => $poe->id]);

        $this->assertSame([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.shop'), 'url' => route('shop.index')],
            ['label' => 'Tinklo įranga', 'url' => $this->categoryUrl('tinklo-iranga')],
            ['label' => 'Komutatoriai', 'url' => $this->categoryUrl('komutatoriai')],
            ['label' => 'PoE komutatoriai', 'url' => $this->categoryUrl('poe-komutatoriai')],
            ['label' => $product->name, 'url' => null],
        ], $this->pageProps(route('shop.show', $product->slug))['breadcrumbs']);
    }

    public function test_a_product_in_a_top_level_category_has_just_that_one_step(): void
    {
        [$network] = $this->networkCategories();
        $product = Product::factory()->create(['product_category_id' => $network->id]);

        $crumbs = $this->pageProps(route('shop.show', $product->slug))['breadcrumbs'];

        $this->assertSame([__('nav.home'), __('nav.shop'), 'Tinklo įranga', $product->name], array_column($crumbs, 'label'));
        $this->assertSame($this->categoryUrl('tinklo-iranga'), $crumbs[2]['url']);
    }

    public function test_a_product_without_a_category_goes_straight_from_the_shop_to_the_product(): void
    {
        $product = Product::factory()->create(['product_category_id' => null]);

        $crumbs = $this->pageProps(route('shop.show', $product->slug))['breadcrumbs'];

        $this->assertSame([__('nav.home'), __('nav.shop'), $product->name], array_column($crumbs, 'label'));
        $this->assertSame(route('shop.index'), $crumbs[1]['url']);
    }

    public function test_the_product_breadcrumbs_are_also_structured_data_ending_on_the_product(): void
    {
        [,, $poe] = $this->networkCategories();
        $product = Product::factory()->create(['product_category_id' => $poe->id]);

        $schema = collect($this->pageProps(route('shop.show', $product->slug))['seo']['jsonLd'])->firstWhere('@type', 'BreadcrumbList');
        $items = $schema['itemListElement'];

        $this->assertSame([1, 2, 3, 4, 5, 6], array_column($items, 'position'));
        $this->assertSame($product->name, $items[5]['name']);
        $this->assertArrayNotHasKey('item', $items[5]);
        $this->assertSame($this->categoryUrl('poe-komutatoriai'), $items[4]['item']);
    }

    public function test_the_product_page_names_the_category_path_in_the_visitors_language(): void
    {
        [,, $poe] = $this->networkCategories();
        $product = Product::factory()->create([
            'product_category_id' => $poe->id,
            'slug' => ['lt' => 'lietuviskas-slug', 'en' => 'english-slug'],
        ]);

        app()->setLocale('en');

        $crumbs = $this->pageProps('/shop/english-slug')['breadcrumbs'];

        $this->assertSame(['Network', 'Switches', 'PoE switches', $product->getTranslation('name', 'en')], array_slice(array_column($crumbs, 'label'), 2));
        $this->assertSame($this->categoryUrl('poe-switches'), $crumbs[4]['url']);
    }
}

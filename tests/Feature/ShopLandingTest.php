<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Shop\ShopCatalog;
use App\Settings\ShopSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * What the plain first page of the shop adds to the results: the popular
 * shelf and the full category menu. Both are left out of every search,
 * filtered cut and later page, which are only results.
 */
class ShopLandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = app(ShopSettings::class);
        $settings->enabled = true;
        $settings->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function props(string $url = '/shop'): array
    {
        $props = [];

        $this->get($url)->assertOk()->assertInertia(function (AssertableInertia $page) use (&$props): void {
            $props = $page->toArray()['props'];
        });

        return $props;
    }

    /**
     * @return list<string>
     */
    private function popularNames(string $url = '/shop'): array
    {
        return array_column($this->props($url)['popularProducts'], 'name');
    }

    private function named(string $name, array $attributes = []): Product
    {
        return Product::factory()->create(['name' => ['lt' => $name, 'en' => $name], ...$attributes]);
    }

    // -- popular shelf -----------------------------------------------------

    public function test_the_shelf_holds_the_products_marked_popular(): void
    {
        $this->named('Marked', ['is_featured' => true]);
        $this->named('Not marked');

        $this->assertSame(['Marked'], $this->popularNames());
    }

    public function test_marked_products_that_cannot_be_shown_are_left_out(): void
    {
        $this->named('Shown', ['is_featured' => true]);
        $this->named('Switched off', ['is_featured' => true, 'is_active' => false]);
        $this->named('Deleted', ['is_featured' => true])->delete();

        $this->assertSame(['Shown'], $this->popularNames());
    }

    public function test_a_sold_out_marked_product_stays_but_after_those_in_stock(): void
    {
        $this->named('A sold out', ['is_featured' => true, 'stock_quantity' => 0]);
        $this->named('B in stock', ['is_featured' => true, 'stock_quantity' => 3]);

        $this->assertSame(['B in stock', 'A sold out'], $this->popularNames());
    }

    public function test_the_shelf_holds_no_more_than_its_limit(): void
    {
        Product::factory()->featured()->count(ShopCatalog::POPULAR_LIMIT + 3)->create();

        $this->assertCount(ShopCatalog::POPULAR_LIMIT, $this->popularNames());
    }

    public function test_without_marked_products_the_shelf_falls_back_to_the_newest_in_stock(): void
    {
        $this->named('Oldest', ['created_at' => now()->subDays(3)]);
        $this->named('Newest', ['created_at' => now()]);
        $this->named('Sold out', ['stock_quantity' => 0, 'created_at' => now()]);
        $this->named('Switched off', ['is_active' => false, 'created_at' => now()]);

        $this->assertSame(['Newest', 'Oldest'], $this->popularNames());
    }

    public function test_the_shelf_is_empty_when_the_shop_has_nothing_to_show(): void
    {
        $this->assertSame([], $this->props()['popularProducts']);
    }

    public function test_the_shelf_products_carry_what_a_card_shows(): void
    {
        $product = Product::factory()->featured()->inStock(2)->create(['sku' => 'SHELF-1']);

        $this->get('/shop')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('popularProducts', 1, fn (AssertableInertia $card) => $card
                ->where('id', $product->id)
                ->where('sku', 'SHELF-1')
                ->where('inStock', true)
                ->where('lowStockCount', 2)
                ->where('priceInclVatCents', $product->priceInclVatCents())
                ->etc()
            )
        );
    }

    // -- where it shows ----------------------------------------------------

    public function test_the_plain_first_page_is_the_landing(): void
    {
        Product::factory()->featured()->create();

        $props = $this->props();

        $this->assertIsArray($props['popularProducts']);
        $this->assertIsArray($props['categoryMenu']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function filteredCuts(): array
    {
        return [
            'search' => ['q=switch'],
            'category' => ['category=anything'],
            'manufacturer' => ['manufacturer=1'],
            'in stock only' => ['in_stock=1'],
            'minimum price' => ['min_price=5'],
            'maximum price' => ['max_price=50'],
            'sort' => ['sort=newest'],
        ];
    }

    #[DataProvider('filteredCuts')]
    public function test_a_filtered_cut_is_only_results(string $query): void
    {
        Product::factory()->featured()->count(2)->create();

        $props = $this->props('/shop?'.$query);

        $this->assertNull($props['popularProducts']);
        $this->assertNull($props['categoryMenu']);
    }

    public function test_a_later_page_is_only_results(): void
    {
        Product::factory()->featured()->count(ShopCatalog::PER_PAGE + 1)->create();

        $props = $this->props('/shop?page=2');

        $this->assertNull($props['popularProducts']);
        $this->assertNull($props['categoryMenu']);
    }

    public function test_a_header_cart_refresh_does_not_build_the_landing(): void
    {
        Product::factory()->featured()->create();

        $this->get('/shop')->assertInertia(fn (AssertableInertia $page) => $page
            ->reloadOnly('cartSummary', fn (AssertableInertia $reload) => $reload
                ->has('cartSummary.count')
                ->missing('popularProducts')
                ->missing('categoryMenu')
            )
        );
    }

    // -- category menu -----------------------------------------------------

    public function test_the_menu_opens_every_level_with_counts_that_include_what_is_below(): void
    {
        $energy = ProductCategory::factory()->create(['name' => ['lt' => 'Energija', 'en' => 'Energy']]);
        $solar = ProductCategory::factory()->childOf($energy)->create(['name' => ['lt' => 'Saulės elektrinės', 'en' => 'Solar']]);
        $inverters = ProductCategory::factory()->childOf($solar)->create(['name' => ['lt' => 'Inverteriai', 'en' => 'Inverters']]);
        Product::factory()->count(2)->create(['product_category_id' => $inverters->id]);
        Product::factory()->create(['product_category_id' => $energy->id]);

        $menu = $this->props()['categoryMenu'];

        $this->assertCount(1, $menu);
        $this->assertSame('Energija', $menu[0]['name']);
        $this->assertSame(3, $menu[0]['count']);
        $this->assertSame('Saulės elektrinės', $menu[0]['children'][0]['name']);
        $this->assertSame(2, $menu[0]['children'][0]['count']);
        $this->assertSame('Inverteriai', $menu[0]['children'][0]['children'][0]['name']);
        $this->assertSame([], $menu[0]['children'][0]['children'][0]['children']);
    }

    public function test_the_menu_leaves_out_categories_without_products(): void
    {
        $filled = ProductCategory::factory()->create();
        $empty = ProductCategory::factory()->create();
        $emptyChild = ProductCategory::factory()->childOf($filled)->create();
        Product::factory()->create(['product_category_id' => $filled->id]);

        $menu = $this->props()['categoryMenu'];

        $this->assertSame([$filled->id], array_column($menu, 'id'));
        $this->assertSame([], $menu[0]['children']);
        $this->assertNotContains($empty->id, array_column($menu, 'id'));
        $this->assertNotContains($emptyChild->id, array_column($menu[0]['children'], 'id'));
    }

    public function test_the_menu_does_not_count_products_that_are_switched_off(): void
    {
        $category = ProductCategory::factory()->create();
        Product::factory()->create(['product_category_id' => $category->id]);
        Product::factory()->inactive()->create(['product_category_id' => $category->id]);

        $this->assertSame(1, $this->props()['categoryMenu'][0]['count']);
    }

    public function test_the_sidebar_tree_still_opens_only_the_chosen_branch(): void
    {
        $network = ProductCategory::factory()->create();
        $switches = ProductCategory::factory()->childOf($network)->create();
        Product::factory()->create(['product_category_id' => $switches->id]);

        $tree = $this->props()['categories'];

        $this->assertSame([], $tree[0]['children']);
        $this->assertSame($switches->id, $this->props('/shop?category='.$network->slug)['categories'][0]['children'][0]['id']);
    }
}

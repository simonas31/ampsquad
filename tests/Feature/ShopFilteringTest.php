<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Settings\ShopSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShopFilteringTest extends TestCase
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
    private function listedSkus(string $query = ''): array
    {
        return array_column($this->props('/shop?'.$query)['products']['data'], 'sku');
    }

    private function named(string $name, array $attributes = []): Product
    {
        return Product::factory()->create(['name' => ['lt' => $name, 'en' => $name], ...$attributes]);
    }

    // -- search ------------------------------------------------------------

    public function test_search_matches_names_ignoring_case_and_accents(): void
    {
        $this->named('Hikvision kronšteinas DS-1663ZJ', ['sku' => 'A1']);
        $this->named('Tinklo komutatorius', ['sku' => 'A2']);

        $this->assertSame(['A1'], $this->listedSkus('q=KRONSTEINAS'));
        $this->assertSame(['A1'], $this->listedSkus('q=ds-1663'));
        $this->assertSame(['A2'], $this->listedSkus('q=komutatorius'));
        $this->assertSame([], $this->listedSkus('q=nothing'));
    }

    public function test_search_matches_sku_and_exact_ean(): void
    {
        $this->named('One', ['sku' => 'KRDS1663ZJ', 'ean' => '6954273658830']);
        $this->named('Two', ['sku' => 'CISINT-E', 'ean' => '12345678']);

        $this->assertSame(['KRDS1663ZJ'], $this->listedSkus('q=krds16'));
        $this->assertSame(['KRDS1663ZJ'], $this->listedSkus('q=6954273658830'));
        $this->assertSame([], $this->listedSkus('q=69542736'), 'EAN is matched whole, not by prefix');
    }

    public function test_every_word_of_a_search_must_match(): void
    {
        $this->named('Hikvision camera bracket', ['sku' => 'A1']);
        $this->named('Hikvision camera', ['sku' => 'A2']);
        $this->named('Ubiquiti bracket', ['sku' => 'A3']);

        $this->assertSame(['A1'], $this->listedSkus('q=hikvision+bracket'));
        $this->assertEqualsCanonicalizing(['A1', 'A2'], $this->listedSkus('q=hikvision'));
    }

    public function test_wildcard_characters_in_a_search_are_taken_literally(): void
    {
        $this->named('100% cotton', ['sku' => 'A1']);
        $this->named('Plain', ['sku' => 'A2']);

        $this->assertSame(['A1'], $this->listedSkus('q=100%25'));
        $this->assertSame([], $this->listedSkus('q=%25_%25x'));
    }

    public function test_search_looks_at_the_names_of_the_current_language(): void
    {
        Product::factory()->create(['sku' => 'A1', 'name' => ['lt' => 'Kabelis', 'en' => 'Cable']]);

        $this->assertSame(['A1'], $this->listedSkus('q=kabelis'));
        $this->assertSame([], $this->listedSkus('q=cable'));

        // A real /en/ URL cannot be requested in tests; see HomeTest.
        app()->setLocale('en');

        $this->assertSame(['A1'], $this->listedSkus('q=cable'));
        $this->assertSame([], $this->listedSkus('q=kabelis'));
    }

    public function test_search_only_uses_the_first_few_words_and_a_limited_length(): void
    {
        $this->named('alpha beta gamma delta epsilon', ['sku' => 'A1']);

        $this->assertSame(['A1'], $this->listedSkus('q=alpha+beta+gamma+delta+epsilon+nothing+more+words'));
        $this->assertSame(100, mb_strlen($this->props('/shop?q='.str_repeat('a', 300))['filters']['search']));
    }

    // -- category ----------------------------------------------------------

    public function test_the_category_filter_includes_sub_categories(): void
    {
        $network = ProductCategory::factory()->create(['name' => ['lt' => 'Tinklo įranga', 'en' => 'Network']]);
        $switches = ProductCategory::factory()->childOf($network)->create(['name' => ['lt' => 'Komutatoriai', 'en' => 'Switches']]);
        $cables = ProductCategory::factory()->create(['name' => ['lt' => 'Kabeliai', 'en' => 'Cables']]);

        Product::factory()->create(['sku' => 'IN-SUB', 'product_category_id' => $switches->id]);
        Product::factory()->create(['sku' => 'IN-ROOT', 'product_category_id' => $network->id]);
        Product::factory()->create(['sku' => 'ELSEWHERE', 'product_category_id' => $cables->id]);
        Product::factory()->create(['sku' => 'NONE']);

        $this->assertEqualsCanonicalizing(['IN-SUB', 'IN-ROOT'], $this->listedSkus('category=tinklo-iranga'));
        $this->assertSame(['IN-SUB'], $this->listedSkus('category=komutatoriai'));

        app()->setLocale('en');

        $this->assertSame(['IN-SUB'], $this->listedSkus('category=switches'));
    }

    public function test_a_category_that_does_not_exist_is_ignored(): void
    {
        $this->named('One', ['sku' => 'A1']);

        $props = $this->props('/shop?category=gone');

        $this->assertSame(['A1'], array_column($props['products']['data'], 'sku'));
        $this->assertNull($props['filters']['category']);
    }

    public function test_the_category_tree_lists_top_level_categories_with_their_counts(): void
    {
        $network = ProductCategory::factory()->create(['name' => ['lt' => 'Tinklo įranga', 'en' => 'Tinklo įranga']]);
        $switches = ProductCategory::factory()->childOf($network)->create();
        $cables = ProductCategory::factory()->create(['name' => ['lt' => 'Kabeliai', 'en' => 'Kabeliai']]);
        ProductCategory::factory()->create(['name' => ['lt' => 'Tuščia', 'en' => 'Tuščia']]);

        Product::factory()->count(2)->create(['product_category_id' => $switches->id]);
        Product::factory()->create(['product_category_id' => $network->id]);
        Product::factory()->create(['product_category_id' => $cables->id]);
        Product::factory()->inactive()->create(['product_category_id' => $cables->id]);

        $tree = $this->props()['categories'];

        $this->assertSame(['Kabeliai', 'Tinklo įranga'], array_column($tree, 'name'));
        $this->assertSame([1, 3], array_column($tree, 'count'));
        $this->assertSame([], $tree[1]['children'], 'sub-categories stay closed until their parent is chosen');
    }

    public function test_choosing_a_category_opens_its_branch_and_marks_it(): void
    {
        $network = ProductCategory::factory()->create(['name' => ['lt' => 'Tinklo įranga', 'en' => 'Tinklo įranga']]);
        $switches = ProductCategory::factory()->childOf($network)->create(['name' => ['lt' => 'Komutatoriai', 'en' => 'Komutatoriai']]);
        $poe = ProductCategory::factory()->childOf($switches)->create(['name' => ['lt' => 'PoE', 'en' => 'PoE']]);
        $routers = ProductCategory::factory()->childOf($network)->create(['name' => ['lt' => 'Maršrutizatoriai', 'en' => 'Maršrutizatoriai']]);
        Product::factory()->create(['product_category_id' => $poe->id]);
        Product::factory()->create(['product_category_id' => $routers->id]);

        $tree = $this->props('/shop?category=komutatoriai')['categories'];
        $networkNode = $tree[0];
        $switchesNode = collect($networkNode['children'])->firstWhere('name', 'Komutatoriai');

        $this->assertFalse($networkNode['isSelected']);
        $this->assertSame(['Komutatoriai', 'Maršrutizatoriai'], array_column($networkNode['children'], 'name'));
        $this->assertTrue($switchesNode['isSelected']);
        $this->assertSame(['PoE'], array_column($switchesNode['children'], 'name'));
        $this->assertSame('komutatoriai', $switchesNode['slug']);
    }

    public function test_category_counts_follow_the_other_filters_but_not_the_category_itself(): void
    {
        $network = ProductCategory::factory()->create(['name' => ['lt' => 'Tinklo įranga', 'en' => 'Tinklo įranga']]);
        $cables = ProductCategory::factory()->create(['name' => ['lt' => 'Kabeliai', 'en' => 'Kabeliai']]);
        $this->named('Cudy switch', ['product_category_id' => $network->id]);
        $this->named('Cudy router', ['product_category_id' => $network->id]);
        $this->named('Cudy cable', ['product_category_id' => $cables->id]);
        $this->named('Other cable', ['product_category_id' => $cables->id]);

        $counts = fn (string $query): array => array_column($this->props('/shop?'.$query)['categories'], 'count', 'name');

        $this->assertSame(['Kabeliai' => 2, 'Tinklo įranga' => 2], $counts(''));
        $this->assertSame(['Kabeliai' => 1, 'Tinklo įranga' => 2], $counts('q=cudy'));
        $this->assertSame(['Kabeliai' => 1, 'Tinklo įranga' => 2], $counts('q=cudy&category=kabeliai'));
    }

    // -- manufacturer ------------------------------------------------------

    public function test_the_manufacturer_filter_narrows_the_listing(): void
    {
        $cudy = Manufacturer::factory()->create(['name' => 'Cudy']);
        $ajax = Manufacturer::factory()->create(['name' => 'Ajax']);
        Product::factory()->create(['sku' => 'A1', 'manufacturer_id' => $cudy->id]);
        Product::factory()->create(['sku' => 'A2', 'manufacturer_id' => $ajax->id]);
        Product::factory()->create(['sku' => 'A3']);

        $this->assertSame(['A1'], $this->listedSkus('manufacturer='.$cudy->id));
        $this->assertCount(3, $this->listedSkus('manufacturer=abc'));
        $this->assertCount(3, $this->listedSkus('manufacturer=-1'));
        $this->assertSame([], $this->listedSkus('manufacturer=999999'));
    }

    public function test_manufacturer_options_count_products_under_the_other_filters(): void
    {
        $cudy = Manufacturer::factory()->create(['name' => 'Cudy']);
        $ajax = Manufacturer::factory()->create(['name' => 'Ajax']);
        Manufacturer::factory()->create(['name' => 'Unused']);
        $this->named('Smart switch', ['manufacturer_id' => $cudy->id]);
        $this->named('Smart hub', ['manufacturer_id' => $ajax->id]);
        $this->named('Smart hub 2', ['manufacturer_id' => $ajax->id]);
        $this->named('Plain cable', ['manufacturer_id' => $cudy->id]);
        Product::factory()->inactive()->create(['manufacturer_id' => $ajax->id]);

        $options = fn (string $query): array => array_column($this->props('/shop?'.$query)['manufacturers'], 'count', 'name');

        $this->assertSame(['Ajax' => 2, 'Cudy' => 2], $options(''));
        $this->assertSame(['Ajax' => 2, 'Cudy' => 1], $options('q=smart'));
        $this->assertSame(['Ajax' => 2, 'Cudy' => 1], $options('q=smart&manufacturer='.$cudy->id), 'the chosen manufacturer does not hide the others');
    }

    public function test_the_chosen_manufacturer_stays_listed_even_when_nothing_matches_it(): void
    {
        $cudy = Manufacturer::factory()->create(['name' => 'Cudy']);

        $options = $this->props('/shop?manufacturer='.$cudy->id)['manufacturers'];

        $this->assertSame([['id' => $cudy->id, 'name' => 'Cudy', 'count' => 0]], $options);
    }

    // -- stock, price, sort ------------------------------------------------

    public function test_the_stock_filter_hides_what_is_sold_out(): void
    {
        Product::factory()->create(['sku' => 'UNTRACKED']);
        Product::factory()->inStock()->create(['sku' => 'STOCKED']);
        Product::factory()->outOfStock()->create(['sku' => 'SOLD-OUT']);

        $this->assertCount(3, $this->listedSkus(''));
        $this->assertEqualsCanonicalizing(['UNTRACKED', 'STOCKED'], $this->listedSkus('in_stock=1'));
        $this->assertCount(3, $this->listedSkus('in_stock=0'));
    }

    public function test_the_price_filter_takes_euros(): void
    {
        Product::factory()->priced(500)->create(['sku' => 'CHEAP']);
        Product::factory()->priced(1999)->create(['sku' => 'MID']);
        Product::factory()->priced(5000)->create(['sku' => 'DEAR']);

        $this->assertEqualsCanonicalizing(['MID', 'DEAR'], $this->listedSkus('min_price=10'));
        $this->assertEqualsCanonicalizing(['CHEAP', 'MID'], $this->listedSkus('max_price=19.99'));
        $this->assertSame(['MID'], $this->listedSkus('min_price=5.01&max_price=49,99'));
        $this->assertSame([], $this->listedSkus('min_price=60&max_price=10'));
    }

    #[DataProvider('unusablePrices')]
    public function test_prices_that_make_no_sense_are_ignored(string $query): void
    {
        Product::factory()->count(2)->create();

        $this->assertCount(2, $this->listedSkus($query));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unusablePrices(): array
    {
        return [
            'text' => ['min_price=cheap'],
            'negative' => ['min_price=-5'],
            'too many decimals' => ['max_price=1.999'],
            'above any price' => ['max_price=100001'],
            'empty' => ['min_price=&max_price='],
            'list' => ['min_price[]=5'],
        ];
    }

    public function test_the_default_order_puts_what_can_be_bought_first_then_follows_the_admin(): void
    {
        Product::factory()->outOfStock()->create(['sku' => 'SOLD-OUT-1', 'order' => 1]);
        Product::factory()->create(['sku' => 'LATE', 'order' => 5]);
        Product::factory()->inStock()->create(['sku' => 'EARLY', 'order' => 2]);
        Product::factory()->outOfStock()->create(['sku' => 'SOLD-OUT-0', 'order' => 0]);

        $this->assertSame(['EARLY', 'LATE', 'SOLD-OUT-0', 'SOLD-OUT-1'], $this->listedSkus(''));
    }

    public function test_products_can_be_sorted(): void
    {
        $this->named('Bravo', ['sku' => 'B', 'price_cents' => 3000, 'created_at' => now()->subDays(2)]);
        $this->named('Ąžuolas', ['sku' => 'A', 'price_cents' => 1000, 'created_at' => now()->subDay()]);
        $this->named('Charlie', ['sku' => 'C', 'price_cents' => 2000, 'created_at' => now()]);

        $this->assertSame(['A', 'C', 'B'], $this->listedSkus('sort=price_asc'));
        $this->assertSame(['B', 'C', 'A'], $this->listedSkus('sort=price_desc'));
        $this->assertSame(['A', 'B', 'C'], $this->listedSkus('sort=name'));
        $this->assertSame(['C', 'A', 'B'], $this->listedSkus('sort=newest'));
    }

    public function test_an_unknown_sort_falls_back_to_the_default(): void
    {
        Product::factory()->create(['sku' => 'A1', 'order' => 1]);
        Product::factory()->create(['sku' => 'A2', 'order' => 0]);

        $props = $this->props('/shop?sort=drop-table');

        $this->assertSame(['A2', 'A1'], array_column($props['products']['data'], 'sku'));
        $this->assertSame('featured', $props['filters']['sort']);
    }

    public function test_pages_do_not_repeat_products_that_share_a_sort_value(): void
    {
        Product::factory()->count(14)->priced(1000)->create();

        $first = array_column($this->props('/shop?sort=price_asc')['products']['data'], 'id');
        $second = array_column($this->props('/shop?sort=price_asc&page=2')['products']['data'], 'id');

        $this->assertCount(12, $first);
        $this->assertCount(2, $second);
        $this->assertSame([], array_intersect($first, $second));
    }

    // -- the page ----------------------------------------------------------

    public function test_the_page_sends_back_the_filters_it_applied(): void
    {
        $cudy = Manufacturer::factory()->create();
        $network = ProductCategory::factory()->create(['name' => ['lt' => 'Tinklo įranga', 'en' => 'Tinklo įranga']]);

        $props = $this->props('/shop?q=%20%20switch%20%20&category=tinklo-iranga&manufacturer='.$cudy->id.'&in_stock=1&min_price=10&max_price=99.5&sort=price_desc');

        $this->assertSame([
            'search' => 'switch',
            'category' => 'tinklo-iranga',
            'manufacturer' => $cudy->id,
            'inStock' => true,
            'minPrice' => '10.00',
            'maxPrice' => '99.50',
            'sort' => 'price_desc',
        ], $props['filters']);
        $this->assertSame(route('shop.index'), $props['shopUrl']);
        $this->assertNotNull($network->id);
    }

    public function test_without_a_query_string_the_filters_are_empty(): void
    {
        $this->assertSame([
            'search' => '',
            'category' => null,
            'manufacturer' => null,
            'inStock' => false,
            'minPrice' => '',
            'maxPrice' => '',
            'sort' => 'featured',
        ], $this->props()['filters']);
    }

    public function test_array_valued_parameters_do_not_break_the_page(): void
    {
        Product::factory()->create();

        $this->get('/shop?q[]=x&category[]=y&manufacturer[]=1&in_stock[]=1&sort[]=price_asc')->assertOk();
    }

    public function test_filters_survive_pagination_links(): void
    {
        Product::factory()->count(13)->create(['name' => ['lt' => 'Cudy switch', 'en' => 'Cudy switch']]);

        $props = $this->props('/shop?q=cudy&sort=name');

        $this->assertStringContainsString('q=cudy', $props['products']['links']['next']);
        $this->assertStringContainsString('sort=name', $props['products']['links']['next']);
    }

    public function test_filtered_and_sorted_pages_are_not_indexed_but_the_plain_shop_is(): void
    {
        Product::factory()->create();

        $this->assertFalse($this->props()['seo']['noindex']);
        $this->assertFalse($this->props('/shop?page=1')['seo']['noindex']);
        $this->assertTrue($this->props('/shop?q=x')['seo']['noindex']);
        $this->assertTrue($this->props('/shop?sort=name')['seo']['noindex']);
        $this->assertTrue($this->props('/shop?in_stock=1')['seo']['noindex']);
    }

    public function test_listing_products_carry_their_catalog_details(): void
    {
        $category = ProductCategory::factory()->create(['name' => ['lt' => 'Komutatoriai', 'en' => 'Komutatoriai']]);
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cudy']);
        $product = Product::factory()->outOfStock()->create([
            'sku' => 'SWITCH-1',
            'image_url' => 'http://bkgrupe.lt/media/switch.jpg',
            'product_category_id' => $category->id,
            'manufacturer_id' => $manufacturer->id,
        ]);

        $card = $this->props()['products']['data'][0];

        $this->assertSame($product->id, $card['id']);
        $this->assertSame('SWITCH-1', $card['sku']);
        $this->assertSame('Cudy', $card['manufacturer']);
        $this->assertSame('Komutatoriai', $card['category']['name']);
        $this->assertSame(route('shop.index', ['category' => 'komutatoriai']), $card['category']['url']);
        $this->assertFalse($card['inStock']);
        $this->assertSame(0, $card['maxQuantity']);
        $this->assertSame('http://bkgrupe.lt/media/switch.jpg', $card['imageUrl']);
        $this->assertSame('http://bkgrupe.lt/media/switch.jpg', $card['imageThumbUrl']);
    }

    public function test_products_without_catalog_details_still_render(): void
    {
        Product::factory()->create();

        $card = $this->props()['products']['data'][0];

        $this->assertNull($card['sku']);
        $this->assertNull($card['manufacturer']);
        $this->assertNull($card['category']);
        $this->assertNull($card['imageUrl']);
        $this->assertTrue($card['inStock']);
        $this->assertSame(99, $card['maxQuantity']);
    }

    public function test_the_listing_runs_the_same_queries_however_many_products_it_shows(): void
    {
        // Every product matches the search: with no results, Eloquent skips
        // the eager loads and the count would differ for the wrong reason.
        $attributes = [
            'name' => ['lt' => 'Cable', 'en' => 'Cable'],
            'product_category_id' => ProductCategory::factory()->create()->id,
            'manufacturer_id' => Manufacturer::factory()->create()->id,
        ];

        Product::factory()->count(2)->create($attributes);
        $this->get('/shop');
        $queriesForTwo = $this->queriesFor('/shop?q=cable&sort=name');

        Product::factory()->count(9)->create($attributes);
        $queriesForEleven = $this->queriesFor('/shop?q=cable&sort=name');

        $this->assertSame($queriesForTwo, $queriesForEleven);
    }

    private function queriesFor(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();

        return count(DB::getQueryLog());
    }

    // -- the product page --------------------------------------------------

    public function test_a_product_page_carries_its_catalog_details_and_describes_them_to_search_engines(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cudy']);
        $product = Product::factory()->inStock(3)->priced(1999)->create([
            'sku' => 'SWITCH-1',
            'ean' => '6954273658830',
            'image_url' => 'http://bkgrupe.lt/media/switch.jpg',
            'manufacturer_id' => $manufacturer->id,
        ]);

        $this->get(route('shop.show', $product->slug))->assertInertia(function (AssertableInertia $page): void {
            $props = $page->toArray()['props'];
            $schema = collect($props['seo']['jsonLd'])->firstWhere('@type', 'Product');

            $this->assertSame('SWITCH-1', $props['product']['sku']);
            $this->assertSame('Cudy', $props['product']['manufacturer']);
            $this->assertTrue($props['product']['inStock']);
            $this->assertSame(3, $props['product']['maxQuantity']);
            $this->assertSame('http://bkgrupe.lt/media/switch.jpg', $props['product']['imageUrl']);
            $this->assertSame('http://bkgrupe.lt/media/switch.jpg', $props['seo']['ogImage']);
            $this->assertSame('SWITCH-1', $schema['sku']);
            $this->assertSame('6954273658830', $schema['gtin']);
            $this->assertSame(['@type' => 'Brand', 'name' => 'Cudy'], $schema['brand']);
            $this->assertSame('https://schema.org/InStock', $schema['offers']['availability']);
        });
    }

    public function test_a_sold_out_product_says_so_to_search_engines_and_can_still_be_viewed(): void
    {
        $product = Product::factory()->outOfStock()->create();

        $this->get(route('shop.show', $product->slug))->assertOk()->assertInertia(function (AssertableInertia $page): void {
            $props = $page->toArray()['props'];
            $schema = collect($props['seo']['jsonLd'])->firstWhere('@type', 'Product');

            $this->assertFalse($props['product']['inStock']);
            $this->assertSame('https://schema.org/OutOfStock', $schema['offers']['availability']);
            $this->assertArrayNotHasKey('sku', $schema);
            $this->assertArrayNotHasKey('brand', $schema);
        });
    }
}

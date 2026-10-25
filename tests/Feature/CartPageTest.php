<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartService;
use App\Settings\ShopSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CartPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = app(ShopSettings::class);
        $settings->enabled = true;
        $settings->save();
    }

    private function visitorWith(Cart $cart): static
    {
        return $this->withCookie(CartService::COOKIE, $cart->token);
    }

    public function test_a_visitor_without_a_cart_sees_an_empty_one(): void
    {
        $this->get('/cart')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Cart')
            ->where('cart.items', [])
            ->where('cart.subtotalCents', 0)
            ->where('cart.totalQuantity', 0)
            ->where('cart.hasUnavailableItems', false)
        );

        $this->assertSame(0, Cart::query()->count());
    }

    public function test_the_cart_page_is_not_indexable_and_has_breadcrumbs(): void
    {
        $this->get('/cart')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('seo.noindex', true)
            ->where('seo.title', __('nav.cart').' - '.config('app.name'))
            ->where('breadcrumbs.1.label', __('nav.cart'))
            ->where('breadcrumbs.1.url', null)
        );
    }

    public function test_lines_totals_and_urls_come_from_the_server(): void
    {
        $cart = Cart::factory()->create();
        $first = CartItem::factory()->for($cart)->for(Product::factory()->priced(1000)->create())->quantity(2)->create();
        $second = CartItem::factory()->for($cart)->for(Product::factory()->priced(250)->create())->quantity(4)->create();

        $this->visitorWith($cart)->get('/cart')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('cart.items', 2)
            ->where('cart.items.0.id', $first->id)
            ->where('cart.items.0.quantity', 2)
            ->where('cart.items.0.unitPriceCents', 1000)
            ->where('cart.items.0.lineTotalCents', 2000)
            ->where('cart.items.0.isAvailable', true)
            ->where('cart.items.0.url', '/cart/items/'.$first->id)
            ->where('cart.items.0.product.name', $first->product->name)
            ->where('cart.items.0.product.url', route('shop.show', $first->product->slug))
            ->where('cart.items.1.id', $second->id)
            ->where('cart.items.1.lineTotalCents', 1000)
            ->where('cart.subtotalCents', 3000)
            ->where('cart.totalQuantity', 6)
        );
    }

    public function test_lines_that_can_no_longer_be_bought_are_flagged_and_left_out_of_the_totals(): void
    {
        $cart = Cart::factory()->create();
        CartItem::factory()->for($cart)->for(Product::factory()->priced(1000)->create())->create();
        $inactive = CartItem::factory()->for($cart)->for(Product::factory()->inactive()->priced(500)->create())->create();
        $deletedProduct = Product::factory()->priced(700)->create();
        CartItem::factory()->for($cart)->for($deletedProduct)->create();
        $deletedProduct->delete();

        $this->visitorWith($cart)->get('/cart')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('cart.items', 3)
            ->where('cart.items.1.id', $inactive->id)
            ->where('cart.items.1.isAvailable', false)
            ->where('cart.items.1.product.url', null)
            ->where('cart.items.2.isAvailable', false)
            ->where('cart.items.2.product.name', $deletedProduct->name)
            ->where('cart.hasUnavailableItems', true)
            ->where('cart.subtotalCents', 1000)
            ->where('cart.totalQuantity', 1)
        );
    }

    public function test_totals_follow_the_current_product_price(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->priced(1000)->create();
        CartItem::factory()->for($cart)->for($product)->quantity(2)->create();

        $product->update(['price_cents' => 1500]);

        $this->visitorWith($cart)->get('/cart')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.items.0.unitPriceCents', 1500)
            ->where('cart.subtotalCents', 3000)
        );
    }

    public function test_a_visitor_only_ever_sees_their_own_cart(): void
    {
        $mine = Cart::factory()->create();
        CartItem::factory()->for($mine)->quantity(1)->create();
        $theirs = Cart::factory()->create();
        CartItem::factory()->for($theirs)->quantity(5)->create();

        $this->visitorWith($mine)->get('/cart')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('cart.items', 1)
            ->where('cart.totalQuantity', 1)
        );

        $this->withCookie(CartService::COOKIE, 'not-a-uuid')->get('/cart')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('cart.items', 0));
    }

    public function test_rendering_the_cart_does_not_query_per_line(): void
    {
        $few = Cart::factory()->create();
        CartItem::factory()->for($few)->create();
        $many = Cart::factory()->create();
        CartItem::factory()->count(8)->for($many)->create();

        $this->visitorWith($few)->get('/cart');

        DB::enableQueryLog();
        $this->visitorWith($few)->get('/cart');
        $queriesForOne = count(DB::getQueryLog());
        DB::flushQueryLog();

        $this->visitorWith($many)->get('/cart');
        $queriesForEight = count(DB::getQueryLog());

        $this->assertSame($queriesForOne, $queriesForEight);
    }

    // -- the header badge --------------------------------------------------

    public function test_the_shared_cart_count_sums_purchasable_units_of_the_visitors_cart_only(): void
    {
        $cart = Cart::factory()->create();
        CartItem::factory()->for($cart)->quantity(2)->create();
        CartItem::factory()->for($cart)->quantity(3)->create();
        CartItem::factory()->for($cart)->for(Product::factory()->inactive()->create())->quantity(7)->create();
        CartItem::factory()->quantity(9)->create();

        $this->visitorWith($cart)->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.count', 5)
        );
    }

    public function test_the_shared_cart_count_is_zero_without_a_cart(): void
    {
        CartItem::factory()->quantity(9)->create();

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.count', 0)
        );
    }

    public function test_the_cart_count_takes_one_query(): void
    {
        $cart = Cart::factory()->create();
        CartItem::factory()->count(3)->for($cart)->create();
        $request = Request::create('/', cookies: [CartService::COOKIE => $cart->token]);

        DB::enableQueryLog();
        $count = app(CartService::class)->totalQuantity($request);

        $this->assertSame(3, $count);
        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_the_cart_count_matches_the_cart_models_own_total(): void
    {
        $cart = Cart::factory()->create();
        CartItem::factory()->for($cart)->quantity(4)->create();
        CartItem::factory()->for($cart)->for(Product::factory()->inactive()->create())->quantity(2)->create();
        $deleted = Product::factory()->create();
        CartItem::factory()->for($cart)->for($deleted)->quantity(6)->create();
        $deleted->delete();
        $request = Request::create('/', cookies: [CartService::COOKIE => $cart->token]);

        $this->assertSame($cart->fresh()->totalQuantity(), app(CartService::class)->totalQuantity($request));
    }
}

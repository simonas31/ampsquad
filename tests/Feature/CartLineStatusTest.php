<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CartLineStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartService;
use App\Settings\ShopSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CartLineStatusTest extends TestCase
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

    /**
     * A line asking for $requested of a product that has only $stock left.
     */
    private function lineAskingFor(int $requested, int $stock, int $priceCents = 1000): CartItem
    {
        return CartItem::factory()
            ->for(Cart::factory()->create())
            ->for(Product::factory()->inStock($stock)->priced($priceCents)->create())
            ->quantity($requested)
            ->create();
    }

    // -- the line ----------------------------------------------------------

    public function test_a_line_is_available_while_the_stock_covers_it(): void
    {
        $line = $this->lineAskingFor(requested: 5, stock: 5);

        $this->assertSame(CartLineStatus::Available, $line->status());
        $this->assertSame(5, $line->countedQuantity());
        $this->assertSame(5000, $line->lineNetCents());
    }

    public function test_a_line_asking_for_more_than_is_in_stock_counts_only_what_is_left(): void
    {
        $line = $this->lineAskingFor(requested: 5, stock: 2);

        $this->assertSame(CartLineStatus::Reduced, $line->status());
        $this->assertSame(2, $line->countedQuantity());
        $this->assertSame(2000, $line->lineNetCents());
        $this->assertSame(5, $line->quantity);
    }

    public function test_a_line_counts_for_nothing_once_its_product_cannot_be_bought(): void
    {
        $soldOut = $this->lineAskingFor(requested: 3, stock: 5);
        $soldOut->product->update(['stock_quantity' => 0]);
        $inactive = $this->lineAskingFor(requested: 3, stock: 5);
        $inactive->product->update(['is_active' => false]);
        $deleted = $this->lineAskingFor(requested: 3, stock: 5);
        $deleted->product->delete();

        foreach ([$soldOut, $inactive, $deleted] as $line) {
            $line = $line->fresh();

            $this->assertSame(CartLineStatus::Unavailable, $line->status());
            $this->assertSame(0, $line->countedQuantity());
            $this->assertSame(0, $line->lineNetCents());
        }
    }

    public function test_only_an_unavailable_line_is_left_out_of_the_count(): void
    {
        $this->assertTrue(CartLineStatus::Available->isCounted());
        $this->assertTrue(CartLineStatus::Reduced->isCounted());
        $this->assertFalse(CartLineStatus::Unavailable->isCounted());
    }

    // -- the cart ----------------------------------------------------------

    public function test_the_cart_totals_count_what_is_in_stock(): void
    {
        $cart = Cart::factory()->create();
        CartItem::factory()->for($cart)->for(Product::factory()->inStock(10)->priced(1000)->create())->quantity(3)->create();
        CartItem::factory()->for($cart)->for(Product::factory()->inStock(2)->priced(500)->create())->quantity(9)->create();

        $this->assertSame(3 * 1000 + 2 * 500, $cart->subtotalNetCents());
        $this->assertSame(5, $cart->totalQuantity());
        $this->assertTrue($cart->hasLinesWithStatus(CartLineStatus::Reduced));
        $this->assertFalse($cart->hasLinesWithStatus(CartLineStatus::Unavailable));
    }

    public function test_the_header_count_matches_the_carts_own_total_with_every_kind_of_line(): void
    {
        $cart = Cart::factory()->create();
        CartItem::factory()->for($cart)->for(Product::factory()->inStock(10)->create())->quantity(3)->create();
        CartItem::factory()->for($cart)->for(Product::factory()->inStock(2)->create())->quantity(9)->create();
        CartItem::factory()->for($cart)->for(Product::factory()->outOfStock()->create())->quantity(4)->create();
        CartItem::factory()->for($cart)->for(Product::factory()->inactive()->create())->quantity(4)->create();
        $request = Request::create('/', cookies: [CartService::COOKIE => $cart->token]);

        $this->assertSame(5, app(CartService::class)->totalQuantity($request));
        $this->assertSame(5, $cart->fresh()->totalQuantity());
    }

    public function test_the_cart_page_shows_a_reduced_line_without_rewriting_it(): void
    {
        $line = $this->lineAskingFor(requested: 5, stock: 2, priceCents: 1200);

        $this->visitorWith($line->cart)->get('/cart')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.items.0.status', 'reduced')
            ->where('cart.items.0.quantity', 2)
            ->where('cart.items.0.requestedQuantity', 5)
            ->where('cart.items.0.maxQuantity', 2)
            ->where('cart.items.0.lineTotalNetCents', 2400)
            ->where('cart.subtotalNetCents', 2400)
            ->where('cart.totalQuantity', 2)
            ->where('cart.hasReducedItems', true)
            ->where('cart.hasUnavailableItems', false)
        );

        $this->assertSame(5, $line->fresh()->quantity);
    }

    public function test_a_cart_with_only_healthy_lines_has_no_flags(): void
    {
        $line = $this->lineAskingFor(requested: 2, stock: 5);

        $this->visitorWith($line->cart)->get('/cart')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.hasReducedItems', false)
            ->where('cart.hasUnavailableItems', false)
        );
    }

    // -- fixing a reduced line ---------------------------------------------

    public function test_adding_to_a_reduced_line_is_refused_and_leaves_it_alone(): void
    {
        $line = $this->lineAskingFor(requested: 5, stock: 2);

        $this->visitorWith($line->cart)
            ->post(route('cart.items.store'), ['product_id' => $line->product_id, 'quantity' => 1])
            ->assertSessionHasErrors(['quantity' => __('shop.all_in_cart', ['count' => 2])]);

        $this->assertSame(5, $line->fresh()->quantity);
    }

    public function test_setting_a_reduced_line_to_what_is_in_stock_makes_it_available_again(): void
    {
        $line = $this->lineAskingFor(requested: 5, stock: 2);

        $this->visitorWith($line->cart)
            ->patch(route('cart.items.update', $line), ['quantity' => 2])
            ->assertSessionHasNoErrors();

        $line = $line->fresh();

        $this->assertSame(2, $line->quantity);
        $this->assertSame(CartLineStatus::Available, $line->status());
    }

    public function test_a_reduced_line_cannot_be_set_above_the_stock_but_can_be_removed(): void
    {
        $line = $this->lineAskingFor(requested: 5, stock: 2);

        $this->visitorWith($line->cart)
            ->patch(route('cart.items.update', $line), ['quantity' => 3])
            ->assertSessionHasErrors(['quantity' => __('shop.not_enough_stock', ['count' => 2])]);

        $this->visitorWith($line->cart)->delete(route('cart.items.destroy', $line))->assertSessionHasNoErrors();
        $this->assertModelMissing($line);
    }

    // -- the props the page and the header read ----------------------------

    public function test_the_cart_page_keeps_the_header_summary_apart_from_its_own_cart(): void
    {
        $line = $this->lineAskingFor(requested: 2, stock: 5);

        $this->visitorWith($line->cart)->get('/cart')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cartSummary.url', '/cart')
            ->where('cartSummary.itemsUrl', '/cart/items')
            ->where('cartSummary.count', 2)
            ->where('cart.clearUrl', '/cart')
            ->missing('cart.itemsUrl')
            ->missing('cart.count')
        );
    }

    public function test_the_clear_url_empties_the_cart(): void
    {
        $line = $this->lineAskingFor(requested: 2, stock: 5);
        $page = $this->visitorWith($line->cart)->get('/cart');
        $clearUrl = $page->viewData('page')['props']['cart']['clearUrl'];

        $this->visitorWith($line->cart)->delete($clearUrl)->assertRedirect();

        $this->assertSame(0, $line->cart->items()->count());
    }
}

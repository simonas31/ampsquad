<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartService;
use App\Settings\ShopSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CartStockTest extends TestCase
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
     * @return array{Cart, CartItem}
     */
    private function cartWithItem(Product $product, int $quantity = 1): array
    {
        $cart = Cart::factory()->create();
        $item = CartItem::factory()->for($cart)->for($product)->quantity($quantity)->create();

        return [$cart, $item];
    }

    // -- the product -------------------------------------------------------

    public function test_a_product_with_no_stock_left_cannot_be_bought(): void
    {
        $this->assertTrue(Product::factory()->create()->isPurchasable());
        $this->assertTrue(Product::factory()->inStock(1)->create()->isPurchasable());
        $this->assertFalse(Product::factory()->outOfStock()->create()->isPurchasable());
        $this->assertFalse(Product::factory()->inactive()->inStock()->create()->isPurchasable());
    }

    public function test_the_most_that_can_be_bought_is_the_stock_or_the_cart_cap(): void
    {
        $this->assertSame(99, Product::factory()->create()->maxPurchasableQuantity());
        $this->assertSame(99, Product::factory()->inStock(500)->create()->maxPurchasableQuantity());
        $this->assertSame(99, Product::factory()->inStock(99)->create()->maxPurchasableQuantity());
        $this->assertSame(4, Product::factory()->inStock(4)->create()->maxPurchasableQuantity());
        $this->assertSame(0, Product::factory()->outOfStock()->create()->maxPurchasableQuantity());
    }

    // -- adding ------------------------------------------------------------

    public function test_a_sold_out_product_cannot_be_added_and_says_why(): void
    {
        $product = Product::factory()->outOfStock()->create();

        $this->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertSessionHasErrors(['product_id' => __('shop.out_of_stock')]);

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_an_inactive_product_is_still_reported_as_unavailable_not_sold_out(): void
    {
        $product = Product::factory()->inactive()->outOfStock()->create();

        $this->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertSessionHasErrors(['product_id' => __('shop.product_unavailable')]);
    }

    public function test_no_more_than_the_stock_can_be_added(): void
    {
        $product = Product::factory()->inStock(2)->create();

        $this->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 3])
            ->assertSessionHasErrors(['quantity' => __('shop.not_enough_stock', ['count' => 2])]);

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_the_stock_can_be_added_in_steps_but_not_passed(): void
    {
        $product = Product::factory()->inStock(3)->create();
        $cart = Cart::factory()->create();

        $this->visitorWith($cart)->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 2])
            ->assertSessionHasNoErrors();
        $this->visitorWith($cart)->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, CartItem::query()->sole()->quantity);

        $this->visitorWith($cart)->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertSessionHasErrors(['quantity' => __('shop.not_enough_stock', ['count' => 3])]);

        $this->assertSame(3, CartItem::query()->sole()->quantity);
    }

    public function test_plenty_of_stock_still_stops_at_the_cart_cap(): void
    {
        $product = Product::factory()->inStock(500)->create();
        $cart = Cart::factory()->create();

        $this->visitorWith($cart)->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 99])
            ->assertSessionHasNoErrors();
        $this->visitorWith($cart)->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertSessionHasErrors(['quantity' => __('shop.max_quantity', ['max' => 99])]);
    }

    public function test_the_stock_messages_are_translated(): void
    {
        $this->assertSame('Sandėlyje liko tik 2 vnt.', trans('shop.not_enough_stock', ['count' => 2], 'lt'));
        $this->assertSame('Šios prekės nėra sandėlyje.', trans('shop.out_of_stock', [], 'lt'));
    }

    // -- changing a line ---------------------------------------------------

    public function test_a_line_can_be_set_up_to_the_stock_but_not_past_it(): void
    {
        [$cart, $item] = $this->cartWithItem(Product::factory()->inStock(4)->create());

        $this->visitorWith($cart)->patch(route('cart.items.update', $item), ['quantity' => 4])
            ->assertSessionHasNoErrors();
        $this->assertSame(4, $item->fresh()->quantity);

        $this->visitorWith($cart)->patch(route('cart.items.update', $item), ['quantity' => 5])
            ->assertSessionHasErrors(['quantity' => __('shop.not_enough_stock', ['count' => 4])]);
        $this->assertSame(4, $item->fresh()->quantity);
    }

    public function test_a_line_whose_product_sold_out_cannot_be_changed_but_can_be_removed(): void
    {
        $product = Product::factory()->inStock(5)->create();
        [$cart, $item] = $this->cartWithItem($product, 2);
        $product->update(['stock_quantity' => 0]);

        $this->visitorWith($cart)->patch(route('cart.items.update', $item), ['quantity' => 1])
            ->assertSessionHasErrors(['product_id' => __('shop.out_of_stock')]);
        $this->assertSame(2, $item->fresh()->quantity);

        $this->visitorWith($cart)->delete(route('cart.items.destroy', $item))->assertSessionHasNoErrors();
        $this->assertModelMissing($item);
    }

    // -- showing the cart --------------------------------------------------

    public function test_a_sold_out_line_is_flagged_and_left_out_of_the_totals(): void
    {
        $available = Product::factory()->priced(1000)->inStock(5)->create();
        $soldOut = Product::factory()->priced(2000)->inStock(5)->create();
        $cart = Cart::factory()->create();
        CartItem::factory()->for($cart)->for($available)->quantity(2)->create();
        CartItem::factory()->for($cart)->for($soldOut)->quantity(1)->create();
        $soldOut->update(['stock_quantity' => 0]);

        $this->visitorWith($cart)->get('/cart')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.subtotalCents', 2000)
            ->where('cart.totalQuantity', 2)
            ->where('cart.hasUnavailableItems', true)
            ->where('cart.items.0.isAvailable', true)
            ->where('cart.items.0.maxQuantity', 5)
            ->where('cart.items.1.isAvailable', false)
            ->where('cart.items.1.maxQuantity', 0)
            ->where('cart.items.1.product.url', null)
        );
    }

    public function test_the_header_count_leaves_out_sold_out_lines(): void
    {
        $available = Product::factory()->inStock(5)->create();
        $soldOut = Product::factory()->inStock(5)->create();
        $cart = Cart::factory()->create();
        CartItem::factory()->for($cart)->for($available)->quantity(2)->create();
        CartItem::factory()->for($cart)->for($soldOut)->quantity(3)->create();
        $soldOut->update(['stock_quantity' => 0]);

        $this->visitorWith($cart)->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.count', 2)
        );
    }

    public function test_a_cart_line_shows_the_suppliers_image_when_there_is_no_upload(): void
    {
        $product = Product::factory()->create(['image_url' => 'http://bkgrupe.lt/media/a.jpg']);
        [$cart] = $this->cartWithItem($product);

        $this->visitorWith($cart)->get('/cart')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.items.0.product.imageThumbUrl', 'http://bkgrupe.lt/media/a.jpg')
        );
    }
}

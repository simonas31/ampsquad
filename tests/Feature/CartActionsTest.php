<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartService;
use App\Settings\ShopSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CartActionsTest extends TestCase
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

    private function visitorWith(Cart $cart): static
    {
        return $this->withCookie(CartService::COOKIE, $cart->token);
    }

    /**
     * @return array{Cart, CartItem}
     */
    private function cartWithItem(?Product $product = null, int $quantity = 1): array
    {
        $cart = Cart::factory()->create();
        $item = CartItem::factory()
            ->for($cart)
            ->for($product ?? Product::factory()->create())
            ->quantity($quantity)
            ->create();

        return [$cart, $item];
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidQuantities(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
            'above the cap' => [100],
            'fractional' => [1.5],
            'text' => ['many'],
            'empty' => [''],
            'null' => [null],
        ];
    }

    // -- adding ------------------------------------------------------------

    public function test_adding_a_product_creates_a_cart_a_line_and_the_cookie(): void
    {
        $product = Product::factory()->priced(1500)->create();

        $this->from('/shop')
            ->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 2])
            ->assertRedirect('/shop')
            ->assertSessionHasNoErrors();

        $cart = Cart::query()->with('items')->sole();

        $this->assertCount(1, $cart->items);
        $this->assertSame($product->id, $cart->items->first()->product_id);
        $this->assertSame(2, $cart->items->first()->quantity);
        $this->assertSame(3000, $cart->subtotalNetCents());
    }

    public function test_the_cart_cookie_is_http_only_lax_and_holds_the_cart_token(): void
    {
        $product = Product::factory()->create();

        $response = $this->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 1]);

        $response->assertCookie(CartService::COOKIE, Cart::query()->sole()->token);

        $cookie = $response->getCookie(CartService::COOKIE, decrypt: false);

        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertGreaterThan(now()->addDays(29)->timestamp, $cookie->getExpiresTime());
    }

    public function test_adding_the_same_product_again_raises_the_existing_line(): void
    {
        $product = Product::factory()->create();
        [$cart] = $this->cartWithItem($product, 2);

        $this->visitorWith($cart)
            ->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 3])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $cart->items()->count());
        $this->assertSame(5, $cart->items()->sole()->quantity);
        $this->assertSame(1, Cart::query()->count());
    }

    public function test_different_products_get_their_own_lines(): void
    {
        [$cart] = $this->cartWithItem(Product::factory()->priced(1000)->create(), 1);
        $other = Product::factory()->priced(250)->create();

        $this->visitorWith($cart)
            ->post(route('cart.items.store'), ['product_id' => $other->id, 'quantity' => 4]);

        $cart->refresh();

        $this->assertSame(2, $cart->items()->count());
        $this->assertSame(2000, $cart->subtotalNetCents());
        $this->assertSame(5, $cart->totalQuantity());
    }

    public function test_a_line_can_reach_but_not_pass_the_quantity_cap(): void
    {
        $product = Product::factory()->create();
        [$cart, $item] = $this->cartWithItem($product, 98);

        $this->visitorWith($cart)
            ->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertSessionHasNoErrors();

        $this->assertSame(99, $item->fresh()->quantity);

        $this->visitorWith($cart)
            ->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(99, $item->fresh()->quantity);
    }

    #[DataProvider('invalidQuantities')]
    public function test_invalid_quantities_are_rejected_without_creating_a_cart(mixed $quantity): void
    {
        $product = Product::factory()->create();

        $this->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => $quantity])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(0, Cart::query()->count());
        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_validation_messages_name_the_fields_in_lithuanian(): void
    {
        $product = Product::factory()->create();

        $this->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 'abc'])
            ->assertSessionHasErrors(['quantity' => 'kiekis turi būti sveikasis skaičius.']);
    }

    public function test_a_missing_quantity_is_rejected(): void
    {
        $product = Product::factory()->create();

        $this->post(route('cart.items.store'), ['product_id' => $product->id])
            ->assertSessionHasErrors('quantity');
    }

    public function test_inactive_deleted_and_unknown_products_cannot_be_added(): void
    {
        $inactive = Product::factory()->inactive()->create();
        $deleted = Product::factory()->create();
        $deleted->delete();

        foreach ([$inactive->id, $deleted->id, 999_999, 'abc', '', null] as $productId) {
            $this->post(route('cart.items.store'), ['product_id' => $productId, 'quantity' => 1])
                ->assertSessionHasErrors('product_id');
        }

        $this->assertSame(0, Cart::query()->count());
        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_the_unavailable_product_message_is_translated(): void
    {
        $inactive = Product::factory()->inactive()->create();

        $this->post(route('cart.items.store'), ['product_id' => $inactive->id, 'quantity' => 1])
            ->assertSessionHasErrors(['product_id' => __('shop.product_unavailable')]);
    }

    public function test_a_client_supplied_price_is_ignored(): void
    {
        $product = Product::factory()->priced(2000)->create();

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
            'price_net_cents' => 1,
            'unit_price' => 0,
            'price' => 0.01,
        ])->assertSessionHasNoErrors();

        $this->assertSame(4000, Cart::query()->sole()->subtotalNetCents());
    }

    public function test_a_malformed_or_unknown_cookie_just_starts_a_new_cart(): void
    {
        $product = Product::factory()->create();
        $payload = ['product_id' => $product->id, 'quantity' => 1];

        $this->withCookie(CartService::COOKIE, 'not-a-uuid')->post(route('cart.items.store'), $payload);
        $this->withCookie(CartService::COOKIE, (string) Str::uuid())->post(route('cart.items.store'), $payload);

        $this->assertSame(2, Cart::query()->count());
        $this->assertSame(2, CartItem::query()->count());
    }

    // -- updating ----------------------------------------------------------

    public function test_a_lines_quantity_can_be_set_to_an_exact_value(): void
    {
        [$cart, $item] = $this->cartWithItem(quantity: 5);

        $this->visitorWith($cart)
            ->from('/cart')
            ->patch(route('cart.items.update', $item), ['quantity' => 2])
            ->assertRedirect('/cart')
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $item->fresh()->quantity);

        $this->visitorWith($cart)
            ->patch(route('cart.items.update', $item), ['quantity' => 99]);

        $this->assertSame(99, $item->fresh()->quantity);
    }

    #[DataProvider('invalidQuantities')]
    public function test_invalid_quantities_cannot_be_set(mixed $quantity): void
    {
        [$cart, $item] = $this->cartWithItem(quantity: 3);

        $this->visitorWith($cart)
            ->patch(route('cart.items.update', $item), ['quantity' => $quantity])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(3, $item->fresh()->quantity);
    }

    public function test_a_line_whose_product_is_no_longer_sold_cannot_be_changed(): void
    {
        $product = Product::factory()->create();
        [$cart, $item] = $this->cartWithItem($product, 2);
        $product->update(['is_active' => false]);

        $this->visitorWith($cart)
            ->patch(route('cart.items.update', $item), ['quantity' => 5])
            ->assertSessionHasErrors('product_id');

        $this->assertSame(2, $item->fresh()->quantity);
    }

    public function test_a_line_whose_product_is_no_longer_sold_can_still_be_removed(): void
    {
        $product = Product::factory()->create();
        [$cart, $item] = $this->cartWithItem($product);
        $product->delete();

        $this->visitorWith($cart)
            ->delete(route('cart.items.destroy', $item))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($item);
    }

    // -- removing and clearing ---------------------------------------------

    public function test_a_line_can_be_removed(): void
    {
        [$cart, $item] = $this->cartWithItem();
        CartItem::factory()->for($cart)->create();

        $this->visitorWith($cart)
            ->from('/cart')
            ->delete(route('cart.items.destroy', $item))
            ->assertRedirect('/cart');

        $this->assertModelMissing($item);
        $this->assertSame(1, $cart->items()->count());
    }

    public function test_the_cart_can_be_cleared_without_touching_other_carts(): void
    {
        [$cart] = $this->cartWithItem();
        CartItem::factory()->for($cart)->create();
        [$otherCart, $otherItem] = $this->cartWithItem();

        $this->visitorWith($cart)
            ->from('/cart')
            ->delete(route('cart.destroy'))
            ->assertRedirect('/cart');

        $this->assertSame(0, $cart->items()->count());
        $this->assertModelExists($cart);
        $this->assertModelExists($otherItem);
        $this->assertSame(1, $otherCart->items()->count());
    }

    public function test_clearing_without_a_cart_is_a_harmless_no_op(): void
    {
        $this->delete(route('cart.destroy'))->assertRedirect();

        $this->assertSame(0, Cart::query()->count());
    }

    // -- ownership ---------------------------------------------------------

    public function test_another_visitors_line_cannot_be_updated_or_removed(): void
    {
        [, $item] = $this->cartWithItem(quantity: 3);
        $intruder = Cart::factory()->create();

        $this->visitorWith($intruder)
            ->patch(route('cart.items.update', $item), ['quantity' => 1])
            ->assertNotFound();

        $this->visitorWith($intruder)
            ->delete(route('cart.items.destroy', $item))
            ->assertNotFound();

        $this->assertSame(3, $item->fresh()->quantity);
    }

    public function test_lines_cannot_be_changed_without_a_cart_cookie(): void
    {
        [, $item] = $this->cartWithItem(quantity: 3);

        $this->patch(route('cart.items.update', $item), ['quantity' => 1])->assertNotFound();
        $this->delete(route('cart.items.destroy', $item))->assertNotFound();
        $this->withCookie(CartService::COOKIE, 'garbage')
            ->delete(route('cart.items.destroy', $item))
            ->assertNotFound();

        $this->assertSame(3, $item->fresh()->quantity);
    }

    public function test_unknown_lines_are_not_found(): void
    {
        $cart = Cart::factory()->create();

        $this->visitorWith($cart)
            ->patch(route('cart.items.update', 999_999), ['quantity' => 1])
            ->assertNotFound();
    }

    public function test_adding_never_touches_another_visitors_cart(): void
    {
        $product = Product::factory()->create();
        [$other, $otherItem] = $this->cartWithItem($product, 4);

        $this->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 1]);

        $this->assertSame(4, $otherItem->fresh()->quantity);
        $this->assertSame(2, Cart::query()->count());
        $this->assertSame(1, $other->items()->count());
    }

    // -- shop switched off -------------------------------------------------

    public function test_every_cart_action_is_not_found_while_the_shop_is_disabled(): void
    {
        $product = Product::factory()->create();
        [$cart, $item] = $this->cartWithItem($product, 2);
        $this->setShopEnabled(false);

        $this->visitorWith($cart)
            ->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertNotFound();
        $this->visitorWith($cart)
            ->patch(route('cart.items.update', $item), ['quantity' => 5])
            ->assertNotFound();
        $this->visitorWith($cart)
            ->delete(route('cart.items.destroy', $item))
            ->assertNotFound();
        $this->visitorWith($cart)
            ->delete(route('cart.destroy'))
            ->assertNotFound();

        $this->assertSame(2, $item->fresh()->quantity);
        $this->assertSame(1, Cart::query()->count());
    }

    public function test_a_disabled_shop_creates_no_cart_or_cookie(): void
    {
        $product = Product::factory()->create();
        $this->setShopEnabled(false);

        $this->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertNotFound()
            ->assertCookieMissing(CartService::COOKIE);

        $this->assertSame(0, Cart::query()->count());
    }

    public function test_re_enabling_the_shop_restores_the_cart_actions(): void
    {
        $product = Product::factory()->create();
        $payload = ['product_id' => $product->id, 'quantity' => 1];

        $this->setShopEnabled(false);
        $this->post(route('cart.items.store'), $payload)->assertNotFound();

        $this->setShopEnabled(true);
        $this->post(route('cart.items.store'), $payload)->assertSessionHasNoErrors();

        $this->assertSame(1, CartItem::query()->count());
    }

    // -- abuse -------------------------------------------------------------

    public function test_cart_actions_are_rate_limited(): void
    {
        $product = Product::factory()->create();
        $payload = ['product_id' => $product->id, 'quantity' => 1];

        for ($i = 0; $i < 60; $i++) {
            $this->post(route('cart.items.store'), $payload)->assertRedirect();
        }

        $this->post(route('cart.items.store'), $payload)->assertStatus(429);
    }
}

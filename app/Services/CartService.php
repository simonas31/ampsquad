<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The guest cart behind the shop. A cart belongs to whoever holds its
 * unguessable token in the cookie, and no route ever carries a cart id, so
 * one visitor cannot name another's cart or lines.
 *
 * Every change locks the cart's row first. That serialises concurrent
 * requests for the same cart (a double-clicked button, two tabs), so the
 * unique line per product and the quantity cap hold without retry loops.
 *
 * Prices are never an input here: totals are worked out from the products
 * (see Cart::subtotalCents()).
 */
class CartService
{
    public const string COOKIE = 'cart_token';

    private const int COOKIE_LIFETIME_MINUTES = 60 * 24 * 30;

    /**
     * The visitor's cart, or null when they have none yet (or the cookie
     * is missing, malformed, or points at a cart that no longer exists).
     */
    public function current(Request $request): ?Cart
    {
        $token = $request->cookie(self::COOKIE);

        if (! is_string($token) || ! Str::isUuid($token)) {
            return null;
        }

        return Cart::query()->where('token', $token)->first();
    }

    /**
     * The visitor's cart, created on first use. Carts are only created when
     * something is added, so browsing or bots never leave empty rows behind.
     * The cookie is queued again every time, which renews it.
     */
    public function currentOrCreate(Request $request): Cart
    {
        $cart = $this->current($request) ?? Cart::query()->create();

        Cookie::queue(Cookie::make(
            name: self::COOKIE,
            value: $cart->token,
            minutes: self::COOKIE_LIFETIME_MINUTES,
            path: '/',
            secure: $request->isSecure(),
            httpOnly: true,
            sameSite: 'lax',
        ));

        return $cart;
    }

    /**
     * Adds to the line for the product, creating it if needed.
     *
     * @throws ValidationException when the product cannot be bought or the line would pass the cap
     */
    public function add(Cart $cart, Product $product, int $quantity): CartItem
    {
        return DB::transaction(function () use ($cart, $product, $quantity): CartItem {
            $this->lock($cart->getKey());

            $this->assertPurchasable($product);

            $item = $cart->items()->firstOrNew(['product_id' => $product->getKey()]);
            $newQuantity = ($item->exists ? $item->quantity : 0) + $quantity;

            $this->assertWithinLimit($newQuantity);

            $item->quantity = $newQuantity;
            $item->save();

            return $item;
        });
    }

    /**
     * Sets the line's quantity to exactly $quantity.
     *
     * @throws ValidationException when the product can no longer be bought or the quantity is out of range
     */
    public function setQuantity(CartItem $item, int $quantity): CartItem
    {
        return DB::transaction(function () use ($item, $quantity): CartItem {
            $this->lock($item->cart_id);

            $item->refresh()->load('product');

            $this->assertPurchasable($item->product);
            $this->assertWithinLimit($quantity);

            $item->update(['quantity' => $quantity]);

            return $item;
        });
    }

    public function remove(CartItem $item): void
    {
        DB::transaction(function () use ($item): void {
            $this->lock($item->cart_id);

            $item->delete();
        });
    }

    /**
     * Empties the cart. The cart itself stays, so the visitor's cookie keeps
     * pointing at it.
     */
    public function clear(Cart $cart): void
    {
        DB::transaction(function () use ($cart): void {
            $this->lock($cart->getKey());

            $cart->items()->delete();
            $cart->touch();
        });
    }

    private function lock(int $cartId): void
    {
        Cart::query()->whereKey($cartId)->lockForUpdate()->firstOrFail();
    }

    private function assertPurchasable(Product $product): void
    {
        if (! $product->isPurchasable()) {
            throw ValidationException::withMessages([
                'product_id' => __('shop.product_unavailable'),
            ]);
        }
    }

    private function assertWithinLimit(int $quantity): void
    {
        if ($quantity < CartItem::MIN_QUANTITY || $quantity > CartItem::MAX_QUANTITY) {
            throw ValidationException::withMessages([
                'quantity' => __('shop.max_quantity', ['max' => CartItem::MAX_QUANTITY]),
            ]);
        }
    }
}

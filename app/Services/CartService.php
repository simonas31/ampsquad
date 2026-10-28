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
 * (see Cart::totalInclVatCents()).
 */
class CartService
{
    public const string COOKIE = 'cart_token';

    private const int COOKIE_LIFETIME_MINUTES = Cart::LIFETIME_DAYS * 24 * 60;

    /**
     * The visitor's cart, or null when they have none yet (or the cookie
     * is missing, malformed, or points at a cart that no longer exists).
     */
    public function current(Request $request): ?Cart
    {
        $token = $this->tokenFrom($request);

        return $token === null ? null : Cart::query()->where('token', $token)->first();
    }

    /**
     * The visitor's cart ready to render: lines in the order they were
     * added, with their products and images loaded. A visitor with no cart
     * gets an empty, unsaved one, so the page never special-cases it.
     */
    public function forDisplay(Request $request): Cart
    {
        $cart = $this->current($request);

        if ($cart === null) {
            return new Cart;
        }

        return $cart->load(['items' => fn ($items) => $items->orderBy('id'), 'items.product.media']);
    }

    /**
     * Units in the visitor's cart that can be bought (the header badge), in a
     * single query. Matches Cart::totalQuantity(): a line counts its quantity
     * or what is left in stock, whichever is less.
     */
    public function totalQuantity(Request $request): int
    {
        $token = $this->tokenFrom($request);

        if ($token === null) {
            return 0;
        }

        return (int) CartItem::query()
            ->join('carts', 'carts.id', '=', 'cart_items.cart_id')
            ->join('products', 'products.id', '=', 'cart_items.product_id')
            ->where('carts.token', $token)
            ->where('products.is_active', true)
            ->whereNull('products.deleted_at')
            ->where('products.stock_quantity', '>', 0)
            ->sum(DB::raw('least(cart_items.quantity, products.stock_quantity)'));
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
     * @throws ValidationException when the product cannot be bought, the cart is full, or the line would pass the cap
     */
    public function add(Cart $cart, Product $product, int $quantity): CartItem
    {
        return DB::transaction(function () use ($cart, $product, $quantity): CartItem {
            $this->lock($cart->getKey());

            $this->assertPurchasable($product);

            $item = $cart->items()->firstOrNew(['product_id' => $product->getKey()]);

            if (! $item->exists && $cart->items()->count() >= Cart::MAX_LINES) {
                throw ValidationException::withMessages([
                    'product_id' => __('shop.cart_full', ['max' => Cart::MAX_LINES]),
                ]);
            }

            // Builds on what the line is worth, so a line left holding more
            // than the stock is corrected by the next add instead of failing
            // on a quantity the visitor never saw.
            $held = $item->exists ? min($item->quantity, $product->maxPurchasableQuantity()) : 0;
            $newQuantity = $held + $quantity;

            $this->assertWithinLimit($newQuantity, $product, $held);

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
            $this->assertWithinLimit($quantity, $item->product);

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

    /**
     * The cookie's value when it looks like a cart token, otherwise null, so
     * junk never reaches a query.
     */
    private function tokenFrom(Request $request): ?string
    {
        $token = $request->cookie(self::COOKIE);

        return is_string($token) && Str::isUuid($token) ? $token : null;
    }

    /**
     * Holds the cart's row until the surrounding transaction ends, so nothing
     * else changes the cart (or checks it out) meanwhile.
     */
    public function lock(int $cartId): void
    {
        Cart::query()->whereKey($cartId)->lockForUpdate()->firstOrFail();
    }

    private function assertPurchasable(Product $product): void
    {
        if ($product->isPurchasable()) {
            return;
        }

        $isOnSale = $product->is_active && ! $product->trashed();

        throw ValidationException::withMessages([
            'product_id' => $isOnSale ? __('shop.out_of_stock') : __('shop.product_unavailable'),
        ]);
    }

    /**
     * The cart cap, or what is left in stock when that is less. $held is
     * what the cart already holds of the product when adding to it, so the
     * message can say "you already have it all" instead of a bare stock
     * count that looks like the visitor was refused for nothing.
     */
    private function assertWithinLimit(int $quantity, Product $product, int $held = 0): void
    {
        $max = $product->maxPurchasableQuantity();

        if ($quantity >= CartItem::MIN_QUANTITY && $quantity <= $max) {
            return;
        }

        throw ValidationException::withMessages(['quantity' => match (true) {
            $max >= CartItem::MAX_QUANTITY => __('shop.max_quantity', ['max' => CartItem::MAX_QUANTITY]),
            $held >= $max => __('shop.all_in_cart', ['count' => $max]),
            $held > 0 => __('shop.not_enough_stock_with_cart', ['count' => $max, 'held' => $held]),
            default => __('shop.not_enough_stock', ['count' => $max]),
        }]);
    }
}

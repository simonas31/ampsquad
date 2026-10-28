<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ShippingMethod;
use Illuminate\Support\Collection;

/**
 * A signature of what a visitor was shown at checkout: every line's product,
 * quantity and prices, the VAT rate, and the delivery method with its price.
 * The checkout page hands it out, the form sends it back, and checkout works
 * it out again from the database: if the two differ, something changed
 * since the visitor looked, and checkout refuses instead of charging
 * something else.
 *
 * It is keyed with the app key, so it can only come from the server. It is
 * never a price: it is compared and thrown away.
 */
final class CartFingerprint
{
    /**
     * @param  Collection<int, CartItem>  $items  With their products loaded.
     */
    public static function for(Collection $items, ShippingMethod $method): string
    {
        $lines = $items
            ->sortBy('product_id')
            ->map(fn (CartItem $item): string => implode(':', [
                $item->product_id,
                $item->quantity,
                $item->product->price_excl_vat_cents,
                $item->product->priceInclVatCents(),
            ]))
            ->implode('|');

        $shipping = implode(':', [$method->getKey(), $method->price_excl_vat_cents, $method->priceInclVatCents()]);

        return hash_hmac('sha256', implode('#', [$lines, 'vat'.Product::vatRatePercent(), 'shipping'.$shipping]), (string) config('app.key'));
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why checkout would not go ahead. Checkout never adjusts what is charged:
 * when what the visitor was shown no longer holds, it stops and says so.
 */
enum CheckoutRefusal: string
{
    /** There is nothing in the cart (or it was already ordered). */
    case EmptyCart = 'empty_cart';

    /** A line holds more than is in stock, or its product can no longer be bought. */
    case LinesUnavailable = 'lines_unavailable';

    /** A price, a quantity or the delivery price is not what the visitor was shown. */
    case StaleCart = 'stale_cart';

    /** The last units went to someone else while this order was being placed. */
    case StockShort = 'stock_short';

    /** The delivery method does not exist or was switched off. */
    case ShippingUnavailable = 'shipping_unavailable';

    /** The payment provider could not start the payment. */
    case PaymentUnavailable = 'payment_unavailable';

    public function message(): string
    {
        return __('shop.checkout.refused.'.$this->value);
    }

    /**
     * Whether the visitor has to look at their cart again; the other two can
     * be fixed on the checkout form.
     */
    public function sendsBackToTheCart(): bool
    {
        return match ($this) {
            self::EmptyCart, self::LinesUnavailable, self::StaleCart, self::StockShort => true,
            self::ShippingUnavailable, self::PaymentUnavailable => false,
        };
    }
}

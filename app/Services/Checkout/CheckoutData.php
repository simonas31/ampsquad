<?php

declare(strict_types=1);

namespace App\Services\Checkout;

/**
 * What the visitor filled in at checkout, once validated. Deliberately no
 * prices, totals, statuses or ids of anything but the delivery method they
 * picked: the rest comes from the cart and the database.
 *
 * The customer is the one exception that is not typed: it is the verified
 * account the visitor is signed in to, found by the server, or null for a
 * guest. It is never read from the form.
 */
final readonly class CheckoutData
{
    public function __construct(
        public string $customerName,
        public string $customerEmail,
        public string $customerPhone,
        public int $shippingMethodId,
        public ?string $deliveryAddress,
        public ?string $customerNote,
        public string $fingerprint,
        public string $locale,
        public ?int $customerId = null,
    ) {}
}

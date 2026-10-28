<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * A payment the provider has started: its id for it, and where the customer
 * pays.
 */
final readonly class PaymentInitiation
{
    public function __construct(
        public string $providerReference,
        public string $redirectUrl,
    ) {}
}

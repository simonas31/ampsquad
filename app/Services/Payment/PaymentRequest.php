<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * What a provider needs to start a payment for an order.
 */
final readonly class PaymentRequest
{
    public function __construct(
        public string $orderNumber,
        public int $amountCents,
        public string $currency,
        public string $customerEmail,
        public string $locale,
        public string $returnUrl,
        public string $callbackUrl,
        public int $windowMinutes,
    ) {}
}

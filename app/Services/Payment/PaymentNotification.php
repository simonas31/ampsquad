<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\PaymentStatus;

/**
 * What a provider says about a payment, in the shop's words. A gateway turns
 * the provider's own message into this after checking it is genuine; from
 * here on nothing knows which provider it came from.
 */
final readonly class PaymentNotification
{
    /**
     * @param  string  $eventKey  Same for the same message and for the same fact learnt another way (a repeat, or a look-up), different for a change.
     * @param  bool  $windowClosed  The provider will take no more money for this payment.
     * @param  ?int  $requestedAmountCents  The amount we asked for, as the provider remembers it.
     * @param  ?int  $paidAmountCents  What the customer actually paid. Only for a payment that succeeded.
     * @param  array<string, string>  $payload  Only what the shop needs to keep: never the customer's account, name or card.
     */
    public function __construct(
        public string $provider,
        public string $eventKey,
        public string $orderNumber,
        public ?string $providerReference,
        public PaymentStatus $status,
        public string $providerStatus,
        public bool $windowClosed = false,
        public ?int $requestedAmountCents = null,
        public ?int $paidAmountCents = null,
        public ?string $currency = null,
        public ?string $paymentToken = null,
        public ?string $channel = null,
        public ?string $bank = null,
        public array $payload = [],
    ) {}
}

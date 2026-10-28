<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * A payment provider that does nothing, for tests and for working on the
 * shop locally. It starts payments without leaving the site (the customer is
 * sent straight to the return page) and accepts messages signed with the
 * app key (see signedMessage()), which is how a test or a developer plays the
 * provider. It refuses to exist in production.
 */
class FakeGateway implements PaymentGateway
{
    public const string NAME = 'fake';

    /**
     * The last payment it was asked to start, for tests to look at.
     */
    public ?PaymentRequest $lastRequest = null;

    private bool $failNextInitiation = false;

    /**
     * @var array<string, PaymentNotification>
     */
    private array $lookUps = [];

    public function name(): string
    {
        return self::NAME;
    }

    public function initiate(PaymentRequest $request): PaymentInitiation
    {
        $this->lastRequest = $request;

        if ($this->failNextInitiation) {
            $this->failNextInitiation = false;

            throw new PaymentGatewayException('The fake gateway was told to fail.');
        }

        return new PaymentInitiation('FAKE'.Str::upper(Str::random(8)), $request->returnUrl);
    }

    public function parseNotification(Request $request): PaymentNotification
    {
        $message = $request->post();
        $signature = $message['signature'] ?? null;
        unset($message['signature']);

        if (! is_string($signature) || ! hash_equals(self::sign($message), $signature)) {
            throw new InvalidPaymentNotification('The signature does not match.');
        }

        $status = match ($message['status'] ?? null) {
            'succeeded' => PaymentStatus::Succeeded,
            'pending' => PaymentStatus::Pending,
            'failed', 'window_closed' => PaymentStatus::Failed,
            default => throw new InvalidPaymentNotification('Unknown status.'),
        };

        $reference = (string) ($message['transaction_id'] ?? '');
        $token = $message['payment_token'] ?? null;

        return new PaymentNotification(
            provider: self::NAME,
            eventKey: hash('sha256', implode('|', [$reference, $message['status'], $token ?? ''])),
            orderNumber: (string) ($message['order_number'] ?? ''),
            providerReference: $reference,
            status: $status,
            providerStatus: $message['status'],
            windowClosed: $message['status'] === 'window_closed',
            requestedAmountCents: isset($message['amount']) ? (int) $message['amount'] : null,
            paidAmountCents: isset($message['paid_amount']) ? (int) $message['paid_amount'] : null,
            currency: $message['currency'] ?? null,
            paymentToken: $token,
            channel: $message['channel'] ?? null,
            bank: $message['bank'] ?? null,
            payload: array_map('strval', $message),
        );
    }

    public function acknowledgement(): Response
    {
        return new Response('OK', 200, ['Content-Type' => 'text/plain']);
    }

    public function lookUp(Payment $payment): ?PaymentNotification
    {
        return $this->lookUps[$payment->provider_reference] ?? null;
    }

    /**
     * Makes the next payment fail to start.
     */
    public function failNextInitiation(): void
    {
        $this->failNextInitiation = true;
    }

    /**
     * Makes lookUp() answer for a payment, as a provider would that knows
     * something its message never delivered.
     */
    public function reportOnLookUp(string $providerReference, PaymentNotification $notification): void
    {
        $this->lookUps[$providerReference] = $notification;
    }

    /**
     * A message as the provider would send it: the fields, signed. Fields
     * used: status (succeeded, pending, failed, window_closed), order_number,
     * transaction_id, amount, paid_amount, currency, payment_token, channel, bank.
     *
     * @param  array<string, string|int>  $fields
     * @return array<string, string>
     */
    public static function signedMessage(array $fields): array
    {
        $message = array_map('strval', $fields);

        return [...$message, 'signature' => self::sign($message)];
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private static function sign(array $message): string
    {
        ksort($message);

        return hash_hmac('sha256', http_build_query($message), (string) config('app.key'));
    }
}

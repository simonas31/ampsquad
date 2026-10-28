<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OPAY (https://opay.dev/docs/v1), with the customer sent to OPAY's hosted
 * payment page and told of the result by a signed message to our callback.
 *
 * Every request and message is signed with the password OPAY gives the shop
 * ("symmetric" signing): the signature is the md5 of every parameter's name
 * and value, one after the other in the order they are sent, followed by the
 * password. Nothing in a message is believed before that signature checks out.
 * Messages signed with a private key (rsa_signature) are not supported.
 */
class OpayGateway implements PaymentGateway
{
    public const string NAME = 'opay';

    private const string STANDARD = 'opay_8.1';

    /**
     * The shop's languages as OPAY names them.
     */
    private const array LANGUAGES = ['lt' => 'LIT', 'en' => 'ENG'];

    /**
     * What the customer's bank statement says. {order_nr} and {website} are
     * OPAY's own placeholders.
     */
    private const array DESCRIPTIONS = [
        'lt' => 'Užsakymas {order_nr} {website}',
        'en' => 'Order {order_nr} {website}',
    ];

    /**
     * Fields of a message that are kept. The rest (the customer's name,
     * account number, email, phone, card) is personal and not needed.
     */
    private const array KEPT_FIELDS = [
        'status', 'transaction_id', 'order_nr', 'website_id', 'standard', 'language',
        'amount', 'currency', 'p_token', 'p_amount', 'p_currency', 'p_channel', 'p_bank', 'p_gmt_date_time',
    ];

    /**
     * @param  array<string, mixed>  $config  config('services.opay')
     */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function initiate(PaymentRequest $request): PaymentInitiation
    {
        $parameters = $this->signed(array_filter([
            'order_nr' => $request->orderNumber,
            'website_id' => $this->websiteId(),
            'redirect_url' => $request->returnUrl,
            'redirect_on_success' => 1,
            'web_service_url' => $request->callbackUrl,
            'standard' => self::STANDARD,
            'language' => self::LANGUAGES[$request->locale] ?? 'ENG',
            'amount' => $request->amountCents,
            'currency' => $request->currency,
            'show_channels' => $this->showChannels(),
            'payment_description' => self::DESCRIPTIONS[$request->locale] ?? self::DESCRIPTIONS['en'],
            'time_limit' => $request->windowMinutes,
            'test' => $this->config['test_user_id'] ?? null,
            'c_email' => $request->customerEmail,
        ], fn (mixed $value): bool => $value !== null && $value !== ''));

        $response = $this->post('/api/createtransaction/', $parameters);
        $result = $response->json('response.result');

        if ($response->successful() && is_array($result) && filled($result['transaction_id'] ?? null) && filled($result['redirect_url'] ?? null)) {
            return new PaymentInitiation((string) $result['transaction_id'], (string) $result['redirect_url']);
        }

        $codes = collect($response->json('response.errors') ?? [])->pluck('code')->filter()->implode(', ');

        Log::error('OPAY did not start the payment.', ['order' => $request->orderNumber, 'http_status' => $response->status(), 'errors' => $codes]);

        throw new PaymentGatewayException("OPAY refused to start the payment (HTTP {$response->status()}: {$codes}).");
    }

    public function parseNotification(Request $request): PaymentNotification
    {
        $encoded = $request->input('encoded');

        if (! is_string($encoded) || $encoded === '') {
            throw new InvalidPaymentNotification('There is no encoded message.');
        }

        $decoded = base64_decode(strtr($encoded, ['-' => '+', '_' => '/', ',' => '=']), true);

        if ($decoded === false) {
            throw new InvalidPaymentNotification('The message is not valid base64.');
        }

        parse_str($decoded, $message);

        foreach ($message as $value) {
            if (! is_string($value)) {
                throw new InvalidPaymentNotification('A field of the message is not plain text.');
            }
        }

        $this->assertSigned($message);

        if (($message['website_id'] ?? null) !== $this->websiteId()) {
            throw new InvalidPaymentNotification('The message is for another website.');
        }

        foreach (['status', 'order_nr', 'transaction_id'] as $required) {
            if (! filled($message[$required] ?? null)) {
                throw new InvalidPaymentNotification("The message has no {$required}.");
            }
        }

        [$status, $windowClosed] = $this->statusFor($message['status']);

        return new PaymentNotification(
            provider: self::NAME,
            eventKey: $this->eventKey($message['transaction_id'], $message['status'], $message['p_token'] ?? null),
            orderNumber: $message['order_nr'],
            providerReference: $message['transaction_id'],
            status: $status,
            providerStatus: $message['status'],
            windowClosed: $windowClosed,
            requestedAmountCents: $this->cents($message['amount'] ?? null),
            paidAmountCents: $this->cents($message['p_amount'] ?? null),
            currency: $message['p_currency'] ?? $message['currency'] ?? null,
            paymentToken: $message['p_token'] ?? null,
            channel: $message['p_channel'] ?? null,
            bank: $message['p_bank'] ?? null,
            payload: array_intersect_key($message, array_flip(self::KEPT_FIELDS)),
        );
    }

    /**
     * OPAY takes exactly the text OK as "received"; anything else and it
     * sends the message again, up to four more times.
     */
    public function acknowledgement(): Response
    {
        return new Response('OK', 200, ['Content-Type' => 'text/plain']);
    }

    public function lookUp(Payment $payment): ?PaymentNotification
    {
        if ($payment->provider_reference === null) {
            return null;
        }

        $parameters = $this->signed([
            'language' => 'ENG',
            'service_name' => 'getPaymentById',
            'website_id' => $this->websiteId(),
        ]);

        try {
            $response = $this->post('/api/transactions/'.rawurlencode($payment->provider_reference), $parameters);
        } catch (PaymentGatewayException $exception) {
            Log::warning('OPAY could not be asked about a payment.', ['payment' => $payment->getKey(), 'reason' => $exception->getMessage()]);

            return null;
        }

        $result = $response->json('response.result');

        if (! $response->successful() || ! is_array($result) || ! isset($result['status'])) {
            Log::warning('OPAY did not say what became of a payment.', ['payment' => $payment->getKey(), 'http_status' => $response->status()]);

            return null;
        }

        return $this->notificationFromLookUp($payment, $result);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function notificationFromLookUp(Payment $payment, array $result): ?PaymentNotification
    {
        $code = (string) $result['status'];

        try {
            [$status, $windowClosed] = $this->statusFor($code);
        } catch (InvalidPaymentNotification) {
            return null;
        }

        $paid = null;

        if ($status === PaymentStatus::Succeeded) {
            $paid = collect($result['payments'] ?? [])->last(fn (mixed $each): bool => is_array($each) && filled($each['p_token'] ?? null));

            if ($paid === null) {
                return null;
            }
        }

        $token = $paid['p_token'] ?? null;
        $reference = (string) ($result['transaction_id'] ?? $payment->provider_reference);
        $payment->loadMissing('order');

        return new PaymentNotification(
            provider: self::NAME,
            eventKey: $this->eventKey($reference, $code, $token),
            orderNumber: $payment->order->number,
            providerReference: $reference,
            status: $status,
            providerStatus: $code,
            windowClosed: $windowClosed,
            requestedAmountCents: $this->cents($result['amount'] ?? null),
            paidAmountCents: $this->cents($paid['p_amount'] ?? null),
            currency: $paid['p_currency'] ?? $result['currency'] ?? null,
            paymentToken: $token,
            channel: $paid['p_channel'] ?? null,
            bank: $paid['p_bank'] ?? null,
            payload: array_intersect_key(
                array_map('strval', [...array_filter($result, 'is_scalar'), ...(array) $paid]),
                array_flip(self::KEPT_FIELDS),
            ),
        );
    }

    /**
     * OPAY's codes: 1 paid, 2 created, 5 not finished (can still be paid),
     * 3 cancelled, 0 time limit exceeded (no more money will be taken).
     *
     * @return array{PaymentStatus, bool}
     */
    private function statusFor(string $code): array
    {
        return match ($code) {
            '1' => [PaymentStatus::Succeeded, false],
            '2', '5' => [PaymentStatus::Pending, false],
            '3' => [PaymentStatus::Failed, false],
            '0' => [PaymentStatus::Failed, true],
            default => throw new InvalidPaymentNotification("Unknown status {$code}."),
        };
    }

    /**
     * The same for a message and for the same fact found by asking, so
     * whichever arrives second is recognised as a repeat.
     */
    private function eventKey(string $transactionId, string $status, ?string $paymentToken): string
    {
        return hash('sha256', implode('|', [self::NAME, $transactionId, $status, $paymentToken ?? '']));
    }

    private function cents(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value) || ! ctype_digit($value)) {
            throw new InvalidPaymentNotification('An amount is not a whole number of cents.');
        }

        return (int) $value;
    }

    /**
     * @param  array<string, string>  $message
     */
    private function assertSigned(array $message): void
    {
        $signature = $message['password_signature'] ?? null;

        if (! is_string($signature)) {
            throw new InvalidPaymentNotification(isset($message['rsa_signature'])
                ? 'Messages signed with a key are not supported.'
                : 'The message is not signed.');
        }

        $password = (string) ($this->config['signature_password'] ?? '');

        if ($password === '') {
            throw new InvalidPaymentNotification('The signature password is not set.');
        }

        if (! hash_equals(md5($this->signingLine($message).$password), strtolower($signature))) {
            throw new InvalidPaymentNotification('The signature does not match.');
        }
    }

    /**
     * Every parameter's name and value, joined without separators, in the
     * order given and without the signatures themselves.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function signingLine(array $parameters): string
    {
        $line = '';

        foreach ($parameters as $name => $value) {
            if ($name !== 'password_signature' && $name !== 'rsa_signature') {
                $line .= $name.$value;
            }
        }

        return $line;
    }

    /**
     * The parameters with their signature added at the end.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private function signed(array $parameters): array
    {
        $password = (string) trim($this->config['signature_password'] ?? '');

        if ($password === '') {
            throw new PaymentGatewayException('OPAY is not set up: OPAY_SIGNATURE_PASSWORD is empty.');
        }

        return [...$parameters, 'password_signature' => md5($this->signingLine($parameters).$password)];
    }

    private function websiteId(): string
    {
        $id = (string) ($this->config['website_id'] ?? '');

        if ($id === '') {
            throw new PaymentGatewayException('OPAY is not set up: OPAY_WEBSITE_ID is empty.');
        }

        return $id;
    }

    private function showChannels(): string
    {
        return collect(explode(',', (string) ($this->config['show_channels'] ?? '')))
            ->map(fn (string $channel): string => trim($channel))
            ->filter()
            ->implode(', ');
    }

    /**
     * @param  array<string, mixed>  $parameters
     *
     * @throws PaymentGatewayException when OPAY cannot be reached
     */
    private function post(string $path, array $parameters): HttpResponse
    {
        try {
            return Http::asForm()
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(max(1, (int) ($this->config['timeout'] ?? 8)))
                ->post(rtrim((string) ($this->config['base_url'] ?? ''), '/').$path, $parameters);
        } catch (ConnectionException $exception) {
            throw new PaymentGatewayException('OPAY could not be reached: '.$exception->getMessage(), previous: $exception);
        }
    }
}

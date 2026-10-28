<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\InvalidPaymentNotification;
use App\Services\Payment\OpayGateway;
use App\Services\Payment\PaymentGatewayException;
use App\Services\Payment\PaymentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The OPAY adapter against OPAY's documented rules (https://opay.dev/docs/v1):
 * signed form requests out, a signed base64 "encoded" message in. OPAY itself
 * is never called; its answers are faked.
 */
class OpayGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const string PASSWORD = '63bdbdbb0a88ef93fa627d5f71fb216e';

    private const string WEBSITE = '8W23U89523';

    private const string CREATE_URL = 'https://sandbox-api.opay.eu/api/createtransaction/';

    /**
     * @param  array<string, mixed>  $config
     */
    private function gateway(array $config = []): OpayGateway
    {
        return new OpayGateway([
            'website_id' => self::WEBSITE,
            'signature_password' => self::PASSWORD,
            'base_url' => 'https://sandbox-api.opay.eu',
            'test_user_id' => null,
            'show_channels' => 'pis,card, banklink_swedbank',
            'timeout' => 8,
            ...$config,
        ]);
    }

    private function paymentRequest(string $locale = 'lt'): PaymentRequest
    {
        return new PaymentRequest(
            orderNumber: 'AS-7K3M9QXD',
            amountCents: 3570,
            currency: 'EUR',
            customerEmail: 'jonas@example.test',
            locale: $locale,
            returnUrl: 'https://ampsquad.lt/orders/abc',
            callbackUrl: 'https://ampsquad.lt/payments/opay/callback',
            windowMinutes: 15,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function created(): array
    {
        return ['response' => ['result' => ['transaction_id' => '8T22R8NMVK', 'redirect_url' => 'https://pay.opay.eu/8T22R8NMVK?locale=lt'], 'errors' => [], 'language' => 'LIT']];
    }

    /**
     * What OPAY signs: every parameter's name and value, in order, then the password, md5.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function signature(array $parameters, string $password = self::PASSWORD): string
    {
        $line = '';

        foreach ($parameters as $name => $value) {
            $line .= $name.$value;
        }

        return md5($line.$password);
    }

    /**
     * A message to the callback the way OPAY builds it: the parameters and
     * their signature, urlencoded, base64, with + / = swapped for - _ ,
     *
     * @param  array<string, string>  $fields
     */
    private function encoded(array $fields, string $password = self::PASSWORD): string
    {
        $fields['password_signature'] = $this->signature($fields, $password);

        return strtr(base64_encode(http_build_query($fields)), ['+' => '-', '/' => '_', '=' => ',']);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function paidMessage(array $overrides = []): array
    {
        return [
            'status' => '1',
            'website_id' => self::WEBSITE,
            'transaction_id' => '8T22R8NMVK',
            'order_nr' => 'AS-7K3M9QXD',
            'standard' => 'opay_8.1',
            'language' => 'LIT',
            'amount' => '3570',
            'currency' => 'EUR',
            'p_token' => '655f00251ad9a0286964',
            'p_amount' => '3570',
            'p_currency' => 'EUR',
            'p_channel' => 'banklink',
            'p_bank' => 'swedbank',
            'p_gmt_date_time' => '2026-10-28 13:14:24',
            'c_full_name' => 'Jonas Jonaitis',
            'c_account_nr' => 'LT003500000000000000',
            'c_email' => 'jonas@example.test',
            'c_mobile_nr' => '+37060000000',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function callbackRequest(array $fields, ?string $password = null): Request
    {
        return Request::create('/payments/opay/callback', 'POST', ['encoded' => $this->encoded($fields, $password ?? self::PASSWORD)]);
    }

    /**
     * @return array<string, string>
     */
    private function sentParameters(ClientRequest $request): array
    {
        parse_str($request->body(), $parameters);

        return $parameters;
    }

    public function test_it_starts_a_payment_with_a_signed_form_in_the_documented_shape(): void
    {
        Http::fake([self::CREATE_URL => Http::response($this->created())]);

        $initiation = $this->gateway()->initiate($this->paymentRequest());

        $this->assertSame('8T22R8NMVK', $initiation->providerReference);
        $this->assertSame('https://pay.opay.eu/8T22R8NMVK?locale=lt', $initiation->redirectUrl);

        Http::assertSent(function (ClientRequest $request): bool {
            $parameters = $this->sentParameters($request);
            $signature = array_pop($parameters);

            $this->assertSame('POST', $request->method());
            $this->assertSame(self::CREATE_URL, $request->url());
            $this->assertStringContainsString('application/x-www-form-urlencoded', $request->header('Content-Type')[0]);
            $this->assertSame([
                'order_nr' => 'AS-7K3M9QXD',
                'website_id' => self::WEBSITE,
                'redirect_url' => 'https://ampsquad.lt/orders/abc',
                'redirect_on_success' => '1',
                'web_service_url' => 'https://ampsquad.lt/payments/opay/callback',
                'standard' => 'opay_8.1',
                'language' => 'LIT',
                'amount' => '3570',
                'currency' => 'EUR',
                'show_channels' => 'pis, card, banklink_swedbank',
                'payment_description' => 'Užsakymas {order_nr} {website}',
                'time_limit' => '15',
                'c_email' => 'jonas@example.test',
            ], $parameters);
            $this->assertSame($this->signature($parameters), $signature);
            $this->assertSame('password_signature', array_key_last($this->sentParameters($request)));

            return true;
        });
    }

    public function test_the_password_is_never_sent(): void
    {
        Http::fake([self::CREATE_URL => Http::response($this->created())]);

        $this->gateway()->initiate($this->paymentRequest());

        Http::assertSent(fn (ClientRequest $request): bool => ! str_contains($request->body(), self::PASSWORD));
    }

    public function test_english_orders_are_started_in_english(): void
    {
        Http::fake([self::CREATE_URL => Http::response($this->created())]);

        $this->gateway()->initiate($this->paymentRequest('en'));

        Http::assertSent(function (ClientRequest $request): bool {
            $parameters = $this->sentParameters($request);

            return $parameters['language'] === 'ENG' && $parameters['payment_description'] === 'Order {order_nr} {website}';
        });
    }

    public function test_a_test_user_makes_it_a_test_payment_and_none_leaves_it_out(): void
    {
        Http::fake([self::CREATE_URL => Http::response($this->created())]);

        $this->gateway(['test_user_id' => '8U22A86U62'])->initiate($this->paymentRequest());
        $this->gateway()->initiate($this->paymentRequest());

        $sent = Http::recorded()->map(fn (array $pair): array => $this->sentParameters($pair[0]));
        $this->assertSame('8U22A86U62', $sent[0]['test']);
        $this->assertArrayNotHasKey('test', $sent[1]);
        $this->assertSame($this->signature(array_slice($sent[0], 0, -1)), $sent[0]['password_signature']);
    }

    public function test_it_gives_up_with_the_providers_error_codes_and_no_secret(): void
    {
        Log::spy();
        Http::fake([self::CREATE_URL => Http::response(['response' => ['result' => false, 'errors' => [['code' => 'API:INVALID_PASSWORD_SIGNATURE', 'message' => 'x']]]], 404)]);

        try {
            $this->gateway()->initiate($this->paymentRequest());
            $this->fail('It should have thrown.');
        } catch (PaymentGatewayException $exception) {
            $this->assertStringContainsString('API:INVALID_PASSWORD_SIGNATURE', $exception->getMessage());
            $this->assertStringNotContainsString(self::PASSWORD, $exception->getMessage());
        }

        Log::shouldHaveReceived('error')->once();
    }

    public function test_an_answer_with_no_transaction_is_a_failure_even_when_it_is_http_200(): void
    {
        Http::fake([self::CREATE_URL => Http::response(['response' => ['result' => [], 'errors' => []]])]);

        $this->expectException(PaymentGatewayException::class);

        $this->gateway()->initiate($this->paymentRequest());
    }

    public function test_an_unreachable_provider_is_a_gateway_failure(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        $this->expectException(PaymentGatewayException::class);
        $this->expectExceptionMessage('could not be reached');

        $this->gateway()->initiate($this->paymentRequest());
    }

    public function test_it_will_not_start_a_payment_it_cannot_sign(): void
    {
        Http::fake();

        foreach ([['signature_password' => ''], ['website_id' => ''], ['signature_password' => null]] as $missing) {
            try {
                $this->gateway($missing)->initiate($this->paymentRequest());
                $this->fail('It should have thrown.');
            } catch (PaymentGatewayException $exception) {
                $this->assertStringContainsString('not set up', $exception->getMessage());
            }
        }

        Http::assertNothingSent();
    }

    public function test_it_reads_a_paid_message_and_keeps_only_what_it_needs(): void
    {
        $notification = $this->gateway()->parseNotification($this->callbackRequest($this->paidMessage()));

        $this->assertSame('opay', $notification->provider);
        $this->assertSame('AS-7K3M9QXD', $notification->orderNumber);
        $this->assertSame('8T22R8NMVK', $notification->providerReference);
        $this->assertSame(PaymentStatus::Succeeded, $notification->status);
        $this->assertSame('1', $notification->providerStatus);
        $this->assertFalse($notification->windowClosed);
        $this->assertSame(3570, $notification->requestedAmountCents);
        $this->assertSame(3570, $notification->paidAmountCents);
        $this->assertSame('EUR', $notification->currency);
        $this->assertSame('655f00251ad9a0286964', $notification->paymentToken);
        $this->assertSame('banklink', $notification->channel);
        $this->assertSame('swedbank', $notification->bank);

        $this->assertSame('1', $notification->payload['status']);
        $this->assertSame('655f00251ad9a0286964', $notification->payload['p_token']);
        foreach (['c_full_name', 'c_account_nr', 'c_email', 'c_mobile_nr', 'password_signature'] as $personal) {
            $this->assertArrayNotHasKey($personal, $notification->payload);
        }
    }

    /**
     * @return array<string, array{string, PaymentStatus, bool}>
     */
    public static function statuses(): array
    {
        return [
            'paid' => ['1', PaymentStatus::Succeeded, false],
            'created' => ['2', PaymentStatus::Pending, false],
            'not finished' => ['5', PaymentStatus::Pending, false],
            'cancelled' => ['3', PaymentStatus::Failed, false],
            'time limit exceeded' => ['0', PaymentStatus::Failed, true],
        ];
    }

    #[DataProvider('statuses')]
    public function test_each_of_opays_statuses_means_what_the_docs_say(string $code, PaymentStatus $status, bool $windowClosed): void
    {
        $fields = $this->paidMessage(['status' => $code]);
        unset($fields['p_token'], $fields['p_amount'], $fields['p_currency']);

        $notification = $this->gateway()->parseNotification($this->callbackRequest($fields));

        $this->assertSame($status, $notification->status);
        $this->assertSame($windowClosed, $notification->windowClosed);
        $this->assertNull($notification->paymentToken);
        $this->assertNull($notification->paidAmountCents);
    }

    public function test_a_status_it_does_not_know_is_refused(): void
    {
        $this->expectException(InvalidPaymentNotification::class);

        $this->gateway()->parseNotification($this->callbackRequest($this->paidMessage(['status' => '9'])));
    }

    public function test_the_base64_variant_with_dashes_underscores_and_commas_is_read(): void
    {
        // Try order numbers until the encoding needs the swapped characters.
        foreach (range(0, 200) as $attempt) {
            $fields = $this->paidMessage(['order_nr' => "AS-ŽĄČ{$attempt}ū?>"]);
            $encoded = $this->encoded($fields);

            if (preg_match('/[-_,]/', $encoded) === 1) {
                break;
            }
        }

        $this->assertMatchesRegularExpression('/[-_,]/', $encoded);
        $this->assertSame($fields['order_nr'], $this->gateway()->parseNotification(Request::create('/', 'POST', ['encoded' => $encoded]))->orderNumber);
    }

    public function test_it_is_read_when_sent_in_the_query_as_well(): void
    {
        $request = Request::create('/payments/opay/callback?'.http_build_query(['encoded' => $this->encoded($this->paidMessage())]), 'POST');

        $this->assertSame('AS-7K3M9QXD', $this->gateway()->parseNotification($request)->orderNumber);
    }

    /**
     * @return array<string, array{callable(self): Request}>
     */
    public static function messagesToRefuse(): array
    {
        return [
            'an amount changed after signing' => [function (self $test): Request {
                $fields = $test->paidMessage();
                $fields['password_signature'] = $test->signature($fields);
                $fields['p_amount'] = '1';

                return Request::create('/', 'POST', ['encoded' => strtr(base64_encode(http_build_query($fields)), ['+' => '-', '/' => '_', '=' => ','])]);
            }],
            'a field added after signing' => [function (self $test): Request {
                $fields = $test->paidMessage();
                $fields['password_signature'] = $test->signature($fields);
                $fields['extra'] = 'x';

                return Request::create('/', 'POST', ['encoded' => strtr(base64_encode(http_build_query($fields)), ['+' => '-', '/' => '_', '=' => ','])]);
            }],
            'the wrong password' => [fn (self $test): Request => $test->callbackRequest($test->paidMessage(), 'another password')],
            'no signature' => [fn (self $test): Request => Request::create('/', 'POST', ['encoded' => strtr(base64_encode(http_build_query($test->paidMessage())), ['+' => '-', '/' => '_', '=' => ','])])],
            'a key signature only' => [fn (self $test): Request => Request::create('/', 'POST', ['encoded' => strtr(base64_encode(http_build_query([...$test->paidMessage(), 'rsa_signature' => 'AAAA'])), ['+' => '-', '/' => '_', '=' => ','])])],
            'another website' => [fn (self $test): Request => $test->callbackRequest($test->paidMessage(['website_id' => 'SOMEONEELSE']))],
            'no order number' => [fn (self $test): Request => $test->callbackRequest(array_diff_key($test->paidMessage(), ['order_nr' => 1]))],
            'no transaction' => [fn (self $test): Request => $test->callbackRequest(array_diff_key($test->paidMessage(), ['transaction_id' => 1]))],
            'no status' => [fn (self $test): Request => $test->callbackRequest(array_diff_key($test->paidMessage(), ['status' => 1]))],
            'an amount that is not cents' => [fn (self $test): Request => $test->callbackRequest($test->paidMessage(['p_amount' => '35.70']))],
            'an amount that is negative' => [fn (self $test): Request => $test->callbackRequest($test->paidMessage(['p_amount' => '-5']))],
            'nothing encoded' => [fn (): Request => Request::create('/', 'POST')],
            'an empty message' => [fn (): Request => Request::create('/', 'POST', ['encoded' => ''])],
            'an array instead of text' => [fn (): Request => Request::create('/', 'POST', ['encoded' => ['a']])],
            'not base64' => [fn (): Request => Request::create('/', 'POST', ['encoded' => '!!!not base64!!!'])],
            'a field that is a list' => [fn (self $test): Request => Request::create('/', 'POST', ['encoded' => strtr(base64_encode('a[]=1&b=2'), ['+' => '-', '/' => '_', '=' => ','])])],
        ];
    }

    /**
     * @param  callable(self): Request  $makeRequest
     */
    #[DataProvider('messagesToRefuse')]
    public function test_a_message_that_is_not_genuine_is_refused(callable $makeRequest): void
    {
        $this->expectException(InvalidPaymentNotification::class);

        $this->gateway()->parseNotification($makeRequest($this));
    }

    public function test_a_message_cannot_be_checked_without_the_password(): void
    {
        $this->expectException(InvalidPaymentNotification::class);
        $this->expectExceptionMessage('password is not set');

        $this->gateway(['signature_password' => ''])->parseNotification($this->callbackRequest($this->paidMessage(), ''));
    }

    public function test_a_repeat_has_the_same_key_and_a_change_has_another(): void
    {
        $gateway = $this->gateway();

        $first = $gateway->parseNotification($this->callbackRequest($this->paidMessage()));
        $repeat = $gateway->parseNotification($this->callbackRequest($this->paidMessage()));
        $pending = $gateway->parseNotification($this->callbackRequest($this->paidMessage(['status' => '5', 'p_token' => ''])));
        $another = $gateway->parseNotification($this->callbackRequest($this->paidMessage(['p_token' => 'another-token'])));

        $this->assertSame($first->eventKey, $repeat->eventKey);
        $this->assertNotSame($first->eventKey, $pending->eventKey);
        $this->assertNotSame($first->eventKey, $another->eventKey);
    }

    public function test_it_answers_the_provider_with_exactly_ok(): void
    {
        $response = $this->gateway()->acknowledgement();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getContent());
    }

    private function paymentWaiting(): Payment
    {
        $order = Order::factory()->create(['number' => 'AS-7K3M9QXD']);

        return Payment::factory()->for($order)->create(['provider' => 'opay', 'provider_reference' => '8T22R8NMVK']);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function lookUpAnswer(array $result): array
    {
        return ['response' => ['result' => ['transaction_id' => '8T22R8NMVK', 'order_nr' => 'AS-7K3M9QXD', 'amount' => '3570', 'currency' => 'EUR', ...$result], 'errors' => []]];
    }

    public function test_it_asks_opay_what_became_of_a_payment_with_a_signed_request(): void
    {
        $payment = $this->paymentWaiting();
        Http::fake(['sandbox-api.opay.eu/api/transactions/8T22R8NMVK' => Http::response($this->lookUpAnswer(['status' => 5, 'payments' => []]))]);

        $this->gateway()->lookUp($payment);

        Http::assertSent(function (ClientRequest $request): bool {
            $parameters = $this->sentParameters($request);
            $signature = array_pop($parameters);

            $this->assertSame('https://sandbox-api.opay.eu/api/transactions/8T22R8NMVK', $request->url());
            $this->assertSame(['language' => 'ENG', 'service_name' => 'getPaymentById', 'website_id' => self::WEBSITE], $parameters);
            $this->assertSame($this->signature($parameters), $signature);

            return true;
        });
    }

    public function test_a_payment_opay_says_was_paid_comes_back_as_a_paid_message_would(): void
    {
        $payment = $this->paymentWaiting();
        Http::fake(['*' => Http::response($this->lookUpAnswer(['status' => 1, 'payments' => [
            ['p_token' => '655f00251ad9a0286964', 'p_amount' => '3570', 'p_currency' => 'EUR', 'p_channel' => 'banklink', 'p_bank' => 'swedbank', 'c_full_name' => 'Jonas'],
        ]]))]);

        $found = $this->gateway()->lookUp($payment);
        $message = $this->gateway()->parseNotification($this->callbackRequest($this->paidMessage()));

        $this->assertSame(PaymentStatus::Succeeded, $found->status);
        $this->assertSame('AS-7K3M9QXD', $found->orderNumber);
        $this->assertSame(3570, $found->paidAmountCents);
        $this->assertSame('655f00251ad9a0286964', $found->paymentToken);
        $this->assertSame('swedbank', $found->bank);
        $this->assertArrayNotHasKey('c_full_name', $found->payload);
        // The same fact however it was learnt, so whichever comes second is a repeat.
        $this->assertSame($message->eventKey, $found->eventKey);
    }

    public function test_other_statuses_found_by_asking_are_mapped_too(): void
    {
        $payment = $this->paymentWaiting();
        $cases = [[5, PaymentStatus::Pending, false], [2, PaymentStatus::Pending, false], [3, PaymentStatus::Failed, false], [0, PaymentStatus::Failed, true]];

        $answers = Http::sequence();
        foreach ($cases as [$code]) {
            $answers->push($this->lookUpAnswer(['status' => $code, 'payments' => []]));
        }
        Http::fake(['*' => $answers]);

        foreach ($cases as [$code, $status, $windowClosed]) {
            $found = $this->gateway()->lookUp($payment);

            $this->assertSame($status, $found->status, "status {$code}");
            $this->assertSame($windowClosed, $found->windowClosed, "status {$code}");
        }
    }

    public function test_it_says_nothing_when_opay_cannot_say(): void
    {
        $payment = $this->paymentWaiting();

        Http::fake(['*' => Http::sequence()
            ->push('', 500)
            ->push(['response' => ['result' => false, 'errors' => [['code' => 'API:UNKNOWN_TRANSACTION_ID']]]], 404)
            ->pushFailedConnection()
            // Paid, but with no payment to point at: not something to act on.
            ->push($this->lookUpAnswer(['status' => 1, 'payments' => []]))
            // A status it does not know.
            ->push($this->lookUpAnswer(['status' => 9]))]);

        foreach (['a server error', 'an unknown transaction', 'no connection', 'paid with no payment', 'an unknown status'] as $case) {
            $this->assertNull($this->gateway()->lookUp($payment), $case);
        }
    }

    public function test_it_does_not_ask_about_a_payment_opay_never_started(): void
    {
        Http::fake();
        $payment = Payment::factory()->create(['provider' => 'opay', 'provider_reference' => null]);

        $this->assertNull($this->gateway()->lookUp($payment));
        Http::assertNothingSent();
    }
}

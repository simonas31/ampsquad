<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Services\Payment\FakeGateway;
use App\Services\Payment\InvalidPaymentNotification;
use App\Services\Payment\OpayGateway;
use App\Services\Payment\PaymentGateway;
use Illuminate\Http\Request;
use RuntimeException;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    public function test_the_gateway_follows_the_config(): void
    {
        config(['shop.payment_gateway' => 'opay']);
        $this->assertInstanceOf(OpayGateway::class, $this->app->make(PaymentGateway::class));

        $this->app->forgetInstance(PaymentGateway::class);
        config(['shop.payment_gateway' => 'fake']);
        $this->assertInstanceOf(FakeGateway::class, $this->app->make(PaymentGateway::class));
    }

    public function test_an_unknown_gateway_is_an_error_not_a_default(): void
    {
        config(['shop.payment_gateway' => 'paypal']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown payment gateway: paypal');

        $this->app->make(PaymentGateway::class);
    }

    public function test_the_fake_gateway_refuses_to_run_in_production(): void
    {
        config(['shop.payment_gateway' => 'fake']);
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot run in production');

        $this->app->make(PaymentGateway::class);
    }

    public function test_the_opay_gateway_is_built_from_the_services_config_without_secrets_in_code(): void
    {
        config(['shop.payment_gateway' => 'opay', 'services.opay.website_id' => 'SITE123456', 'services.opay.signature_password' => 'secret']);

        $this->assertSame('opay', $this->app->make(PaymentGateway::class)->name());
        $this->assertEmpty(config('services.opay.test_user_id'));
        $this->assertStringContainsString('sandbox', config('services.opay.base_url'));
    }

    public function test_the_fake_gateway_accepts_only_signed_known_messages(): void
    {
        $gateway = new FakeGateway;
        $message = FakeGateway::signedMessage(['status' => 'succeeded', 'order_number' => 'AS-X', 'transaction_id' => 'T1', 'amount' => 100, 'paid_amount' => 100, 'currency' => 'EUR', 'payment_token' => 'p1']);

        $notification = $gateway->parseNotification(Request::create('/', 'POST', $message));

        $this->assertSame(PaymentStatus::Succeeded, $notification->status);
        $this->assertSame(100, $notification->paidAmountCents);
        $this->assertSame('fake', $gateway->name());
        $this->assertSame('OK', $gateway->acknowledgement()->getContent());

        $this->expectException(InvalidPaymentNotification::class);

        $gateway->parseNotification(Request::create('/', 'POST', [...$message, 'paid_amount' => '1']));
    }

    public function test_the_fake_gateway_refuses_a_status_it_does_not_know(): void
    {
        $message = FakeGateway::signedMessage(['status' => 'refunded', 'order_number' => 'AS-X', 'transaction_id' => 'T1']);

        $this->expectException(InvalidPaymentNotification::class);

        (new FakeGateway)->parseNotification(Request::create('/', 'POST', $message));
    }
}

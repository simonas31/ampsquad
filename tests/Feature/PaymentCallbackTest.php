<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentOutcome;
use App\Enums\PaymentStatus;
use App\Events\OrderPaid;
use App\Events\PaymentNeedsAPerson;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Product;
use App\Services\Payment\FakeGateway;
use App\Services\Payment\PaymentGateway;
use App\Settings\ShopSettings;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use ReflectionMethod;
use Tests\Concerns\CheckoutScenario;
use Tests\TestCase;

/**
 * What the payment provider's server tells us is the only thing that makes
 * an order paid, and it arrives repeated, late and out of order.
 */
class PaymentCallbackTest extends TestCase
{
    use CheckoutScenario;
    use RefreshDatabase;

    private Product $router;

    private Product $cable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->switchCheckoutOn();

        $this->router = Product::factory()->priced(1000)->inStock(10)->create();
        $this->cable = Product::factory()->priced(500)->inStock(5)->create();
    }

    /**
     * An order as checkout leaves it: awaiting payment, its stock taken, its
     * payment started.
     */
    private function placedOrder(): Order
    {
        $order = Order::factory()->withLine($this->router, 2)->withLine($this->cable, 1)->create();
        Product::takeStock($this->router->id, 2);
        Product::takeStock($this->cable->id, 1);
        Payment::factory()->for($order)->create(['provider_reference' => 'FAKE0001']);

        return $order->refresh();
    }

    /**
     * @param  array<string, string>  $message
     */
    private function providerCalls(array $message): TestResponse
    {
        return $this->post(route('payments.callback', ['provider' => 'fake']), $message);
    }

    private function assertStock(int $router, int $cable): void
    {
        $this->assertSame($router, $this->router->fresh()->stock_quantity);
        $this->assertSame($cable, $this->cable->fresh()->stock_quantity);
    }

    public function test_a_confirmed_payment_makes_the_order_paid_and_is_acknowledged(): void
    {
        Event::fake([OrderPaid::class, PaymentNeedsAPerson::class]);
        $order = $this->placedOrder();

        $response = $this->providerCalls($this->providerMessage($order));

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());
        $this->assertStringContainsString('text/plain', $response->headers->get('Content-Type'));

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);

        $payment = $order->payments()->sole();
        $this->assertSame(PaymentStatus::Succeeded, $payment->status);
        $this->assertSame('tok-1', $payment->provider_payment_token);
        $this->assertSame($order->total_incl_vat_cents, $payment->paid_amount_cents);
        $this->assertSame('banklink', $payment->channel);
        $this->assertSame('swedbank', $payment->bank);
        $this->assertNotNull($payment->paid_at);

        $event = PaymentEvent::query()->sole();
        $this->assertSame(PaymentOutcome::Paid->value, $event->outcome);
        $this->assertNotNull($event->processed_at);
        $this->assertSame($payment->id, $event->payment_id);

        Event::assertDispatchedTimes(OrderPaid::class, 1);
        Event::assertNotDispatched(PaymentNeedsAPerson::class);
        $this->assertStock(8, 4);
    }

    public function test_the_same_message_twice_changes_and_sends_nothing_the_second_time(): void
    {
        Event::fake([OrderPaid::class, PaymentNeedsAPerson::class]);
        $order = $this->placedOrder();
        $message = $this->providerMessage($order);

        $this->providerCalls($message)->assertOk();
        $paidAt = $order->fresh()->paid_at;
        $this->travel(5)->minutes();
        $second = $this->providerCalls($message);

        $second->assertOk();
        $this->assertSame('OK', $second->getContent());
        $this->assertEquals($paidAt, $order->fresh()->paid_at);
        $this->assertSame(1, PaymentEvent::query()->count());
        Event::assertDispatchedTimes(OrderPaid::class, 1);
        $this->assertStock(8, 4);
    }

    public function test_a_message_with_a_bad_signature_is_refused_and_changes_nothing(): void
    {
        Event::fake([OrderPaid::class]);
        $order = $this->placedOrder();
        $message = $this->providerMessage($order);
        $message['paid_amount'] = '1';

        $response = $this->providerCalls($message);

        $response->assertStatus(400);
        $this->assertNotSame('OK', $response->getContent());
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertSame(0, PaymentEvent::query()->count());
        Event::assertNotDispatched(OrderPaid::class);
    }

    public function test_an_unsigned_or_empty_message_is_refused(): void
    {
        $order = $this->placedOrder();
        $unsigned = $this->providerMessage($order);
        unset($unsigned['signature']);

        $this->providerCalls($unsigned)->assertStatus(400);
        $this->providerCalls([])->assertStatus(400);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_a_genuine_message_about_an_order_we_do_not_have_is_acknowledged_and_kept(): void
    {
        $order = $this->placedOrder();

        $first = $this->providerCalls($this->providerMessage($order, ['order_number' => 'AS-NOSUCH00']));
        $second = $this->providerCalls($this->providerMessage($order, ['order_number' => 'AS-NOSUCH00']));

        $this->assertSame('OK', $first->getContent());
        $this->assertSame('OK', $second->getContent());
        $event = PaymentEvent::query()->sole();
        $this->assertNull($event->order_id);
        $this->assertSame(PaymentOutcome::UnknownOrder->value, $event->outcome);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_a_failure_or_a_pending_message_after_the_payment_succeeded_undoes_nothing(): void
    {
        $order = $this->placedOrder();
        $this->providerCalls($this->providerMessage($order))->assertOk();

        $this->providerCalls($this->providerMessage($order, ['status' => 'pending', 'payment_token' => null]))->assertOk();
        $this->providerCalls($this->providerMessage($order, ['status' => 'failed', 'payment_token' => null]))->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Succeeded, $order->payments()->sole()->status);
        $this->assertSame(
            [PaymentOutcome::Paid->value, PaymentOutcome::Ignored->value, PaymentOutcome::Ignored->value],
            PaymentEvent::query()->orderBy('id')->pluck('outcome')->all(),
        );
        $this->assertStock(8, 4);
    }

    public function test_a_customer_who_cancelled_can_pay_afterwards(): void
    {
        Event::fake([OrderPaid::class]);
        $order = $this->placedOrder();

        $this->providerCalls($this->providerMessage($order, ['status' => 'pending', 'payment_token' => null]))->assertOk();
        $this->assertSame(PaymentStatus::Pending, $order->payments()->sole()->status);

        $this->providerCalls($this->providerMessage($order, ['status' => 'failed', 'payment_token' => null]))->assertOk();
        $this->assertSame(PaymentStatus::Failed, $order->payments()->sole()->status);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertStock(8, 4);

        $this->providerCalls($this->providerMessage($order))->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Succeeded, $order->payments()->sole()->status);
        Event::assertDispatchedTimes(OrderPaid::class, 1);
    }

    public function test_the_wrong_amount_is_acknowledged_but_does_not_pay_the_order(): void
    {
        Event::fake([OrderPaid::class, PaymentNeedsAPerson::class]);
        $order = $this->placedOrder();

        $this->providerCalls($this->providerMessage($order, ['paid_amount' => $order->total_incl_vat_cents - 1]))->assertOk()->assertSee('OK');

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertNotSame(PaymentStatus::Succeeded, $order->payments()->sole()->status);
        $this->assertSame($order->total_incl_vat_cents - 1, $order->payments()->sole()->paid_amount_cents);
        $this->assertSame(PaymentOutcome::AmountMismatch->value, PaymentEvent::query()->sole()->outcome);
        Event::assertNotDispatched(OrderPaid::class);
        Event::assertDispatched(PaymentNeedsAPerson::class, fn (PaymentNeedsAPerson $event): bool => $event->outcome === PaymentOutcome::AmountMismatch && $event->order->is($order));
    }

    public function test_the_wrong_currency_or_a_different_requested_amount_does_not_pay_the_order_either(): void
    {
        Event::fake([OrderPaid::class, PaymentNeedsAPerson::class]);
        $order = $this->placedOrder();

        $this->providerCalls($this->providerMessage($order, ['currency' => 'USD', 'payment_token' => 'a']))->assertOk();
        $this->providerCalls($this->providerMessage($order, ['amount' => 1, 'payment_token' => 'b']))->assertOk();

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertSame(2, PaymentEvent::query()->where('outcome', PaymentOutcome::AmountMismatch->value)->count());
        Event::assertNotDispatched(OrderPaid::class);
    }

    public function test_a_payment_after_the_order_expired_pays_it_again_if_the_stock_is_still_there(): void
    {
        Event::fake([OrderPaid::class, PaymentNeedsAPerson::class]);
        $order = $this->placedOrder();
        $this->travelTo(now()->addMinutes(30));
        $this->artisan('app:expire-unpaid-orders')->assertSuccessful();
        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        $this->assertStock(10, 5);

        $this->providerCalls($this->providerMessage($order))->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(PaymentOutcome::PaidLate->value, PaymentEvent::query()->sole()->outcome);
        $this->assertStock(8, 4);
        Event::assertDispatchedTimes(OrderPaid::class, 1);
        Event::assertNotDispatched(PaymentNeedsAPerson::class);
    }

    public function test_a_payment_after_the_order_expired_and_the_stock_went_is_kept_and_reported_and_takes_no_stock(): void
    {
        Event::fake([OrderPaid::class, PaymentNeedsAPerson::class]);
        $order = $this->placedOrder();
        $this->travelTo(now()->addMinutes(30));
        $this->artisan('app:expire-unpaid-orders')->assertSuccessful();
        // Someone else bought the cables meanwhile; the router is still there.
        $this->cable->update(['stock_quantity' => 0]);

        $this->providerCalls($this->providerMessage($order))->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Expired, $order->status);
        $this->assertSame(PaymentStatus::Succeeded, $order->payments()->sole()->status);
        $this->assertSame(PaymentOutcome::PaidAfterExpiry->value, PaymentEvent::query()->sole()->outcome);
        $this->assertStock(10, 0);
        Event::assertNotDispatched(OrderPaid::class);
        Event::assertDispatched(PaymentNeedsAPerson::class, fn (PaymentNeedsAPerson $event): bool => $event->outcome === PaymentOutcome::PaidAfterExpiry);
    }

    public function test_a_payment_for_a_cancelled_order_is_kept_and_reported(): void
    {
        Event::fake([OrderPaid::class, PaymentNeedsAPerson::class]);
        $order = $this->placedOrder();
        $order->transitionTo(OrderStatus::Cancelled);

        $this->providerCalls($this->providerMessage($order))->assertOk();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Succeeded, $order->payments()->sole()->status);
        Event::assertNotDispatched(OrderPaid::class);
        Event::assertDispatched(PaymentNeedsAPerson::class, fn (PaymentNeedsAPerson $event): bool => $event->outcome === PaymentOutcome::PaidAfterCancel);
    }

    public function test_a_second_payment_for_a_paid_order_is_reported_and_does_nothing_more(): void
    {
        Event::fake([OrderPaid::class, PaymentNeedsAPerson::class]);
        $order = $this->placedOrder();
        $this->providerCalls($this->providerMessage($order))->assertOk();

        $this->providerCalls($this->providerMessage($order, ['payment_token' => 'tok-2']))->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame('tok-1', $order->payments()->sole()->provider_payment_token);
        $this->assertSame(
            [PaymentOutcome::Paid->value, PaymentOutcome::DoublePayment->value],
            PaymentEvent::query()->orderBy('id')->pluck('outcome')->all(),
        );
        Event::assertDispatchedTimes(OrderPaid::class, 1);
        Event::assertDispatched(PaymentNeedsAPerson::class, fn (PaymentNeedsAPerson $event): bool => $event->outcome === PaymentOutcome::DoublePayment);
        $this->assertStock(8, 4);
    }

    public function test_a_second_payment_through_another_provider_payment_is_also_reported(): void
    {
        Event::fake([OrderPaid::class, PaymentNeedsAPerson::class]);
        $order = $this->placedOrder();
        $this->providerCalls($this->providerMessage($order))->assertOk();

        $this->providerCalls($this->providerMessage($order, ['transaction_id' => 'FAKE0002', 'payment_token' => 'tok-9']))->assertOk();

        $this->assertSame(2, $order->payments()->count());
        $this->assertSame(2, $order->payments()->where('status', PaymentStatus::Succeeded)->count());
        Event::assertDispatchedTimes(OrderPaid::class, 1);
        Event::assertDispatched(PaymentNeedsAPerson::class, fn (PaymentNeedsAPerson $event): bool => $event->outcome === PaymentOutcome::DoublePayment);
    }

    public function test_the_provider_closing_the_window_gives_the_order_up_at_once_and_only_once(): void
    {
        $order = $this->placedOrder();
        $message = $this->providerMessage($order, ['status' => 'window_closed', 'payment_token' => null]);

        $this->providerCalls($message)->assertOk();
        $this->providerCalls($message)->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Expired, $order->status);
        $this->assertSame(PaymentStatus::Failed, $order->payments()->sole()->status);
        $this->assertStock(10, 5);
        $this->assertSame([PaymentOutcome::WindowClosed->value], PaymentEvent::query()->pluck('outcome')->all());
    }

    public function test_the_provider_closing_the_window_on_a_paid_order_changes_nothing(): void
    {
        $order = $this->placedOrder();
        $this->providerCalls($this->providerMessage($order))->assertOk();

        $this->providerCalls($this->providerMessage($order, ['status' => 'window_closed', 'payment_token' => null]))->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Succeeded, $order->payments()->sole()->status);
        $this->assertStock(8, 4);
    }

    public function test_a_payment_message_for_an_order_with_no_payment_row_creates_one(): void
    {
        $order = Order::factory()->withLine($this->router, 1)->create();
        Product::takeStock($this->router->id, 1);

        $this->providerCalls($this->providerMessage($order, ['transaction_id' => 'FAKE7777']))->assertOk();

        $payment = $order->payments()->sole();
        $this->assertSame('FAKE7777', $payment->provider_reference);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_only_the_configured_provider_and_only_posts_are_accepted(): void
    {
        $order = $this->placedOrder();

        $this->post(route('payments.callback', ['provider' => 'opay']), $this->providerMessage($order))->assertNotFound();
        $this->get(route('payments.callback', ['provider' => 'fake']))->assertStatus(405);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_a_payment_is_confirmed_even_when_the_shop_and_checkout_were_switched_off_meanwhile(): void
    {
        $order = $this->placedOrder();
        $settings = app(ShopSettings::class);
        $settings->enabled = false;
        $settings->checkoutEnabled = false;
        $settings->save();

        $this->providerCalls($this->providerMessage($order))->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    /**
     * Laravel skips its forgery check while running tests, so this asks the
     * middleware directly which requests it would never check.
     */
    public function test_the_callback_needs_no_token_but_the_checkout_form_does(): void
    {
        $middleware = app(PreventRequestForgery::class);
        $isExempt = new ReflectionMethod($middleware, 'inExceptArray');

        $this->assertTrue($isExempt->invoke($middleware, Request::create('/payments/opay/callback', 'POST')));
        $this->assertTrue($isExempt->invoke($middleware, Request::create('/payments/fake/callback', 'POST')));
        $this->assertFalse($isExempt->invoke($middleware, Request::create('/checkout', 'POST')));
        $this->assertFalse($isExempt->invoke($middleware, Request::create('/cart/items', 'POST')));
    }

    public function test_the_gateway_the_callback_uses_is_the_one_checkout_uses(): void
    {
        $this->assertInstanceOf(FakeGateway::class, app(PaymentGateway::class));
        $this->assertSame(app(PaymentGateway::class), $this->gateway());
    }
}

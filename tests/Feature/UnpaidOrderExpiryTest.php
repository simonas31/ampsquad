<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\Checkout\OrderStock;
use App\Services\Payment\FakeGateway;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\PaymentNotification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Tests\Concerns\CheckoutScenario;
use Tests\TestCase;

/**
 * Orders nobody paid for are given up once their window and grace are over,
 * their stock goes back exactly once, and the provider is asked first
 * whether the money came after all.
 */
class UnpaidOrderExpiryTest extends TestCase
{
    use CheckoutScenario;
    use RefreshDatabase;

    private Product $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->router = Product::factory()->priced(1000)->inStock(10)->create();
    }

    /**
     * An order that holds two units, whose window closed $minutesAgo minutes ago.
     */
    private function heldOrder(int $minutesAgo): Order
    {
        $order = Order::factory()->withLine($this->router, 2)->create([
            'payment_expires_at' => now()->subMinutes($minutesAgo),
        ]);
        Product::takeStock($this->router->id, 2);
        Payment::factory()->for($order)->create(['provider' => 'fake']);

        return $order;
    }

    public function test_an_order_past_its_window_and_grace_is_given_up_and_its_stock_goes_back(): void
    {
        $order = $this->heldOrder(Order::EXPIRY_GRACE_MINUTES + 1);
        $this->assertSame(8, $this->router->fresh()->stock_quantity);

        $this->artisan('app:expire-unpaid-orders')->expectsOutputToContain('Gave up 1 unpaid order(s).')->assertSuccessful();

        $order->refresh();
        $this->assertSame(OrderStatus::Expired, $order->status);
        $this->assertNotNull($order->expired_at);
        $this->assertSame(PaymentStatus::Failed, $order->payments()->sole()->status);
        $this->assertSame(10, $this->router->fresh()->stock_quantity);
    }

    public function test_an_order_inside_its_window_or_its_grace_is_left_alone(): void
    {
        $open = $this->heldOrder(-10);
        $inGrace = $this->heldOrder(Order::EXPIRY_GRACE_MINUTES - 1);

        $this->artisan('app:expire-unpaid-orders')->expectsOutputToContain('Gave up 0')->assertSuccessful();

        $this->assertSame(OrderStatus::PendingPayment, $open->fresh()->status);
        $this->assertSame(OrderStatus::PendingPayment, $inGrace->fresh()->status);
        $this->assertSame(6, $this->router->fresh()->stock_quantity);
    }

    public function test_orders_in_other_statuses_are_left_alone(): void
    {
        $orders = collect(['paid', 'shipped', 'cancelled', 'expired', 'refunded'])->map(
            fn (string $state): Order => Order::factory()->{$state}()->withLine($this->router)->create(['payment_expires_at' => now()->subHour()]),
        );

        $this->artisan('app:expire-unpaid-orders')->assertSuccessful();

        $this->assertSame(
            ['paid', 'shipped', 'cancelled', 'expired', 'refunded'],
            $orders->map(fn (Order $order): string => $order->fresh()->status->value)->all(),
        );
        $this->assertSame(10, $this->router->fresh()->stock_quantity);
    }

    public function test_running_it_again_or_twice_at_once_puts_the_stock_back_once(): void
    {
        $order = $this->heldOrder(30);

        $this->artisan('app:expire-unpaid-orders')->assertSuccessful();
        $this->artisan('app:expire-unpaid-orders')->assertSuccessful();

        $this->assertSame(10, $this->router->fresh()->stock_quantity);

        // Two runs that both picked the order up before either moved it.
        $stale = Order::factory()->withLine($this->router, 3)->create();
        Product::takeStock($this->router->id, 3);
        $first = Order::query()->findOrFail($stale->id);
        $second = Order::query()->findOrFail($stale->id);
        foreach ([$first, $second] as $copy) {
            if ($copy->transitionTo(OrderStatus::Expired)) {
                app(OrderStock::class)->release($copy);
            }
        }
        $this->assertSame(10, $this->router->fresh()->stock_quantity);
    }

    public function test_a_payment_the_provider_confirms_when_asked_pays_the_order_instead_of_losing_it(): void
    {
        Event::fake([OrderPaid::class]);
        $order = $this->heldOrder(30);
        $payment = $order->payments()->sole();
        $this->gateway()->reportOnLookUp($payment->provider_reference, new PaymentNotification(
            provider: 'fake',
            eventKey: 'asked-1',
            orderNumber: $order->number,
            providerReference: $payment->provider_reference,
            status: PaymentStatus::Succeeded,
            providerStatus: 'succeeded',
            requestedAmountCents: $order->total_incl_vat_cents,
            paidAmountCents: $order->total_incl_vat_cents,
            currency: 'EUR',
            paymentToken: 'tok-asked',
        ));

        $this->artisan('app:expire-unpaid-orders')->expectsOutputToContain('Gave up 0')->assertSuccessful();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(8, $this->router->fresh()->stock_quantity);
        Event::assertDispatchedTimes(OrderPaid::class, 1);
    }

    public function test_a_provider_that_cannot_be_asked_does_not_stop_the_order_being_given_up(): void
    {
        $order = $this->heldOrder(30);

        $this->artisan('app:expire-unpaid-orders')->assertSuccessful();

        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
    }

    public function test_a_provider_that_fails_when_asked_does_not_stop_it_either(): void
    {
        $order = $this->heldOrder(30);
        $other = $this->heldOrder(30);
        $this->app->instance(PaymentGateway::class, new class extends FakeGateway
        {
            public function lookUp(Payment $payment): ?PaymentNotification
            {
                throw new \RuntimeException('The provider is down.');
            }
        });
        Exceptions::fake();

        $this->artisan('app:expire-unpaid-orders')->expectsOutputToContain('Gave up 2')->assertSuccessful();

        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        $this->assertSame(OrderStatus::Expired, $other->fresh()->status);
        Exceptions::assertReported(\RuntimeException::class);
    }

    public function test_many_orders_are_all_given_up(): void
    {
        $orders = collect(range(1, 60))->map(fn (): Order => Order::factory()->create(['payment_expires_at' => now()->subHour()]));

        $this->artisan('app:expire-unpaid-orders')->expectsOutputToContain('Gave up 60')->assertSuccessful();

        $this->assertSame(60, Order::query()->where('status', OrderStatus::Expired)->count());
        $this->assertSame(60, $orders->count());
    }

    public function test_it_runs_every_minute_without_overlapping(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'app:expire-unpaid-orders'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_stale_unpaid_orders_are_anonymized_daily(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'app:anonymize-stale-orders'));

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * *', $event->expression);
    }
}

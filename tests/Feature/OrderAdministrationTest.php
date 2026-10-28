<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\Checkout\OrderAdministration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the shop does to an order after it is placed. Stock goes back only for
 * the call that really moves the order, so doing it twice never gives it back
 * twice.
 */
class OrderAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private Product $router;

    private OrderAdministration $administration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->router = Product::factory()->priced(1000)->inStock(10)->create();
        $this->administration = app(OrderAdministration::class);
    }

    /**
     * An order as checkout leaves it, holding 3 of the router.
     *
     * @param  'awaitingPayment'|'paid'|'shipped'  $state
     */
    private function order(string $state): Order
    {
        $order = Order::factory()->{$state}()->withLine($this->router, 3)->create();
        Product::takeStock($this->router->id, 3);
        $payment = Payment::factory()->for($order);
        ($state === 'awaitingPayment' ? $payment : $payment->succeeded())->create();

        return $order->refresh();
    }

    public function test_a_paid_order_is_shipped(): void
    {
        $order = $this->order('paid');

        $this->assertTrue($this->administration->markShipped($order));

        $order->refresh();
        $this->assertSame(OrderStatus::Shipped, $order->status);
        $this->assertNotNull($order->shipped_at);
        $this->assertSame(7, $this->router->fresh()->stock_quantity);
    }

    public function test_only_a_paid_order_can_be_shipped(): void
    {
        $waiting = $this->order('awaitingPayment');
        $shipped = $this->order('shipped');
        $shippedAt = $shipped->shipped_at;

        $this->assertFalse($this->administration->markShipped($waiting));
        $this->assertFalse($this->administration->markShipped($shipped));

        $this->assertSame(OrderStatus::PendingPayment, $waiting->fresh()->status);
        $this->assertNull($waiting->fresh()->shipped_at);
        $this->assertEquals($shippedAt, $shipped->fresh()->shipped_at);
    }

    public function test_cancelling_an_order_nobody_paid_gives_the_stock_back_and_closes_its_payment(): void
    {
        $order = $this->order('awaitingPayment');

        // Whatever the admin asked for, an order that holds stock for nobody gives it up.
        $this->assertTrue($this->administration->cancel($order, restock: false));

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame(10, $this->router->fresh()->stock_quantity);
        $this->assertSame(PaymentStatus::Failed, $order->payments()->sole()->status);
    }

    public function test_cancelling_a_paid_order_puts_the_stock_back_when_asked_and_leaves_the_money_alone(): void
    {
        $order = $this->order('paid');

        $this->assertTrue($this->administration->cancel($order, restock: true));

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(10, $this->router->fresh()->stock_quantity);
        $this->assertSame(PaymentStatus::Succeeded, $order->payments()->sole()->status);
        $this->assertTrue($order->fresh()->needsAPerson());
    }

    public function test_cancelling_a_paid_order_keeps_the_stock_out_when_the_goods_are_gone(): void
    {
        $order = $this->order('paid');

        $this->assertTrue($this->administration->cancel($order, restock: false));

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(7, $this->router->fresh()->stock_quantity);
    }

    public function test_a_shipped_order_cannot_be_cancelled(): void
    {
        $order = $this->order('shipped');

        $this->assertFalse($this->administration->cancel($order, restock: true));

        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
        $this->assertSame(7, $this->router->fresh()->stock_quantity);
    }

    public function test_two_cancellations_of_the_same_order_give_the_stock_back_once(): void
    {
        $order = $this->order('paid');
        $first = Order::query()->findOrFail($order->id);
        $second = Order::query()->findOrFail($order->id);

        $this->assertTrue($this->administration->cancel($first, restock: true));
        $this->assertFalse($this->administration->cancel($second, restock: true));

        $this->assertSame(10, $this->router->fresh()->stock_quantity);
    }

    public function test_a_waiting_order_that_was_paid_meanwhile_is_cancelled_as_the_paid_order_it_now_is(): void
    {
        $order = $this->order('awaitingPayment');
        $stale = Order::query()->findOrFail($order->id);

        // The payment confirmation gets there first (it takes no stock: the order already held it).
        $order->transitionTo(OrderStatus::Paid);
        $order->payments()->update(['status' => PaymentStatus::Succeeded]);

        $this->assertSame(OrderStatus::PendingPayment, $stale->status, 'the admin is still looking at the old page');
        $this->assertTrue($this->administration->cancel($stale, restock: true));

        // Cancelled once, stock back once, and the money it took is flagged, not lost.
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(10, $this->router->fresh()->stock_quantity);
        $this->assertSame(PaymentStatus::Succeeded, $order->payments()->sole()->status);
        $this->assertTrue($order->fresh()->needsAPerson());
    }

    public function test_a_shipped_order_that_came_back_is_refunded_with_its_payments(): void
    {
        $order = $this->order('shipped');

        $this->assertTrue($this->administration->refund($order, restock: false));

        $order->refresh();
        $this->assertSame(OrderStatus::Refunded, $order->status);
        $this->assertNotNull($order->refunded_at);
        $payment = $order->payments()->sole();
        $this->assertSame(PaymentStatus::Refunded, $payment->status);
        $this->assertNotNull($payment->refunded_at);
        $this->assertSame(7, $this->router->fresh()->stock_quantity);
        $this->assertFalse($order->needsAPerson());
    }

    public function test_the_returned_goods_go_back_in_stock_only_when_asked_and_only_once(): void
    {
        $order = $this->order('shipped');
        $first = Order::query()->findOrFail($order->id);
        $second = Order::query()->findOrFail($order->id);

        $this->assertTrue($this->administration->refund($first, restock: true));
        $this->assertFalse($this->administration->refund($second, restock: true));

        $this->assertSame(10, $this->router->fresh()->stock_quantity);
    }

    public function test_only_a_shipped_order_can_be_refunded(): void
    {
        foreach (['awaitingPayment', 'paid'] as $state) {
            $order = $this->order($state);

            $this->assertFalse($this->administration->refund($order, restock: true));
            $this->assertNull($order->fresh()->refunded_at);
        }

        $this->assertSame(0, Payment::query()->where('status', PaymentStatus::Refunded)->count());
    }

    public function test_a_payment_is_marked_refunded_once(): void
    {
        $order = $this->order('paid');
        $payment = $order->payments()->sole();

        $this->assertTrue($this->administration->markPaymentRefunded($payment));
        $refundedAt = $payment->fresh()->refunded_at;
        $this->travel(1)->hour();
        $this->assertFalse($this->administration->markPaymentRefunded($payment));

        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertEquals($refundedAt, $payment->fresh()->refunded_at);
    }

    public function test_a_payment_that_is_not_succeeded_cannot_be_marked_refunded(): void
    {
        $order = $this->order('awaitingPayment');

        $this->assertFalse($this->administration->markPaymentRefunded($order->payments()->sole()));
    }

    public function test_refunding_the_money_of_a_cancelled_order_settles_it(): void
    {
        $order = $this->order('paid');
        $this->administration->cancel($order, restock: true);
        $this->assertTrue($order->fresh()->needsAPerson());

        $this->administration->markPaymentRefunded($order->payments()->sole());

        $this->assertFalse($order->fresh()->needsAPerson());
    }
}

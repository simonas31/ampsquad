<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * An order moves along a fixed set of transitions, and each move happens
 * once: of two requests racing to move the same order, one wins and the other
 * is told so. Giving stock back is only done by the winner, which is what
 * keeps it from being given back twice.
 */
class OrderStatusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Written out here on purpose, apart from the enum, so changing the
     * rules means changing them in two places and seeing why.
     *
     * @return array<string, list<OrderStatus>>
     */
    private static function expectedTransitions(): array
    {
        return [
            'pending_payment' => [OrderStatus::Paid, OrderStatus::Expired, OrderStatus::Cancelled],
            'paid' => [OrderStatus::Shipped, OrderStatus::Cancelled],
            'shipped' => [OrderStatus::Refunded],
            'expired' => [OrderStatus::Paid],
            'cancelled' => [],
            'refunded' => [],
        ];
    }

    public function test_the_transitions_are_exactly_these(): void
    {
        foreach (OrderStatus::cases() as $from) {
            $expected = self::expectedTransitions()[$from->value];

            foreach (OrderStatus::cases() as $to) {
                $this->assertSame(
                    in_array($to, $expected, true),
                    $from->canTransitionTo($to),
                    "{$from->value} to {$to->value}",
                );
            }
        }
    }

    public function test_no_status_moves_to_itself(): void
    {
        foreach (OrderStatus::cases() as $status) {
            $this->assertFalse($status->canTransitionTo($status));
        }
    }

    public function test_cancelled_and_refunded_are_the_end(): void
    {
        $this->assertSame([], OrderStatus::Cancelled->allowedTransitions());
        $this->assertSame([], OrderStatus::Refunded->allowedTransitions());
    }

    public function test_only_a_payment_confirmation_route_leads_to_paid(): void
    {
        $leadingToPaid = collect(OrderStatus::cases())
            ->filter(fn (OrderStatus $from): bool => $from->canTransitionTo(OrderStatus::Paid))
            ->map(fn (OrderStatus $from): string => $from->value)
            ->values()
            ->all();

        $this->assertSame(['pending_payment', 'expired'], $leadingToPaid);
    }

    public function test_every_status_but_the_first_has_a_timestamp_column_that_exists(): void
    {
        $this->assertNull(OrderStatus::PendingPayment->timestampColumn());

        foreach (OrderStatus::cases() as $status) {
            if ($status === OrderStatus::PendingPayment) {
                continue;
            }

            $this->assertTrue(Schema::hasColumn('orders', $status->timestampColumn()), $status->value);
        }
    }

    public function test_a_move_changes_the_status_and_stamps_the_time(): void
    {
        $order = Order::factory()->create();

        $this->assertTrue($order->transitionTo(OrderStatus::Paid));

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertNull($order->fresh()->shipped_at);
    }

    public function test_a_move_that_is_not_allowed_changes_nothing(): void
    {
        $order = Order::factory()->create();

        $this->assertFalse($order->transitionTo(OrderStatus::Shipped));

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertNull($order->fresh()->shipped_at);
    }

    public function test_an_expired_order_can_still_be_paid(): void
    {
        $order = Order::factory()->expired()->create();

        $this->assertTrue($order->transitionTo(OrderStatus::Paid));

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_the_second_of_two_requests_for_the_same_move_loses(): void
    {
        $order = Order::factory()->create();
        $first = Order::query()->findOrFail($order->id);
        $second = Order::query()->findOrFail($order->id);

        // Both think the order is waiting, and both are allowed to move it.
        $this->assertTrue($second->status->canTransitionTo(OrderStatus::Paid));

        $this->assertTrue($first->transitionTo(OrderStatus::Paid));
        $this->assertFalse($second->transitionTo(OrderStatus::Paid));

        $this->assertSame(OrderStatus::Paid, $second->status);
    }

    public function test_an_expiry_that_lost_to_a_payment_leaves_the_order_paid(): void
    {
        $order = Order::factory()->pastItsWindow()->create();
        $payment = Order::query()->findOrFail($order->id);
        $sweeper = Order::query()->findOrFail($order->id);

        $this->assertTrue($payment->transitionTo(OrderStatus::Paid));
        $this->assertFalse($sweeper->transitionTo(OrderStatus::Expired));

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertNull($order->fresh()->expired_at);
    }

    public function test_stock_goes_back_once_however_often_the_expiry_runs(): void
    {
        $product = Product::factory()->inStock(5)->create();
        $order = Order::factory()->pastItsWindow()->withLine($product, 2)->create();
        Product::takeStock($product->id, 2);
        $this->assertSame(3, $product->fresh()->stock_quantity);

        foreach (range(1, 3) as $run) {
            // Each run starts from the row as it was read before the others.
            $copy = Order::query()->findOrFail($order->id);

            if ($copy->transitionTo(OrderStatus::Expired)) {
                $copy->lines->each(fn ($line) => Product::returnStock($line->product_id, $line->quantity));
            }
        }

        $this->assertSame(5, $product->fresh()->stock_quantity);
        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
    }
}

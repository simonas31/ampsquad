<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * What the shop does to an order after it is placed: ship it, cancel it,
 * record a refund. Each is one transaction under the order's row lock, and
 * only the call that actually moves the order may put stock back, so a
 * double click, two admins, or a payment confirmation arriving at the same
 * moment never give stock back twice. Each returns whether this call did it;
 * false means the order was not where it was thought to be and nothing changed.
 *
 * Refunds are made by hand in the payment provider's portal: recording one
 * here only records it.
 */
class OrderAdministration
{
    public function __construct(private readonly OrderStock $stock) {}

    public function markShipped(Order $order): bool
    {
        return DB::transaction(fn (): bool => $this->lock($order)->transitionTo(OrderStatus::Shipped));
    }

    /**
     * Cancels an order that is waiting for payment (its stock always goes
     * back) or one that was paid (its stock goes back when $restock says the
     * goods are still there). A paid order's money is not touched: it is
     * refunded by hand and recorded with markPaymentRefunded().
     */
    public function cancel(Order $order, bool $restock): bool
    {
        return DB::transaction(function () use ($order, $restock): bool {
            $locked = $this->lock($order);
            $wasWaiting = $locked->status === OrderStatus::PendingPayment;

            if (! $locked->transitionTo(OrderStatus::Cancelled)) {
                return false;
            }

            if ($wasWaiting || $restock) {
                $this->stock->release($locked);
            }

            if ($wasWaiting) {
                $locked->payments()->where('status', PaymentStatus::Pending)->update(['status' => PaymentStatus::Failed]);
            }

            return true;
        });
    }

    /**
     * An order that was shipped came back and was refunded: the order and its
     * payments are marked refunded, and the goods go back in stock if they
     * can be sold again.
     */
    public function refund(Order $order, bool $restock): bool
    {
        return DB::transaction(function () use ($order, $restock): bool {
            $locked = $this->lock($order);

            if (! $locked->transitionTo(OrderStatus::Refunded)) {
                return false;
            }

            $locked->payments()->where('status', PaymentStatus::Succeeded)->update([
                'status' => PaymentStatus::Refunded,
                'refunded_at' => now(),
            ]);

            if ($restock) {
                $this->stock->release($locked);
            }

            return true;
        });
    }

    /**
     * Records that the money of one payment has been paid back (an extra
     * payment, or one for an order that could not keep it).
     */
    public function markPaymentRefunded(Payment $payment): bool
    {
        return Payment::query()
            ->whereKey($payment->getKey())
            ->where('status', PaymentStatus::Succeeded)
            ->update(['status' => PaymentStatus::Refunded, 'refunded_at' => now()]) === 1;
    }

    private function lock(Order $order): Order
    {
        return Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
    }
}

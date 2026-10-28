<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\PaymentProcessor;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Gives up the orders nobody paid for: their stock goes back on the shelf.
 * It waits for the payment window plus the grace period, and before it gives
 * an order up it asks the provider whether the money came after all, because
 * a message that never arrived must not cost a customer their order.
 *
 * Safe to run at any time and as often as you like: an order is given up by
 * the one run that moves it out of "awaiting payment", so stock is returned
 * once however the runs overlap.
 */
class UnpaidOrderExpirer
{
    private const int CHUNK = 50;

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly PaymentProcessor $processor,
        private readonly OrderStock $stock,
    ) {}

    /**
     * @return int How many orders were given up.
     */
    public function expire(): int
    {
        $expired = 0;

        Order::query()
            ->where('status', OrderStatus::PendingPayment)
            ->where('payment_expires_at', '<', now()->subMinutes(Order::EXPIRY_GRACE_MINUTES))
            ->chunkById(self::CHUNK, function ($orders) use (&$expired): void {
                foreach ($orders as $order) {
                    // One order that cannot be given up must not stop the rest,
                    // or it would block every other order every minute.
                    try {
                        if ($this->expireOne($order)) {
                            $expired++;
                        }
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                }
            });

        return $expired;
    }

    private function expireOne(Order $order): bool
    {
        $this->askTheProvider($order);

        return DB::transaction(function () use ($order): bool {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->transitionTo(OrderStatus::Expired)) {
                return false;
            }

            $this->stock->release($locked);
            $locked->payments()->where('status', PaymentStatus::Pending)->update(['status' => PaymentStatus::Failed]);

            return true;
        });
    }

    /**
     * What the provider knows is handled as if its message had come. If it
     * cannot be asked, the order is still given up: a payment confirmed later
     * is accepted by the processor (and reinstates the order if it can).
     */
    private function askTheProvider(Order $order): void
    {
        $order->payments()
            ->where('provider', $this->gateway->name())
            ->where('status', PaymentStatus::Pending)
            ->get()
            ->each(function (Payment $payment): void {
                try {
                    $found = $this->gateway->lookUp($payment);

                    if ($found !== null) {
                        $this->processor->handle($found);
                    }
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
    }
}

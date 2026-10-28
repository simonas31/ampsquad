<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Moves an order's stock in and out of the shelves. Every caller already
 * holds the order's row lock or is placing the order, inside a transaction.
 * The lines are always handled in product order, so two orders that share
 * products take their locks in the same order and cannot deadlock.
 *
 * Giving stock back is not safe to repeat: callers do it only for the one
 * request that made the order leave its status (Order::transitionTo()).
 */
class OrderStock
{
    /**
     * Takes every line's units, or throws having taken none that matter: the
     * caller's transaction rolls back.
     *
     * @throws InsufficientStock
     */
    public function take(Order $order): void
    {
        foreach ($this->linesOf($order) as $line) {
            if (! Product::takeStock($line->product_id, $line->quantity)) {
                throw new InsufficientStock($line);
            }
        }
    }

    /**
     * Takes the units again for an order that gave them up, all or nothing
     * (a savepoint undoes the ones already taken when a later one is gone).
     * Returns whether it could.
     */
    public function takeAgain(Order $order): bool
    {
        try {
            DB::transaction(fn () => $this->take($order));

            return true;
        } catch (InsufficientStock) {
            return false;
        }
    }

    public function release(Order $order): void
    {
        foreach ($this->linesOf($order) as $line) {
            Product::returnStock($line->product_id, $line->quantity);
        }
    }

    /**
     * @return iterable<int, OrderLine>
     */
    private function linesOf(Order $order): iterable
    {
        return $order->lines()->orderBy('product_id')->get();
    }
}

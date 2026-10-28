<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An order has just become paid. Dispatched once per order, by the request
 * that moved it, and only once its transaction has committed.
 */
class OrderPaid implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Order $order) {}
}

<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\PaymentOutcome;
use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Money and an order disagree (see PaymentOutcome::needsAPerson()), and
 * nothing will put it right by itself.
 */
class PaymentNeedsAPerson implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly PaymentOutcome $outcome,
    ) {}
}

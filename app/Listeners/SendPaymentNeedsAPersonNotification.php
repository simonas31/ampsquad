<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\PaymentNeedsAPerson;
use App\Mail\PaymentNeedsAPersonMail;
use App\Services\RecipientNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Goes to the same people as paid orders: whoever looks at orders also has
 * to deal with money that came when it should not have.
 */
class SendPaymentNeedsAPersonNotification implements ShouldQueue
{
    public function __construct(private readonly RecipientNotifier $notifier) {}

    public function handle(PaymentNeedsAPerson $event): void
    {
        $this->notifier->notify(NotificationType::OrderPaid, new PaymentNeedsAPersonMail($event->order, $event->outcome));
    }
}

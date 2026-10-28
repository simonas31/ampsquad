<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\OrderPaid;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderPaidMail;
use App\Services\RecipientNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * The customer's confirmation, in their language, and the shop's heads-up
 * to everyone subscribed to paid orders.
 */
class SendOrderPaidNotifications implements ShouldQueue
{
    public function __construct(private readonly RecipientNotifier $notifier) {}

    public function handle(OrderPaid $event): void
    {
        Mail::to($event->order->customer_email)
            ->locale($event->order->locale)
            ->queue(new OrderConfirmationMail($event->order));

        $this->notifier->notify(NotificationType::OrderPaid, new OrderPaidMail($event->order));
    }
}

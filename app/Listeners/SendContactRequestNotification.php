<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\ContactRequestSubmitted;
use App\Mail\ContactRequestReceivedMail;
use App\Services\RecipientNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendContactRequestNotification implements ShouldQueue
{
    public function __construct(private readonly RecipientNotifier $notifier) {}

    public function handle(ContactRequestSubmitted $event): void
    {
        $this->notifier->notify(
            NotificationType::ContactFormSubmitted,
            new ContactRequestReceivedMail($event->contactRequest),
        );
    }
}

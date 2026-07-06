<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Recipient;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Adding a new notification type later is: add a NotificationType case,
 * fire an event somewhere, add a listener that calls notify() — no schema
 * or Filament resource changes needed.
 */
class RecipientNotifier
{
    public function notify(NotificationType $type, Mailable $mailable): void
    {
        Recipient::query()
            ->active()
            ->subscribedTo($type)
            ->get()
            ->each(fn (Recipient $recipient) => Mail::to($recipient->email)->queue($mailable));
    }
}

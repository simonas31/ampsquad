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
    /**
     * Mails go to the shop's team in the app's current language, which is
     * Lithuanian (APP_LOCALE) in the queue worker that sends them, unless
     * $locale says another of the site's languages. The language is always
     * set on the mail explicitly so that it is the same wherever it is built.
     * Run synchronously (QUEUE_CONNECTION=sync, as in tests) it follows the
     * language of the page the visitor who caused it was reading.
     */
    public function notify(NotificationType $type, Mailable $mailable, ?string $locale = null): void
    {
        $locale ??= app()->getLocale();

        Recipient::query()
            ->active()
            ->subscribedTo($type)
            ->get()
            ->each(fn (Recipient $recipient) => Mail::to($recipient->email)->locale($locale)->queue($mailable));
    }
}

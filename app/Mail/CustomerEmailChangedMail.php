<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Sent to the address an account has just left, in the customer's language
 * (the sender sets it with ->locale()), so that someone who did not ask for
 * the change finds out. It names the new address only in part.
 */
class CustomerEmailChangedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Customer $customer) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.customer_email_changed.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.customer-email-changed',
            with: [
                'customer' => $this->customer,
                'newEmail' => $this->maskedNewEmail(),
            ],
        );
    }

    /**
     * jonas@example.com as j****@example.com.
     */
    private function maskedNewEmail(): string
    {
        [$name, $domain] = array_pad(explode('@', (string) $this->customer->email, 2), 2, '');

        return Str::mask($name, '*', 1).'@'.$domain;
    }
}

<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells a customer their account was closed, in their language (the sender
 * sets it with ->locale()), so that someone who did not close it finds out.
 * It is built from the name alone, not from the account, which by the time
 * it is sent is only kept for its retention period.
 */
class CustomerAccountDeletedMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $fullName) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.customer_account_deleted.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.customer-account-deleted',
            with: [
                'fullName' => $this->fullName,
                'years' => Customer::RETENTION_YEARS,
            ],
        );
    }
}

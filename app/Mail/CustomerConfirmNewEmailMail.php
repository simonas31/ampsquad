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
use Illuminate\Support\Facades\URL;

/**
 * Sent to the address a customer wants to move their account to, in their
 * language (the sender sets it with ->locale()). The account only moves when
 * its link is followed, which shows the customer can read mail sent there.
 * Like the verification link it is signed and ties itself to that address.
 */
class CustomerConfirmNewEmailMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Customer $customer) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.customer_confirm_new_email.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.customer-confirm-new-email',
            with: [
                'customer' => $this->customer,
                'confirmUrl' => $this->confirmationUrl(),
                'hours' => Customer::VERIFICATION_LINK_HOURS,
            ],
        );
    }

    public function confirmationUrl(): string
    {
        return URL::temporarySignedRoute(
            'customer.email.confirm',
            now()->addHours(Customer::VERIFICATION_LINK_HOURS),
            ['id' => $this->customer->getKey(), 'hash' => sha1((string) $this->customer->pending_email)],
        );
    }
}

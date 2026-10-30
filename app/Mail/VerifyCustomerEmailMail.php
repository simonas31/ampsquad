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
 * What a new customer gets to prove the address is theirs: it welcomes them
 * and carries the link that verifies it, in the language they registered in
 * (the sender sets it with ->locale()). The link is made when the mail is
 * built, so its time starts when the mail is sent.
 */
class VerifyCustomerEmailMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * An account deleted while its mail waits in the queue needs no mail.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Customer $customer) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.customer_verify_email.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.verify-customer-email',
            with: [
                'customer' => $this->customer,
                'verifyUrl' => $this->verificationUrl(),
                'hours' => Customer::VERIFICATION_LINK_HOURS,
            ],
        );
    }

    /**
     * Signed and short lived, and tied to the address it was sent to: it
     * stops working if the address changes first.
     */
    public function verificationUrl(): string
    {
        return URL::temporarySignedRoute(
            'customer.verification.verify',
            now()->addHours(Customer::VERIFICATION_LINK_HOURS),
            ['id' => $this->customer->getKey(), 'hash' => sha1($this->customer->getEmailForVerification())],
        );
    }
}

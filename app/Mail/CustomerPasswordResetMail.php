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
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/**
 * The link that lets a customer choose a new password, in their language
 * (the sender sets it with ->locale()). The token in it is the secret: it
 * works once, for the time the password broker allows.
 */
class CustomerPasswordResetMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Customer $customer, public readonly string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.customer_password_reset.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.customer-password-reset',
            with: [
                'customer' => $this->customer,
                'resetUrl' => $this->resetUrl(),
                'minutes' => (int) config('auth.passwords.customers.expire'),
            ],
        );
    }

    /**
     * The page is in the customer's language, like their orders' pages in
     * their mails: the queue worker that builds this has no visitor and so
     * no language of its own.
     */
    public function resetUrl(): string
    {
        $url = route('customer.password.reset', ['token' => $this->token, 'email' => $this->customer->getEmailForPasswordReset()]);

        return LaravelLocalization::getLocalizedURL($this->customer->locale, $url);
    }
}

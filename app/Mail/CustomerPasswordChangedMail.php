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
 * Tells a customer their password was changed, in their language (the sender
 * sets it with ->locale()). It holds nothing secret: it is there so that
 * someone who did not change it knows to act.
 */
class CustomerPasswordChangedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Customer $customer) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.customer_password_changed.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.customer-password-changed',
            with: [
                'customer' => $this->customer,
                'forgotPasswordUrl' => LaravelLocalization::getLocalizedURL($this->customer->locale, route('customer.password.request')),
            ],
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\PaymentOutcome;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lang;

/**
 * Tells the shop that a payment and its order disagree, and what to do.
 */
class PaymentNeedsAPersonMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly PaymentOutcome $outcome,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.payment_problem.subject', ['number' => $this->order->number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.payment-needs-a-person',
            with: [
                'order' => $this->order,
                'explanation' => self::explanationOf($this->outcome),
                'adminUrl' => OrderResource::getUrl('view', ['record' => $this->order], panel: 'admin'),
            ],
        );
    }

    /**
     * What happened and what to do about it, for the person reading, in the
     * current language.
     */
    public static function explanationOf(PaymentOutcome $outcome): string
    {
        $key = 'mail.payment_problem.explanations.'.$outcome->value;

        return Lang::has($key) ? __($key) : __('mail.payment_problem.explanations.default');
    }
}

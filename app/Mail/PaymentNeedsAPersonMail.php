<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\PaymentOutcome;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

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
            subject: "Payment needs attention: order {$this->order->number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.payment-needs-a-person',
            with: [
                'order' => $this->order,
                'explanation' => self::explanationOf($this->outcome),
            ],
        );
    }

    /**
     * What happened and what to do about it, for the person reading.
     */
    public static function explanationOf(PaymentOutcome $outcome): string
    {
        return match ($outcome) {
            PaymentOutcome::PaidAfterExpiry => 'The customer paid after the order had expired, and the goods are no longer in stock. The order was left expired. Refund the payment in the OPAY portal, or put the goods back in stock and ask a developer to reinstate the order.',
            PaymentOutcome::PaidAfterCancel => 'The customer paid an order that was cancelled. The order was left cancelled. Refund the payment in the OPAY portal.',
            PaymentOutcome::DoublePayment => 'The customer paid this order more than once. The order is paid, and nothing was done for the extra payment. Refund the extra payment in the OPAY portal.',
            PaymentOutcome::AmountMismatch => 'The provider says the customer paid an amount, or in a currency, that is not the order\'s. The order was left unpaid. Check the payment in the OPAY portal: refund it, or contact the customer.',
            default => 'This payment needs a person to look at it.',
        };
    }
}

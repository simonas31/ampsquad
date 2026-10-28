<?php

declare(strict_types=1);

namespace App\Mail;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Number;

/**
 * Tells the shop a paid order is waiting to be looked at, in the language
 * the sender sets (RecipientNotifier: Lithuanian unless told otherwise).
 */
class OrderPaidMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.order_paid.subject', [
                'number' => $this->order->number,
                'total' => Number::currency($this->order->total_incl_vat_cents / 100, $this->order->currency, app()->getLocale()),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.order-paid',
            with: [
                'order' => $this->order->loadMissing('lines'),
                'adminUrl' => OrderResource::getUrl('view', ['record' => $this->order], panel: 'admin'),
            ],
        );
    }
}

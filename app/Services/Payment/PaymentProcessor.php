<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentOutcome;
use App\Enums\PaymentStatus;
use App\Events\OrderPaid;
use App\Events\PaymentNeedsAPerson;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Services\Checkout\OrderStock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Applies what a payment provider says to the order it is about, whichever
 * provider it is. This and nothing else makes an order paid.
 *
 * Messages arrive more than once, late and out of order, so every rule here
 * is about not doing a thing twice and not undoing a thing that happened:
 *  - the message is stored first, under a key that is unique, and a message
 *    that is already stored is dropped;
 *  - the order is locked, so two messages for it are handled one at a time;
 *  - the order only becomes paid by a guarded status change, and only the one
 *    that made the change sends the mail;
 *  - a success is never undone by a later failure or "still pending";
 *  - money that arrives for an order that cannot take it (expired with the
 *    stock gone, cancelled, already paid, the wrong amount) is recorded and
 *    reported to a person, never hidden and never doubled up.
 */
class PaymentProcessor
{
    public function __construct(private readonly OrderStock $stock) {}

    public function handle(PaymentNotification $notification): PaymentOutcome
    {
        $order = Order::query()->where('number', $notification->orderNumber)->first();

        if ($order === null) {
            return $this->handleUnknownOrder($notification);
        }

        return DB::transaction(function () use ($notification, $order): PaymentOutcome {
            $order = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            $event = $this->store($notification, $order);

            if ($event === null) {
                return PaymentOutcome::Duplicate;
            }

            $payment = $this->paymentFor($order, $notification);
            $outcome = $this->apply($order, $payment, $notification);

            $event->update([
                'payment_id' => $payment->getKey(),
                'outcome' => $outcome->value,
                'processed_at' => now(),
            ]);

            if ($outcome->needsAPerson()) {
                PaymentNeedsAPerson::dispatch($order, $outcome);
            }

            return $outcome;
        });
    }

    private function handleUnknownOrder(PaymentNotification $notification): PaymentOutcome
    {
        $event = $this->store($notification, null);

        $event?->update(['outcome' => PaymentOutcome::UnknownOrder->value, 'processed_at' => now()]);

        return $event === null ? PaymentOutcome::Duplicate : PaymentOutcome::UnknownOrder;
    }

    /**
     * Keeps the message. Null when it is already kept, which is what makes a
     * repeat harmless.
     */
    private function store(PaymentNotification $notification, ?Order $order): ?PaymentEvent
    {
        try {
            return PaymentEvent::query()->create([
                'provider' => $notification->provider,
                'event_key' => $notification->eventKey,
                'order_id' => $order?->getKey(),
                'provider_status' => $notification->providerStatus,
                'payload' => $notification->payload,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /**
     * The payment the message is about: the one with the provider's id, else
     * one of the order's that the provider has not named yet, else a new one.
     */
    private function paymentFor(Order $order, PaymentNotification $notification): Payment
    {
        $payments = $order->payments()->where('provider', $notification->provider);

        $payment = $notification->providerReference === null
            ? null
            : (clone $payments)->where('provider_reference', $notification->providerReference)->first();

        $payment ??= (clone $payments)->whereNull('provider_reference')->latest('id')->first();

        if ($payment === null) {
            $payment = $order->payments()->create([
                'provider' => $notification->provider,
                'amount_cents' => $order->total_incl_vat_cents,
                'currency' => $order->currency,
            ]);
        }

        if ($payment->provider_reference === null && $notification->providerReference !== null) {
            $payment->provider_reference = $notification->providerReference;
            $payment->save();
        }

        return $payment;
    }

    private function apply(Order $order, Payment $payment, PaymentNotification $notification): PaymentOutcome
    {
        return match ($notification->status) {
            PaymentStatus::Succeeded => $this->applySuccess($order, $payment, $notification),
            PaymentStatus::Pending => $this->applyPending($payment, $notification),
            PaymentStatus::Failed => $notification->windowClosed
                ? $this->applyWindowClosed($order, $payment)
                : $this->applyFailure($payment),
            PaymentStatus::Refunded => PaymentOutcome::Ignored,
        };
    }

    private function applySuccess(Order $order, Payment $payment, PaymentNotification $notification): PaymentOutcome
    {
        if (
            $notification->paidAmountCents !== $order->total_incl_vat_cents
            || ($notification->requestedAmountCents !== null && $notification->requestedAmountCents !== $order->total_incl_vat_cents)
            || strtoupper((string) $notification->currency) !== $order->currency
        ) {
            $payment->update(['paid_amount_cents' => $notification->paidAmountCents]);

            return PaymentOutcome::AmountMismatch;
        }

        if ($payment->status === PaymentStatus::Succeeded) {
            return $payment->provider_payment_token === $notification->paymentToken
                ? PaymentOutcome::AlreadyPaid
                : PaymentOutcome::DoublePayment;
        }

        $anotherSucceeded = $order->payments()
            ->where('status', PaymentStatus::Succeeded)
            ->whereKeyNot($payment->getKey())
            ->exists();

        $payment->update([
            'status' => PaymentStatus::Succeeded,
            'provider_payment_token' => $notification->paymentToken,
            'paid_amount_cents' => $notification->paidAmountCents,
            'channel' => $notification->channel,
            'bank' => $notification->bank,
            'paid_at' => now(),
        ]);

        if ($anotherSucceeded || in_array($order->status, [OrderStatus::Paid, OrderStatus::Shipped, OrderStatus::Refunded], true)) {
            return PaymentOutcome::DoublePayment;
        }

        return match ($order->status) {
            OrderStatus::PendingPayment => $this->markPaid($order, PaymentOutcome::Paid),
            OrderStatus::Expired => $this->stock->takeAgain($order)
                ? $this->markPaid($order, PaymentOutcome::PaidLate)
                : PaymentOutcome::PaidAfterExpiry,
            OrderStatus::Cancelled => PaymentOutcome::PaidAfterCancel,
            default => PaymentOutcome::Ignored,
        };
    }

    private function markPaid(Order $order, PaymentOutcome $outcome): PaymentOutcome
    {
        if (! $order->transitionTo(OrderStatus::Paid)) {
            return PaymentOutcome::Ignored;
        }

        OrderPaid::dispatch($order);

        return $outcome;
    }

    private function applyPending(Payment $payment, PaymentNotification $notification): PaymentOutcome
    {
        if ($payment->status === PaymentStatus::Succeeded) {
            return PaymentOutcome::Ignored;
        }

        $payment->update(['status' => PaymentStatus::Pending, 'channel' => $notification->channel ?? $payment->channel]);

        return PaymentOutcome::Pending;
    }

    private function applyFailure(Payment $payment): PaymentOutcome
    {
        if ($payment->status === PaymentStatus::Succeeded) {
            return PaymentOutcome::Ignored;
        }

        $payment->update(['status' => PaymentStatus::Failed]);

        return PaymentOutcome::Failed;
    }

    /**
     * The provider will take no more money for this payment, so the order
     * need not wait out its grace period.
     */
    private function applyWindowClosed(Order $order, Payment $payment): PaymentOutcome
    {
        if ($payment->status === PaymentStatus::Succeeded) {
            return PaymentOutcome::Ignored;
        }

        $payment->update(['status' => PaymentStatus::Failed]);

        if ($order->status === OrderStatus::PendingPayment && $order->transitionTo(OrderStatus::Expired)) {
            $this->stock->release($order);
        }

        return PaymentOutcome::WindowClosed;
    }
}

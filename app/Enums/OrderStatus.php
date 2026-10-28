<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where an order is in its life. It only ever moves along allowedTransitions(),
 * and Order::transitionTo() makes each move happen once.
 *
 * Stock follows the status: an order takes its stock when it is created
 * (PendingPayment), and gives it back when it ends in Expired or Cancelled.
 * A Refunded order was shipped, so whether its goods go back on the shelf is
 * for the person refunding it to say.
 */
enum OrderStatus: string implements HasColor, HasLabel
{
    /** Created, stock taken, waiting for the payment provider to confirm. */
    case PendingPayment = 'pending_payment';

    /** The provider confirmed the money. Only a payment callback gets an order here. */
    case Paid = 'paid';

    /** Sent or handed over. */
    case Shipped = 'shipped';

    /** Called off by the shop. */
    case Cancelled = 'cancelled';

    /** Nobody paid in time. */
    case Expired = 'expired';

    /** Shipped, then returned and refunded by the shop. */
    case Refunded = 'refunded';

    public function getLabel(): string
    {
        return __('admin.enums.order_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PendingPayment => 'warning',
            self::Paid => 'info',
            self::Shipped => 'success',
            self::Cancelled, self::Expired => 'gray',
            self::Refunded => 'danger',
        };
    }

    /**
     * Expired can still become Paid: a bank can confirm a payment after the
     * window closed. The caller must take the stock again first, and only
     * moves the order if that works.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Paid, self::Expired, self::Cancelled],
            self::Expired => [self::Paid],
            self::Paid => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Refunded],
            self::Cancelled, self::Refunded => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /**
     * The orders column stamped with the time an order enters this status.
     */
    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::PendingPayment => null,
            self::Paid => 'paid_at',
            self::Shipped => 'shipped_at',
            self::Cancelled => 'cancelled_at',
            self::Expired => 'expired_at',
            self::Refunded => 'refunded_at',
        };
    }
}

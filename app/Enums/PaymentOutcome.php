<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What was done with a message from the payment provider, kept on the
 * message (payment_events.outcome). The problems are the ones where money
 * and the order disagree, which a person has to look at.
 */
enum PaymentOutcome: string
{
    /** The order is paid. */
    case Paid = 'paid';

    /** Paid after the order had expired, and the stock could be taken again. */
    case PaidLate = 'paid_late';

    /** The same message seen again; nothing was done. */
    case Duplicate = 'duplicate';

    /** The same payment confirmed again; nothing was done. */
    case AlreadyPaid = 'already_paid';

    /** Not finished yet. */
    case Pending = 'pending';

    /** The customer cancelled or the payment failed. */
    case Failed = 'failed';

    /** The provider closed the payment window; the order was given up. */
    case WindowClosed = 'window_closed';

    /** Too late to matter: for example a failure reported after the payment succeeded. */
    case Ignored = 'ignored';

    /** About an order we do not have. */
    case UnknownOrder = 'unknown_order';

    /** Money came for an order that had expired and the stock is gone: refund it. */
    case PaidAfterExpiry = 'paid_after_expiry';

    /** Money came for an order that was cancelled: refund it. */
    case PaidAfterCancel = 'paid_after_cancel';

    /** A second payment came for an order that is already paid: refund it. */
    case DoublePayment = 'double_payment';

    /** The amount or currency paid is not the order's: the order was left unpaid. */
    case AmountMismatch = 'amount_mismatch';

    public function needsAPerson(): bool
    {
        return match ($this) {
            self::PaidAfterExpiry, self::PaidAfterCancel, self::DoublePayment, self::AmountMismatch => true,
            default => false,
        };
    }
}

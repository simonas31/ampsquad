<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What a payment provider has told us about one payment. The provider's own
 * codes are mapped onto these by its gateway, so nothing outside a gateway
 * knows them.
 */
enum PaymentStatus: string implements HasColor, HasLabel
{
    /** Started or not finished; the customer can still pay. */
    case Pending = 'pending';

    /** The provider confirmed the money. */
    case Succeeded = 'succeeded';

    /** Cancelled by the customer or timed out. */
    case Failed = 'failed';

    /** Paid back by the shop. Recorded by hand: no refund is sent from here. */
    case Refunded = 'refunded';

    public function getLabel(): string
    {
        return __('admin.enums.payment_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Succeeded => 'success',
            self::Failed => 'gray',
            self::Refunded => 'danger',
        };
    }
}

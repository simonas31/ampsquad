<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How an order reaches the customer. The price of each is the shop's to set
 * (see the order's shipping columns, which keep what was charged).
 */
enum ShippingMethod: string implements HasLabel
{
    case Pickup = 'pickup';
    case LpExpress = 'lp_express';
    case Dpd = 'dpd';
    case Omniva = 'omniva';
    case LietuvosPastas = 'lietuvos_pastas';

    public function getLabel(): string
    {
        return $this->labelIn(app()->getLocale());
    }

    /**
     * The name in a given language, which an order keeps in the language of
     * the person who placed it.
     */
    public function labelIn(string $locale): string
    {
        return __('shop.shipping.'.$this->value, [], $locale);
    }

    /**
     * Whether the customer must say where to send it: an address, or the
     * parcel locker. Pickup happens at the shop.
     */
    public function needsDeliveryAddress(): bool
    {
        return $this !== self::Pickup;
    }
}

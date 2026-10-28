<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ShopSettings extends Settings
{
    /**
     * Master switch for the whole public shop and cart. Products stay
     * manageable in the admin panel while it is off.
     */
    public bool $enabled;

    /**
     * Whether visitors can check out and pay online. Needs the shop to be
     * enabled as well. Payment confirmations from the provider are accepted
     * whatever this is, so a payment started before it was switched off still
     * lands.
     */
    public bool $checkoutEnabled;

    public static function group(): string
    {
        return 'shop';
    }
}

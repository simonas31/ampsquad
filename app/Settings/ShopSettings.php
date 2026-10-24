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

    public static function group(): string
    {
        return 'shop';
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Number;

/**
 * Whole cents as text the way the current language writes money.
 */
final class Money
{
    public static function format(int $cents, string $currency = Product::CURRENCY): string
    {
        return Number::currency($cents / 100, $currency, app()->getLocale());
    }
}

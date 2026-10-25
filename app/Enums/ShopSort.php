<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The orders the shop listing can be sorted in. The values are what the
 * `sort` query parameter carries.
 */
enum ShopSort: string
{
    case Featured = 'featured';
    case PriceAsc = 'price_asc';
    case PriceDesc = 'price_desc';
    case Name = 'name';
    case Newest = 'newest';
}

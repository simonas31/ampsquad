<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\OrderLine;
use RuntimeException;

/**
 * The units of a line could not be taken out of stock: the product has
 * fewer left, or is no longer on sale.
 */
class InsufficientStock extends RuntimeException
{
    public function __construct(public readonly OrderLine $orderLine)
    {
        parent::__construct("Product {$orderLine->product_id} has fewer than {$orderLine->quantity} left.");
    }
}

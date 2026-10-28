<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Enums\CheckoutRefusal;
use RuntimeException;

/**
 * Checkout stopped before anything was charged or changed. The message is
 * already in the visitor's language and fit to show them.
 */
class CheckoutRefused extends RuntimeException
{
    public function __construct(public readonly CheckoutRefusal $reason)
    {
        parent::__construct($reason->message());
    }
}

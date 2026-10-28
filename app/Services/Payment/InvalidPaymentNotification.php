<?php

declare(strict_types=1);

namespace App\Services\Payment;

use RuntimeException;

/**
 * A message to the callback URL that is not from the provider or was
 * changed: its signature is wrong, a field is missing or malformed.
 */
class InvalidPaymentNotification extends RuntimeException {}

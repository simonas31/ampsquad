<?php

declare(strict_types=1);

namespace App\Services\Payment;

use RuntimeException;

/**
 * The provider could not be reached, is not set up, or refused the request.
 * The message is for the log: it never carries a secret.
 */
class PaymentGatewayException extends RuntimeException {}

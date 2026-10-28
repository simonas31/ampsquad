<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Payment\InvalidPaymentNotification;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\PaymentProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where the payment provider's server tells us what became of a payment.
 * No session, no CSRF token: the message is believed only if the gateway
 * can prove it is the provider's.
 *
 * A message that is not genuine is refused (400) and never acknowledged. One
 * that is genuine is applied and acknowledged, even when it changes nothing
 * (a repeat, an order we do not have): that is what stops the provider
 * sending it again. If applying it fails, the error is left to surface (500)
 * so the provider tries again later; applying is safe to repeat.
 */
class PaymentCallbackController extends Controller
{
    public function __invoke(Request $request, string $provider, PaymentGateway $gateway, PaymentProcessor $processor): Response
    {
        abort_unless($provider === $gateway->name(), 404);

        try {
            $notification = $gateway->parseNotification($request);
        } catch (InvalidPaymentNotification $exception) {
            Log::warning('A payment message was refused.', ['provider' => $provider, 'reason' => $exception->getMessage()]);

            return response('Invalid message', 400);
        }

        $processor->handle($notification);

        return $gateway->acknowledgement();
    }
}

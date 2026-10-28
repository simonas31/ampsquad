<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Everything the shop needs from a payment provider, and nothing that is
 * one provider's own. Carts, orders and the payment processor only know this;
 * a provider is a class that implements it, chosen by config('shop.payment_gateway').
 */
interface PaymentGateway
{
    /**
     * The provider's name as kept on payments and in the callback URL.
     */
    public function name(): string;

    /**
     * Starts a payment and says where to send the customer to pay it.
     *
     * @throws PaymentGatewayException when the provider cannot be reached or refuses
     */
    public function initiate(PaymentRequest $request): PaymentInitiation;

    /**
     * Reads a message the provider sent to the callback URL. Checks that the
     * provider really sent it and that nobody changed it on the way, and
     * throws when it cannot tell: nothing a message says is trusted before that.
     *
     * @throws InvalidPaymentNotification
     */
    public function parseNotification(Request $request): PaymentNotification;

    /**
     * What to answer the provider with once a message is dealt with, which is
     * the only thing that stops it sending the message again.
     */
    public function acknowledgement(): Response;

    /**
     * Asks the provider what became of a payment, for when its message never
     * came. Null when it does not know or cannot say right now.
     */
    public function lookUp(Payment $payment): ?PaymentNotification;
}

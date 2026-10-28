<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Services\CartService;
use App\Services\Checkout\CartFingerprint;
use App\Services\Payment\FakeGateway;
use App\Services\Payment\PaymentGateway;
use App\Settings\ShopSettings;

/**
 * The pieces every checkout and payment test needs: a shop with checkout
 * switched on, a visitor's cart, the form a visitor would send, and the
 * provider's messages as the fake gateway accepts them.
 */
trait CheckoutScenario
{
    protected function switchCheckoutOn(): void
    {
        $settings = app(ShopSettings::class);
        $settings->enabled = true;
        $settings->checkoutEnabled = true;
        $settings->save();
    }

    /**
     * A cart holding the given products, as [Product, quantity] pairs.
     *
     * @param  list<array{Product, int}>  $lines
     */
    protected function cartWith(array $lines): Cart
    {
        $cart = Cart::factory()->create();

        foreach ($lines as [$product, $quantity]) {
            CartItem::factory()->for($cart)->for($product)->quantity($quantity)->create();
        }

        return $cart;
    }

    protected function visitorWith(Cart $cart): static
    {
        return $this->withCookie(CartService::COOKIE, $cart->token);
    }

    /**
     * What the checkout page would give the form for this choice of delivery.
     */
    protected function fingerprintFor(Cart $cart, ShippingMethod $method): string
    {
        return CartFingerprint::for($cart->items()->with('product')->get(), $method);
    }

    /**
     * The form a visitor sends for the cart and delivery method.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function checkoutForm(Cart $cart, ShippingMethod $method, array $overrides = []): array
    {
        return [
            'name' => 'Jonas Jonaitis',
            'email' => 'jonas@example.test',
            'phone' => '+37060000000',
            'shipping_method_id' => $method->getKey(),
            'delivery_address' => $method->needsDeliveryAddress() ? 'Gedimino pr. 1, Vilnius' : null,
            'note' => null,
            'accept_terms' => true,
            'fingerprint' => $this->fingerprintFor($cart, $method),
            ...$overrides,
        ];
    }

    protected function gateway(): FakeGateway
    {
        $gateway = app(PaymentGateway::class);
        $this->assertInstanceOf(FakeGateway::class, $gateway);

        return $gateway;
    }

    /**
     * A message from the provider about an order, as the fake gateway
     * accepts it: signed, and by default a successful payment of the whole
     * total. A field overridden with null is left out of the message.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, string>
     */
    protected function providerMessage(Order $order, array $overrides = []): array
    {
        $payment = $order->payments()->first();

        return FakeGateway::signedMessage(array_filter([
            'status' => 'succeeded',
            'order_number' => $order->number,
            'transaction_id' => $payment?->provider_reference ?? 'FAKE0001',
            'amount' => $order->total_incl_vat_cents,
            'paid_amount' => $order->total_incl_vat_cents,
            'currency' => 'EUR',
            'payment_token' => 'tok-1',
            'channel' => 'banklink',
            'bank' => 'swedbank',
            ...$overrides,
        ], fn (mixed $value): bool => $value !== null));
    }
}

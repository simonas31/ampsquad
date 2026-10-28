<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Enums\CartLineStatus;
use App\Enums\CheckoutRefusal;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Services\CartService;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\PaymentGatewayException;
use App\Services\Payment\PaymentRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns a cart into an order and starts its payment, or refuses and changes
 * nothing.
 *
 * It all happens in one transaction under the cart's row lock: the cart is
 * read again, every line checked, what the visitor was shown compared with
 * what is true now, the order and its lines copied from the products, the
 * stock taken, the payment started with the provider, and the cart emptied.
 * Anything that goes wrong rolls the lot back, so there is never a half-made
 * order, stock held by nobody, or a lost cart. The provider is called inside
 * the transaction on purpose: the alternative is an order that exists, holds
 * stock and has no payment, which then has to be undone by hand. The cost is
 * that the products' rows stay locked for as long as the provider takes to
 * answer, a few hundred milliseconds, with a timeout.
 *
 * Prices, totals and statuses are never read from the visitor.
 */
class CheckoutService
{
    public function __construct(
        private readonly CartService $carts,
        private readonly OrderStock $stock,
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * What the visitor can choose from, each with what they would pay in
     * all and the fingerprint the form must send back for that choice.
     *
     * @param  Collection<int, CartItem>  $items  With their products loaded.
     * @return list<array{id: int, name: string, description: ?string, isPickup: bool, priceExclVatCents: int, priceInclVatCents: int, vatCents: int, totalInclVatCents: int, fingerprint: string}>
     */
    public function shippingOptions(Collection $items): array
    {
        $lines = $items->map(fn (CartItem $item): array => [
            'line_total_excl_vat_cents' => $item->lineExclVatCents(),
            'line_total_incl_vat_cents' => $item->lineInclVatCents(),
        ]);

        return ShippingMethod::query()->forCheckout()->get()
            ->map(function (ShippingMethod $method) use ($items, $lines): array {
                $totals = OrderTotals::forLines($lines, $method->price_excl_vat_cents);

                return [
                    'id' => $method->getKey(),
                    'name' => $method->name,
                    'description' => $method->description ?: null,
                    'isPickup' => $method->is_pickup,
                    'priceExclVatCents' => $method->price_excl_vat_cents,
                    'priceInclVatCents' => $totals->shippingInclVatCents,
                    'vatCents' => $totals->vatCents,
                    'totalInclVatCents' => $totals->totalInclVatCents,
                    'fingerprint' => CartFingerprint::for($items, $method),
                ];
            })
            ->all();
    }

    /**
     * @throws CheckoutRefused
     */
    public function place(Cart $cart, CheckoutData $data): Payment
    {
        return DB::transaction(function () use ($cart, $data): Payment {
            $this->carts->lock($cart->getKey());

            $items = $cart->items()->with('product')->orderBy('product_id')->get();

            if ($items->isEmpty()) {
                throw new CheckoutRefused(CheckoutRefusal::EmptyCart);
            }

            if ($items->contains(fn (CartItem $item): bool => $item->status() !== CartLineStatus::Available)) {
                throw new CheckoutRefused(CheckoutRefusal::LinesUnavailable);
            }

            $method = ShippingMethod::query()->active()->find($data->shippingMethodId)
                ?? throw new CheckoutRefused(CheckoutRefusal::ShippingUnavailable);

            if (! hash_equals(CartFingerprint::for($items, $method), $data->fingerprint)) {
                throw new CheckoutRefused(CheckoutRefusal::StaleCart);
            }

            $lines = $items->map(fn (CartItem $item): array => OrderLine::snapshotOf($item->product, $item->quantity, $data->locale));
            $totals = OrderTotals::forLines($lines, $method->price_excl_vat_cents);

            $order = Order::query()->create([
                'locale' => $data->locale,
                'currency' => Product::CURRENCY,
                'vat_rate_percent' => Product::vatRatePercent(),
                ...$totals->toAttributes(),
                'shipping_method_id' => $method->getKey(),
                'shipping_label' => $method->getTranslation('name', $data->locale),
                'shipping_details' => $method->getTranslation('description', $data->locale) ?: null,
                'shipping_is_pickup' => $method->is_pickup,
                'delivery_address' => $method->needsDeliveryAddress() ? $data->deliveryAddress : null,
                'customer_name' => $data->customerName,
                'customer_email' => $data->customerEmail,
                'customer_phone' => $data->customerPhone,
                'customer_note' => $data->customerNote,
                'terms_accepted_at' => now(),
            ]);

            $order->setRelation('lines', $order->lines()->createMany($lines->all()));

            try {
                $this->stock->take($order);
            } catch (InsufficientStock) {
                throw new CheckoutRefused(CheckoutRefusal::StockShort);
            }

            try {
                $initiation = $this->gateway->initiate(new PaymentRequest(
                    orderNumber: $order->number,
                    amountCents: $order->total_incl_vat_cents,
                    currency: $order->currency,
                    customerEmail: $order->customer_email,
                    locale: $order->locale,
                    returnUrl: route('orders.show', ['order' => $order->uuid]),
                    callbackUrl: route('payments.callback', ['provider' => $this->gateway->name()]),
                    windowMinutes: Order::PAYMENT_WINDOW_MINUTES,
                ));
            } catch (PaymentGatewayException $exception) {
                report($exception);

                throw new CheckoutRefused(CheckoutRefusal::PaymentUnavailable);
            }

            $payment = $order->payments()->create([
                'provider' => $this->gateway->name(),
                'provider_reference' => $initiation->providerReference,
                'amount_cents' => $order->total_incl_vat_cents,
                'currency' => $order->currency,
                'redirect_url' => $initiation->redirectUrl,
            ]);

            $cart->items()->delete();
            $cart->touch();

            return $payment->setRelation('order', $order);
        });
    }
}

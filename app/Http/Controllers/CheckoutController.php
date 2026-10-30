<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CartLineStatus;
use App\Enums\CheckoutRefusal;
use App\Enums\OrderStatus;
use App\Http\Requests\StoreCheckoutRequest;
use App\Http\Resources\CartResource;
use App\Http\Resources\CustomerAddressResource;
use App\Models\Order;
use App\Services\CartService;
use App\Services\Checkout\CheckoutRefused;
use App\Services\Checkout\CheckoutService;
use App\Services\Customers\CustomerAddressBook;
use App\Support\PageUrls;
use App\Support\Seo\Breadcrumbs;
use App\Support\Seo\SeoData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CheckoutController extends Controller
{
    /**
     * The order a visitor just placed, in their session, so that coming back
     * to checkout from the payment page (the browser's Back button) leads to
     * that order and its "pay now" and not to an empty cart.
     */
    private const string WAITING_ORDER_KEY = 'waiting_order';

    public function __construct(
        private readonly CartService $carts,
        private readonly CheckoutService $checkout,
        private readonly CustomerAddressBook $addressBook,
    ) {}

    /**
     * The checkout form's data: the cart, and the delivery methods with what
     * each would make the total and the fingerprint to send back for it. A
     * cart that cannot be checked out as it is goes back to the cart page
     * with the reason, so the visitor fixes it there.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $cart = $this->carts->forDisplay($request);

        if ($cart->items->isEmpty() && ($waiting = $this->waitingOrder($request)) !== null) {
            return redirect()->route('orders.show', ['order' => $waiting->uuid]);
        }

        $refusal = match (true) {
            $cart->items->isEmpty() => CheckoutRefusal::EmptyCart,
            $cart->hasLinesWithStatus(CartLineStatus::Reduced), $cart->hasLinesWithStatus(CartLineStatus::Unavailable) => CheckoutRefusal::LinesUnavailable,
            default => null,
        };

        if ($refusal !== null) {
            return redirect()->route('cart.show')->withErrors(['checkout' => $refusal->message()]);
        }

        $breadcrumbs = Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.cart'), 'url' => route('cart.show')],
            ['label' => __('nav.checkout'), 'url' => null],
        ]);

        // A customer signed in with a verified address gets their details and
        // saved addresses; anyone else, an unverified customer included, is
        // a guest and sees the same form as before.
        $customer = $request->user('customer');
        $customer = $customer?->hasVerifiedEmail() ? $customer : null;

        return Inertia::render('Checkout', [
            'customer' => $customer === null ? null : [
                'fullName' => $customer->full_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ],
            'savedAddresses' => $customer === null ? [] : CustomerAddressResource::collection($customer->addressesDefaultFirst()),
            'cart' => new CartResource($cart),
            'shippingMethods' => $this->checkout->shippingOptions($cart->items),
            'submitUrl' => route('checkout.store', absolute: false),
            'rulesUrl' => PageUrls::find('buying-and-selling-rules', 'pirkimo-pardavimo-taisyklės', 'buying-and-selling-rules'),
            'privacyUrl' => PageUrls::privacyPolicy(),
            'breadcrumbs' => $breadcrumbs->toArray(),
            'seo' => SeoData::make(pageTitle: __('nav.checkout'), noindex: true),
        ]);
    }

    /**
     * Places the order and sends the visitor to pay: an external redirect,
     * which Inertia follows as a full page visit.
     */
    public function store(StoreCheckoutRequest $request): SymfonyResponse
    {
        $cart = $this->carts->current($request);

        try {
            if ($cart === null) {
                throw new CheckoutRefused(CheckoutRefusal::EmptyCart);
            }

            $payment = $this->checkout->place($cart, $request->toData());
        } catch (CheckoutRefused $refused) {
            $back = $refused->reason->sendsBackToTheCart() ? redirect()->route('cart.show') : back();

            return $back->withErrors(['checkout' => $refused->getMessage()]);
        }

        $request->session()->put(self::WAITING_ORDER_KEY, $payment->order->uuid);

        $this->saveNewAddress($request);

        return Inertia::location($payment->redirect_url);
    }

    /**
     * Keeps the address the customer typed in parts, when they asked for it,
     * once their order is placed. It is a convenience: a customer who already
     * keeps the most addresses still has their order.
     */
    private function saveNewAddress(StoreCheckoutRequest $request): void
    {
        if (! $request->savesNewAddress()) {
            return;
        }

        try {
            $this->addressBook->add($request->checkoutCustomer(), $request->newAddressData(), false);
        } catch (ValidationException) {
            // The limit was reached; nothing to do.
        }
    }

    /**
     * The visitor's order that still waits for its payment, if its window is
     * open; otherwise null (paid, expired, or never placed in this session).
     */
    private function waitingOrder(Request $request): ?Order
    {
        $uuid = $request->session()->get(self::WAITING_ORDER_KEY);

        $order = is_string($uuid) ? Order::query()->where('uuid', $uuid)->first() : null;

        return $order?->status === OrderStatus::PendingPayment && $order->payment_expires_at->isFuture() ? $order : null;
    }
}

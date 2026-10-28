<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CartLineStatus;
use App\Enums\CheckoutRefusal;
use App\Http\Requests\StoreCheckoutRequest;
use App\Http\Resources\CartResource;
use App\Models\Page;
use App\Services\CartService;
use App\Services\Checkout\CheckoutRefused;
use App\Services\Checkout\CheckoutService;
use App\Support\Seo\Breadcrumbs;
use App\Support\Seo\SeoData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly CheckoutService $checkout,
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

        return Inertia::render('Checkout', [
            'cart' => new CartResource($cart),
            'shippingMethods' => $this->checkout->shippingOptions($cart->items),
            'submitUrl' => route('checkout.store', absolute: false),
            'termsUrl' => $this->pageUrl('terms-and-conditions'),
            'privacyUrl' => $this->pageUrl('privacy-policy'),
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

        return Inertia::location($payment->redirect_url);
    }

    /**
     * Where an admin-managed page is, or null if the shop has not made it:
     * the form links to the terms and the privacy policy the customer
     * accepts, and says so in plain words when there is nothing to link to.
     */
    private function pageUrl(string $key): ?string
    {
        $page = Page::query()->where('key', $key)->first();

        return $page === null ? null : route('pages.show', ['slug' => $page->slug], absolute: false);
    }
}

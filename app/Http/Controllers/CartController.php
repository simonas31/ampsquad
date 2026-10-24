<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\CartResource;
use App\Models\CartItem;
use App\Services\CartService;
use App\Support\Seo\Breadcrumbs;
use App\Support\Seo\SeoData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(private readonly CartService $carts) {}

    public function show(Request $request): Response
    {
        $breadcrumbs = Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.cart'), 'url' => null],
        ]);

        return Inertia::render('Cart', [
            'cart' => new CartResource($this->carts->forDisplay($request)),
            'maxQuantity' => CartItem::MAX_QUANTITY,
            'breadcrumbs' => $breadcrumbs->toArray(),
            'seo' => SeoData::make(pageTitle: __('nav.cart'), noindex: true),
        ]);
    }

    /**
     * Idempotent: a visitor with no cart already has an empty one.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $cart = $this->carts->current($request);

        if ($cart !== null) {
            $this->carts->clear($cart);
        }

        return back();
    }
}

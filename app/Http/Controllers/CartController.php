<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly CartService $carts) {}

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

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;

class CartItemController extends Controller
{
    public function __construct(private readonly CartService $carts) {}

    public function store(StoreCartItemRequest $request): RedirectResponse
    {
        $cart = $this->carts->currentOrCreate($request);

        $this->carts->add($cart, $request->product(), $request->quantity());

        return back();
    }

    public function update(UpdateCartItemRequest $request, CartItem $cartItem): RedirectResponse
    {
        $this->carts->setQuantity($cartItem, $request->quantity());

        return back();
    }

    public function destroy(CartItem $cartItem): RedirectResponse
    {
        $this->carts->remove($cartItem);

        return back();
    }
}

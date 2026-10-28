<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\CartLineStatus;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Needs `items.product.media` loaded (see CartService::forDisplay()). The
 * totals count what can be bought: a line holding more than is in stock counts
 * only what is left, and a line that can no longer be bought counts for
 * nothing. Both are still listed, flagged by their status, so the visitor can
 * see and fix them.
 *
 * `clearUrl` is where DELETE empties the cart. It lives here, not in the
 * shared `cartSummary` prop, because this page's own `cart` prop and the
 * shared one must never be mistaken for each other.
 *
 * @mixin Cart
 */
class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'items' => CartItemResource::collection($this->items),
            'subtotalNetCents' => $this->subtotalNetCents(),
            'vatRate' => Product::vatRatePercent(),
            'vatCents' => $this->vatCents(),
            'totalGrossCents' => $this->totalGrossCents(),
            'totalQuantity' => $this->totalQuantity(),
            'hasReducedItems' => $this->hasLinesWithStatus(CartLineStatus::Reduced),
            'hasUnavailableItems' => $this->hasLinesWithStatus(CartLineStatus::Unavailable),
            'clearUrl' => route('cart.destroy', absolute: false),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Needs `items.product.media` loaded (see CartService::forDisplay()). The
 * totals leave out lines that can no longer be bought; those lines are
 * still listed, flagged, so the visitor can see and remove them.
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
            'subtotalCents' => $this->subtotalCents(),
            'totalQuantity' => $this->totalQuantity(),
            'hasUnavailableItems' => $this->items->contains(
                fn (CartItem $item): bool => ! $item->isAvailable(),
            ),
        ];
    }
}

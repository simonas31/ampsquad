<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Needs `product` (with its media) loaded.
 *
 * `url` is where the line is updated (PATCH) or removed (DELETE), relative
 * so the request always stays on the page's own origin. A line
 * whose product can no longer be bought still shows what it was, with
 * `isAvailable` false and no product link, because the shop page for it
 * would 404.
 *
 * @mixin CartItem
 */
class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isAvailable = $this->isAvailable();

        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'isAvailable' => $isAvailable,
            'unitPriceCents' => $this->product->price_cents,
            'lineTotalCents' => $this->lineTotalCents(),
            'url' => route('cart.items.update', $this->resource, false),
            'product' => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'url' => $isAvailable ? route('shop.show', $this->product->slug) : null,
                'imageThumbUrl' => $this->product->getFirstMediaUrl('featured_image', 'thumbnail') ?: null,
            ],
        ];
    }
}

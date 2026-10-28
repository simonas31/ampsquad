<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Prices go out as whole cents, without and with VAT; the client only
 * formats them. `category` is left out unless the product's category was
 * loaded.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'excerpt' => $this->excerpt,
            'description' => $this->when($request->routeIs('shop.show'), fn () => $this->description),
            'priceExclVatCents' => $this->price_excl_vat_cents,
            'priceInclVatCents' => $this->priceInclVatCents(),
            'url' => route('shop.show', $this->slug),
            'imageUrl' => $this->featuredImageUrl('large'),
            'imageThumbUrl' => $this->featuredImageUrl('thumbnail'),
            'sku' => $this->sku,
            'manufacturer' => $this->whenLoaded('manufacturer', fn (): ?string => $this->manufacturer?->name),
            'category' => $this->whenLoaded('category', fn (): ?array => $this->category === null ? null : [
                'name' => $this->category->name,
                'url' => route('shop.index', ['category' => $this->category->slug]),
            ]),
            'inStock' => $this->isInStock(),
            'lowStockCount' => $this->lowStockCount(),
            'maxQuantity' => $this->maxPurchasableQuantity(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Prices go out as whole cents; the client only formats them.
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
            'priceCents' => $this->price_cents,
            'url' => route('shop.show', $this->slug),
            'imageUrl' => $this->getFirstMediaUrl('featured_image', 'large') ?: null,
            'imageThumbUrl' => $this->getFirstMediaUrl('featured_image', 'thumbnail') ?: null,
        ];
    }
}

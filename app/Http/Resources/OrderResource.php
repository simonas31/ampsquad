<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order as its own page shows it, to anyone who has its uuid. So no
 * contact details and no address: what was bought, what it cost, how it is
 * delivered and where it stands. Needs `lines.product.media` loaded. The photo
 * is the product's current one (the order keeps no copy of it), or none.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'status' => $this->status->value,
            'placedAt' => $this->created_at->toIso8601String(),
            'paymentExpiresAt' => $this->payment_expires_at->toIso8601String(),
            'payUrl' => $this->openPaymentUrl(),
            'lines' => $this->lines->map(fn (OrderLine $line): array => [
                'name' => $line->product_name,
                'sku' => $line->sku,
                'imageThumbUrl' => $line->product?->featuredImageUrl('thumbnail'),
                'quantity' => $line->quantity,
                'unitPriceInclVatCents' => $line->unit_price_incl_vat_cents,
                'lineTotalInclVatCents' => $line->line_total_incl_vat_cents,
            ])->values(),
            'shipping' => [
                'label' => $this->shipping_label,
                'details' => $this->shipping_details,
                'isPickup' => $this->shipping_is_pickup,
                'priceInclVatCents' => $this->shipping_incl_vat_cents,
            ],
            'subtotalExclVatCents' => $this->subtotal_excl_vat_cents,
            'shippingExclVatCents' => $this->shipping_excl_vat_cents,
            'vatRate' => $this->vat_rate_percent,
            'vatCents' => $this->vat_cents,
            'totalInclVatCents' => $this->total_incl_vat_cents,
        ];
    }
}

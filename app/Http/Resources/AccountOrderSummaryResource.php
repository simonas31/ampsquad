<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order as a row in the customer's list of orders. Needs `items_count`
 * (the units on it) loaded with it, so the list costs the same however many
 * orders it holds. The way to pay is asked for only when the order is still
 * waiting for its payment, which few are.
 *
 * @mixin Order
 */
class AccountOrderSummaryResource extends JsonResource
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
            'itemCount' => (int) $this->items_count,
            'totalInclVatCents' => $this->total_incl_vat_cents,
            'url' => route('account.orders.show', ['customerOrder' => $this->number], absolute: false),
            'payUrl' => $this->openPaymentUrl(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order as its owner sees it in their account: everything its own page
 * shows to anyone who has its uuid (see OrderResource), and, since only the
 * customer sees this, where it is delivered and their note, and the moments
 * of its life. Needs `lines.product.media` loaded.
 *
 * The moments are the order's own timestamps, in the order they happened:
 * the status stays the single source of truth, and this is only what it has
 * left behind. An order that expired and was paid late shows both.
 *
 * @mixin Order
 */
class AccountOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new OrderResource($this->resource))->toArray($request),
            'deliveryAddress' => $this->delivery_address,
            'customerNote' => $this->customer_note,
            'timeline' => $this->timeline(),
        ];
    }

    /**
     * @return list<array{key: string, at: string}>
     */
    private function timeline(): array
    {
        return collect([
            'placed' => $this->created_at,
            'paid' => $this->paid_at,
            'shipped' => $this->shipped_at,
            'cancelled' => $this->cancelled_at,
            'expired' => $this->expired_at,
            'refunded' => $this->refunded_at,
        ])
            ->filter()
            ->sort()
            ->map(fn ($at, string $key): array => ['key' => $key, 'at' => $at->toIso8601String()])
            ->values()
            ->all();
    }
}

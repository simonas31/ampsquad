<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A guest's shopping cart, found again through the token in their cookie.
 * Totals are never stored: they are worked out from the products' current
 * prices, and lines whose product can no longer be bought are left out.
 */
class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [];

    protected static function booted(): void
    {
        static::creating(function (self $cart): void {
            $cart->token ??= (string) Str::uuid();
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Sum of the purchasable lines, in cents.
     */
    public function subtotalCents(): int
    {
        return $this->purchasableItems()->sum(fn (CartItem $item): int => $item->lineTotalCents());
    }

    /**
     * Number of units across the purchasable lines (the header badge).
     */
    public function totalQuantity(): int
    {
        return $this->purchasableItems()->sum('quantity');
    }

    /**
     * @return Collection<int, CartItem>
     */
    private function purchasableItems(): Collection
    {
        $this->loadMissing('items.product');

        return $this->items->filter(fn (CartItem $item): bool => $item->isAvailable());
    }
}

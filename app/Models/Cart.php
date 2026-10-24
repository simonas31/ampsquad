<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
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

    use MassPrunable;

    /**
     * How long an untouched cart is kept. The cart cookie lasts as long.
     */
    public const int LIFETIME_DAYS = 30;

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

    /**
     * Carts nobody has touched for LIFETIME_DAYS (run daily by the
     * scheduler). Their lines go with them through the foreign key.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('updated_at', '<', now()->subDays(self::LIFETIME_DAYS));
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

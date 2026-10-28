<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CartLineStatus;
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
 * prices and stock. A line holding more than is in stock counts only what is
 * left, and a line whose product can no longer be bought counts for nothing
 * (see CartItem::countedQuantity()).
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
     * Most distinct products one cart may hold. Nobody orders more at once
     * through a shop like this, and without a cap a script could grow a cart
     * to thousands of lines, each loaded with its product and photo every
     * time the cart page is opened.
     */
    public const int MAX_LINES = 100;

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
     * Sum of the counted lines without VAT, in cents.
     */
    public function subtotalExclVatCents(): int
    {
        return $this->lines()->sum(fn (CartItem $item): int => $item->lineExclVatCents());
    }

    /**
     * Sum of the counted lines with VAT, in cents. What the visitor pays.
     */
    public function totalInclVatCents(): int
    {
        return $this->lines()->sum(fn (CartItem $item): int => $item->lineInclVatCents());
    }

    /**
     * The VAT in the total, in cents: the difference between the two sums,
     * so the subtotal excl. VAT and the VAT always add up to the total.
     */
    public function vatCents(): int
    {
        return $this->totalInclVatCents() - $this->subtotalExclVatCents();
    }

    /**
     * Number of counted units (the header badge).
     */
    public function totalQuantity(): int
    {
        return $this->lines()->sum(fn (CartItem $item): int => $item->countedQuantity());
    }

    /**
     * Whether any line is not worth what it holds, so the visitor should
     * look at the cart before relying on its totals.
     */
    public function hasLinesWithStatus(CartLineStatus $status): bool
    {
        return $this->lines()->contains(fn (CartItem $item): bool => $item->status() === $status);
    }

    /**
     * @return Collection<int, CartItem>
     */
    private function lines(): Collection
    {
        $this->loadMissing('items.product');

        return $this->items;
    }
}

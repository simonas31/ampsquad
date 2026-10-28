<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CartLineStatus;
use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One product in a cart, with the quantity the visitor asked for. That
 * quantity is a request, not a promise: status(), countedQuantity() and
 * lineExclVatCents() and lineInclVatCents() say what it is worth at the product's
 * current stock and price, and reading a cart never rewrites the line.
 */
class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    public const int MIN_QUANTITY = 1;

    public const int MAX_QUANTITY = 99;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity' => self::MIN_QUANTITY,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'quantity',
    ];

    /**
     * Changing a line counts as cart activity, so abandoned carts can be
     * told apart by carts.updated_at.
     *
     * @var list<string>
     */
    protected $touches = ['cart'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Includes soft deleted products, so a line can still say what it was
     * for after the product has been taken off sale.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function status(): CartLineStatus
    {
        return match (true) {
            ! $this->product->isPurchasable() => CartLineStatus::Unavailable,
            $this->quantity > $this->product->maxPurchasableQuantity() => CartLineStatus::Reduced,
            default => CartLineStatus::Available,
        };
    }

    /**
     * The units that count: the quantity, held down to what is in stock,
     * and none at all once the product cannot be bought.
     */
    public function countedQuantity(): int
    {
        return $this->product->isPurchasable()
            ? min($this->quantity, $this->product->maxPurchasableQuantity())
            : 0;
    }

    /**
     * Counted units times the product's current price without VAT, in cents.
     */
    public function lineExclVatCents(): int
    {
        return $this->countedQuantity() * $this->product->price_excl_vat_cents;
    }

    /**
     * Counted units times the product's current price with VAT, in cents.
     * The unit price is rounded before it is multiplied, so what the
     * visitor sees per unit times the quantity is exactly what they pay.
     */
    public function lineInclVatCents(): int
    {
        return $this->countedQuantity() * $this->product->priceInclVatCents();
    }
}

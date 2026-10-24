<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /**
     * Whether the product can still be bought.
     */
    public function isAvailable(): bool
    {
        return $this->product->isPurchasable();
    }

    /**
     * Quantity times the product's current price, in cents.
     */
    public function lineTotalCents(): int
    {
        return $this->quantity * $this->product->price_cents;
    }
}

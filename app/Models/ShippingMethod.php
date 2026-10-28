<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShippingMethodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * A way for a customer to get their order, managed in the admin panel:
 * a courier, a parcel locker, collecting at the shop. Nothing in the code
 * knows which exist. An order copies what it needs of the method it used
 * (see the orders table), so editing or deleting a method never changes an
 * order that was placed with it.
 *
 * The price is held without VAT, like a product's, and the VAT is added to it
 * the same way (Product::addVat()).
 */
class ShippingMethod extends Model
{
    /** @use HasFactory<ShippingMethodFactory> */
    use HasFactory;

    use HasTranslations;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'price_excl_vat_cents' => 0,
        'is_pickup' => false,
        'is_active' => true,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'price_excl_vat_cents',
        'is_pickup',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    public array $translatable = [
        'name',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_excl_vat_cents' => 'integer',
            'is_pickup' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * What a customer can choose from at checkout: the cheapest first, and
     * the oldest first among equals, so the list never reshuffles.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForCheckout(Builder $query): Builder
    {
        return $query->active()->orderBy('price_excl_vat_cents')->orderBy('id');
    }

    public function priceInclVatCents(): int
    {
        return Product::addVat($this->price_excl_vat_cents);
    }

    /**
     * Whether the customer must say where to send it: an address, or the
     * parcel locker. Not for collecting at the shop.
     */
    public function needsDeliveryAddress(): bool
    {
        return ! $this->is_pickup;
    }
}

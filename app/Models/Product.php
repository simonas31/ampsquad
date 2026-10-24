<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasImageConversions;
use App\Models\Concerns\HasSlug;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

/**
 * Something the public shop sells. Deliberately flat: one price in the
 * shop's single currency (EUR), no variants or stock. Soft deleted rather
 * than removed, so a product that is in a cart (or, later, on an order)
 * never leaves a dangling reference.
 */
class Product extends Model implements HasMedia
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use HasImageConversions, InteractsWithMedia {
        HasImageConversions::registerMediaConversions insteadof InteractsWithMedia;
    }
    use HasSlug;
    use HasTranslations;
    use SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'order' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'excerpt',
        'description',
        'price_cents',
        'is_active',
        'order',
    ];

    /**
     * @var list<string>
     */
    public array $translatable = [
        'name',
        'slug',
        'excerpt',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'is_active' => 'boolean',
            'order' => 'integer',
        ];
    }

    protected function slugSourceAttribute(): string
    {
        return 'name';
    }

    /**
     * Once a product has been on sale its URL is indexed, so renaming it
     * leaves the slug alone; only regenerateSlug() changes it, with a
     * redirect from the old one.
     */
    protected function slugIsLocked(): bool
    {
        return $this->getOriginal('is_active') === true;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured_image')
            ->singleFile()
            ->acceptsMimeTypes(self::$imageMimeTypes);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Soft deleted products are already excluded by SoftDeletes' global
     * scope, so this is everything the shop is allowed to show and sell.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Whether this product can currently be added to, or stay in, a cart.
     */
    public function isPurchasable(): bool
    {
        return $this->is_active && ! $this->trashed();
    }
}

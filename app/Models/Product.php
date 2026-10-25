<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShopSort;
use App\Models\Concerns\HasImageConversions;
use App\Models\Concerns\HasSlug;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

/**
 * Something the public shop sells. Deliberately flat: one price in the
 * shop's single currency (EUR), no variants.
 *
 * Three kinds of data live here, with different owners. The catalog import
 * owns the descriptive fields and the price (see CatalogImporter). The shop
 * owns `stock_quantity` and `is_active`: an import seeds the quantity when it
 * creates a product and never changes it afterwards. Soft deleted rather than
 * removed, so a product that is in a cart (or, later, on an order) never
 * leaves a dangling reference.
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
     * €100,000.00 — far above any real price, and far below the column's
     * unsigned 32-bit limit, so an input slip cannot overflow it.
     */
    public const int MAX_PRICE_CENTS = 10_000_000;

    /**
     * The one currency prices are held and shown in.
     */
    public const string CURRENCY = 'EUR';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'stock_quantity' => 0,
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
        'sku',
        'ean',
        'stock_quantity',
        'image_url',
        'product_category_id',
        'manufacturer_id',
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
            'stock_quantity' => 'integer',
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

    /**
     * Product photographs are public content, so they live on the public
     * disk whatever FILESYSTEM_DISK is. Filament's upload falls back to that
     * setting (the private `local` disk by default) when the collection names
     * no disk, and a private file has no public URL: the shop would answer
     * 403 for every product image.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured_image')
            ->useDisk('public')
            ->singleFile()
            ->acceptsMimeTypes(self::$imageMimeTypes);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
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
     * Words of a search that are looked for; the rest are ignored.
     */
    public const int MAX_SEARCH_TERMS = 5;

    /**
     * Products whose name, SKU or EAN contain every word of $text. Names are
     * compared the way the database's own text columns are, ignoring case
     * and accents, so "kronsteinas" finds "Kronšteinas".
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $text): Builder
    {
        $words = array_slice(array_filter(explode(' ', Str::squish($text))), 0, self::MAX_SEARCH_TERMS);

        foreach ($words as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';

            $query->where(fn (Builder $match) => $match
                ->whereRaw('json_unquote(json_extract(`name`, ?)) collate utf8mb4_unicode_ci like ?', [$this->localePath(), $like])
                ->orWhere('sku', 'like', $like)
                ->orWhere('ean', $word));
        }

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePricedBetween(Builder $query, ?int $minCents, ?int $maxCents): Builder
    {
        return $query
            ->when($minCents !== null, fn (Builder $priced) => $priced->where('price_cents', '>=', $minCents))
            ->when($maxCents !== null, fn (Builder $priced) => $priced->where('price_cents', '<=', $maxCents));
    }

    /**
     * Featured, the default, puts what can be bought first and then goes
     * alphabetically. Every sort ends on the id, so pages never repeat or
     * skip a product whose sort value is shared.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSortedBy(Builder $query, ShopSort $sort): Builder
    {
        match ($sort) {
            ShopSort::Featured => $this->orderedByName($query->orderByRaw('`stock_quantity` = 0')),
            ShopSort::PriceAsc => $query->orderBy('price_cents'),
            ShopSort::PriceDesc => $query->orderByDesc('price_cents'),
            ShopSort::Name => $this->orderedByName($query),
            ShopSort::Newest => $query->orderByDesc('created_at'),
        };

        return $sort === ShopSort::Newest ? $query->orderByDesc('id') : $query->orderBy('id');
    }

    /**
     * Whether this product can currently be added to, or stay in, a cart.
     */
    public function isPurchasable(): bool
    {
        return $this->is_active && ! $this->trashed() && $this->isInStock();
    }

    /**
     * The most of this product one cart line may hold: the cart cap, or
     * what is left in stock when that is less.
     */
    public function maxPurchasableQuantity(): int
    {
        return min(CartItem::MAX_QUANTITY, $this->stock_quantity);
    }

    /**
     * The uploaded photograph if there is one, otherwise the supplier's
     * image link the catalog import saved.
     */
    public function featuredImageUrl(string $conversion): ?string
    {
        return $this->getFirstMediaUrl('featured_image', $conversion) ?: $this->image_url;
    }

    /**
     * By the current language's name, ignoring case and accents.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    private function orderedByName(Builder $query): Builder
    {
        return $query->orderByRaw('json_unquote(json_extract(`name`, ?)) collate utf8mb4_unicode_ci', [$this->localePath()]);
    }

    /**
     * Path of the current language inside a translatable JSON column.
     */
    private function localePath(): string
    {
        return '$."'.app()->getLocale().'"';
    }
}

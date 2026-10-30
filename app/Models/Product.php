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
use InvalidArgumentException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

/**
 * Something the public shop sells. Deliberately flat: one price in the
 * shop's single currency (EUR), no variants.
 *
 * Two prices, always named by whether they include VAT (columns, methods,
 * JSON keys, translation keys), never by "net" or "gross":
 *  - excl. VAT: what the catalog import supplies and the admin types; the
 *    only one stored.
 *  - incl. VAT: what the visitor pays and what the shop shows first.
 * The price incl. VAT is worked out from the one excl. VAT (see
 * priceInclVatCents()) and never stored, so the two cannot disagree. The VAT
 * is the difference between them.
 *
 * Three kinds of data live here, with different owners. The catalog import
 * owns the descriptive fields and the price (see CatalogImporter). The shop
 * owns `stock_quantity`, `is_active` and `is_featured`: an import seeds the
 * quantity when it creates a product and never changes it afterwards, and
 * never touches the other two. After that the stock
 * only moves through the admin panel and through orders (takeStock() and
 * returnStock()). Soft deleted rather than removed, so a product that is in a
 * cart or on an order never leaves a dangling reference.
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
     * €100,000.00 without VAT — far above any real price, and far below the
     * column's unsigned 32-bit limit, so an input slip cannot overflow it.
     */
    public const int MAX_PRICE_EXCL_VAT_CENTS = 10_000_000;

    /**
     * Longest SKU, and the most stock one product can hold: limits of the
     * product itself, shared by the catalog import and the admin panel.
     */
    public const int MAX_SKU_LENGTH = 64;

    public const int MAX_STOCK_QUANTITY = 100_000_000;

    /**
     * At or below this many left, the shop says how many are left.
     */
    public const int LOW_STOCK_THRESHOLD = 5;

    /**
     * The one currency prices are held and shown in.
     */
    public const string CURRENCY = 'EUR';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'is_featured' => false,
        'stock_quantity' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'excerpt',
        'description',
        'price_excl_vat_cents',
        'is_active',
        'is_featured',
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
            'price_excl_vat_cents' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
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

    /**
     * The VAT rate in whole percent (config/shop.php).
     */
    public static function vatRatePercent(): int
    {
        return (int) config('shop.vat_rate');
    }

    /**
     * A price without VAT with the VAT added, to the nearest cent (halves
     * round up). Whole-number arithmetic, so the result is exact.
     */
    public static function addVat(int $exclVatCents): int
    {
        return intdiv($exclVatCents * (100 + self::vatRatePercent()) + 50, 100);
    }

    /**
     * The price with VAT. Shown for one unit and multiplied by the quantity
     * for a cart line, so the lines the visitor sees add up to the total.
     */
    public function priceInclVatCents(): int
    {
        return self::addVat($this->price_excl_vat_cents);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderLines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /**
     * Takes $quantity units out of stock for an order, or none at all.
     * Returns whether it did.
     *
     * One UPDATE decides: it only matches a product that is on sale and has
     * at least $quantity left, so of two buyers of the last unit exactly one
     * gets true, and the stock can never go below zero, whatever the callers
     * read a moment before. The count is never read first and written back.
     * The caller runs it inside the order's transaction and rolls back when
     * it returns false.
     *
     * The product's updated_at is left alone: a sale is not a change to the
     * page, and the sitemap's lastmod follows updated_at.
     */
    public static function takeStock(int $productId, int $quantity): bool
    {
        self::assertWholeUnits($quantity);

        return static::query()
            ->whereKey($productId)
            ->where('is_active', true)
            ->where('stock_quantity', '>=', $quantity)
            ->toBase()
            ->decrement('stock_quantity', $quantity) === 1;
    }

    /**
     * Puts units taken by takeStock() back, for an order that is not going
     * ahead. A product taken off sale or deleted since still gets its stock
     * back. Returns whether the product was found.
     *
     * Not safe to call twice for one order: the caller makes sure it is, by
     * doing it only for the call of Order::transitionTo() that returned true.
     */
    public static function returnStock(int $productId, int $quantity): bool
    {
        self::assertWholeUnits($quantity);

        return static::withTrashed()
            ->whereKey($productId)
            ->toBase()
            ->increment('stock_quantity', $quantity) === 1;
    }

    private static function assertWholeUnits(int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException("Stock moves in whole units of at least 1, got {$quantity}.");
        }
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
     * How many are left when only a few are, otherwise null: the exact stock
     * of a well-stocked product is nobody's business but the shop's.
     */
    public function lowStockCount(): ?int
    {
        return $this->isInStock() && $this->stock_quantity <= self::LOW_STOCK_THRESHOLD
            ? $this->stock_quantity
            : null;
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
     * The products the owner put on the "Popular products" shelf.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Words of a search that are looked for; the rest are ignored.
     */
    public const int MAX_SEARCH_TERMS = 5;

    /**
     * Products whose name, SKU or EAN contain every word of $text. Names are
     * compared the way the database's own text columns are, ignoring case
     * and accents, so "kronsteinas" finds "Kronšteinas". Names are read in
     * $locale, the current language when none is given.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $text, ?string $locale = null): Builder
    {
        $words = array_slice(array_filter(explode(' ', Str::squish($text))), 0, self::MAX_SEARCH_TERMS);

        foreach ($words as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';

            $query->where(fn (Builder $match) => $match
                ->whereRaw('json_unquote(json_extract(`name`, ?)) collate utf8mb4_unicode_ci like ?', [$this->localePath($locale), $like])
                ->orWhere('sku', 'like', $like)
                ->orWhere('ean', $word));
        }

        return $query;
    }

    /**
     * Products whose price with VAT, in cents, lies within the bounds; a
     * null bound is open. Compares the same rounded figure
     * priceInclVatCents() gives, so a product is never shown at a price the
     * filter disagrees with.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePricedBetween(Builder $query, ?int $minInclVatCents, ?int $maxInclVatCents): Builder
    {
        $inclVat = 'floor(`price_excl_vat_cents` * (100 + ?) / 100 + 0.5)';
        $rate = self::vatRatePercent();

        return $query
            ->when($minInclVatCents !== null, fn (Builder $priced) => $priced->whereRaw("{$inclVat} >= ?", [$rate, $minInclVatCents]))
            ->when($maxInclVatCents !== null, fn (Builder $priced) => $priced->whereRaw("{$inclVat} <= ?", [$rate, $maxInclVatCents]));
    }

    /**
     * Availability, the default, puts what can be bought first and then goes
     * alphabetically. Every sort ends on the id, so pages never repeat or
     * skip a product whose sort value is shared.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSortedBy(Builder $query, ShopSort $sort): Builder
    {
        match ($sort) {
            ShopSort::Availability => $query->orderByRaw('`stock_quantity` = 0')->orderedByName(),
            ShopSort::PriceAsc => $query->orderBy('price_excl_vat_cents'),
            ShopSort::PriceDesc => $query->orderByDesc('price_excl_vat_cents'),
            ShopSort::Name => $query->orderedByName(),
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
     * By the name in $locale (the current language when none is given),
     * ignoring case and accents.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrderedByName(Builder $query, string $direction = 'asc', ?string $locale = null): Builder
    {
        return $query->orderByRaw(
            'json_unquote(json_extract(`name`, ?)) collate utf8mb4_unicode_ci '.(strtolower($direction) === 'desc' ? 'desc' : 'asc'),
            [$this->localePath($locale)],
        );
    }

    /**
     * Path of a language ($locale, or the current one) inside a translatable
     * JSON column.
     */
    private function localePath(?string $locale = null): string
    {
        return '$."'.($locale ?? app()->getLocale()).'"';
    }
}

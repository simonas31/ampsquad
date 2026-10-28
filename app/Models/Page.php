<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasImageConversions;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\HasTranslatableBlocks;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class Page extends Model implements HasMedia
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    use HasImageConversions, InteractsWithMedia {
        HasImageConversions::registerMediaConversions insteadof InteractsWithMedia;
    }
    use HasSeo;
    use HasSlug;
    use HasTranslatableBlocks;
    use HasTranslations;

    /**
     * Seeded system pages that hardcoded nav/footer links point at —
     * guarded from accidental deletion in the admin. Admins can still add
     * further pages freely (e.g. FAQ, Careers); this list only protects
     * the ones the app itself assumes exist.
     */
    public const PROTECTED_KEYS = ['about', 'privacy-policy', 'terms-and-conditions'];

    /**
     * Pages the fixed navigation always links from the header and footer,
     * so their menu checkboxes are locked in the admin.
     */
    public const ALWAYS_IN_MENUS_KEYS = ['about'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'title',
        'blocks',
        'show_in_header',
        'show_in_footer',
    ];

    /**
     * @var list<string>
     */
    public array $translatable = [
        'title',
        'slug',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'show_in_header' => 'boolean',
            'show_in_footer' => 'boolean',
        ];
    }

    /**
     * Public site content, so it lives on the public disk whatever
     * FILESYSTEM_DISK is. Filament's upload falls back to that setting (the
     * private `local` disk by default) when a collection names no disk, and a
     * private file has no public URL: nginx answers 404 for every image.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured_image')
            ->useDisk('public')
            ->singleFile()
            ->acceptsMimeTypes(self::$imageMimeTypes);

        $this->addMediaCollection('content_blocks')
            ->useDisk('public')
            ->acceptsMimeTypes(self::$imageMimeTypes);
    }

    /**
     * Pages resolve through the `/{slug}` catch-all, so a slug equal to a
     * literal route segment (or a locale prefix) would be shadowed by it.
     *
     * @return list<string>
     */
    protected function reservedSlugs(string $locale): array
    {
        $locales = array_keys(LaravelLocalization::getSupportedLocales());

        return [
            'projects',
            'contact',
            'shop',
            'cart',
            'admin',
            ...array_map(fn (string $code): string => trans('routes.price_calculator', [], $code), $locales),
            ...$locales,
        ];
    }

    /**
     * Pages have no draft state: every page is a live URL from creation.
     */
    protected function slugIsLocked(): bool
    {
        return true;
    }

    /**
     * Pages that have a slug in the current locale, so they have a URL to
     * link to. A page written in one language only has none in the other.
     *
     * @param  Builder<Page>  $query
     */
    public function scopeWithSlugInCurrentLocale(Builder $query): void
    {
        $column = 'slug_'.app()->getLocale();

        $query->whereNotNull($column)->where($column, '!=', '');
    }

    public function isAlwaysInMenus(): bool
    {
        return in_array($this->key, self::ALWAYS_IN_MENUS_KEYS, true);
    }

    public function isProtected(): bool
    {
        return in_array($this->key, self::PROTECTED_KEYS, true);
    }
}

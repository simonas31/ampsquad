<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasImageConversions;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\HasTranslatableBlocks;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'slug',
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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured_image')
            ->singleFile()
            ->acceptsMimeTypes(self::$imageMimeTypes);

        $this->addMediaCollection('content_blocks')
            ->acceptsMimeTypes(self::$imageMimeTypes);
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

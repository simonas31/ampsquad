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
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'title',
        'slug',
        'blocks',
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

    public function isProtected(): bool
    {
        return in_array($this->key, self::PROTECTED_KEYS, true);
    }
}

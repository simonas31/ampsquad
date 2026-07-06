<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Models\Concerns\HasImageConversions;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\HasTranslatableBlocks;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

/**
 * A completed or ongoing electrical engineering project (category/tag-driven,
 * block-content body, gallery/featured-image). A future content type that
 * doesn't fit this shape (e.g. a "Team Member" or "FAQ" entry) should get its
 * own table the way `pages` did, rather than growing this one with more
 * nullable columns.
 */
class Project extends Model implements HasMedia
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use HasImageConversions, InteractsWithMedia {
        HasImageConversions::registerMediaConversions insteadof InteractsWithMedia;
    }
    use HasSeo;
    use HasTranslatableBlocks;
    use HasTranslations;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'author_id',
        'title',
        'slug',
        'excerpt',
        'blocks',
        'status',
        'published_at',
        'is_featured',
        'order',
        'location',
        'client_name',
        'completed_at',
    ];

    /**
     * @var list<string>
     */
    public array $translatable = [
        'title',
        'slug',
        'excerpt',
        'location',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'blocks' => 'array',
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'order' => 'integer',
            'completed_at' => 'date',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured_image')
            ->singleFile()
            ->acceptsMimeTypes(self::$imageMimeTypes);

        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(self::$imageMimeTypes);

        $this->addMediaCollection('content_blocks')
            ->acceptsMimeTypes(self::$imageMimeTypes);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ProjectStatus::Published)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }
}

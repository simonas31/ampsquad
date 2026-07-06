<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VideoType;
use App\Models\Concerns\HasImageConversions;
use Database\Factories\VideoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

class Video extends Model implements HasMedia
{
    /** @use HasFactory<VideoFactory> */
    use HasFactory;

    use HasImageConversions;
    use HasTranslations;
    use InteractsWithMedia;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'type',
        'embed_url',
        'order',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    public array $translatable = [
        'title',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VideoType::class,
            'order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('video')
            ->singleFile()
            ->acceptsMimeTypes(['video/mp4', 'video/webm', 'video/quicktime']);

        $this->addMediaCollection('poster')
            ->singleFile()
            ->acceptsMimeTypes(self::$imageMimeTypes);
    }

    /**
     * Overrides HasImageConversions' version to scope conversions to the
     * poster collection only — running image resize/WebP conversions
     * against an actual video file would fail.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')
            ->width(400)
            ->height(300)
            ->format('webp')
            ->performOnCollections('poster')
            ->queued();

        $this->addMediaConversion('large')
            ->width(1600)
            ->format('webp')
            ->withResponsiveImages()
            ->performOnCollections('poster')
            ->queued();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

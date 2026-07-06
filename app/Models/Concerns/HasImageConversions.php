<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Queued thumbnail/responsive/WebP generation shared by every model with
 * image media collections, so Project/Page/Video don't each redefine the
 * same conversions. Must be combined with Spatie's InteractsWithMedia,
 * which is what actually provides addMediaConversion().
 */
trait HasImageConversions
{
    /**
     * @var list<string>
     */
    public static array $imageMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')
            ->width(400)
            ->height(300)
            ->format('webp')
            ->queued();

        $this->addMediaConversion('large')
            ->width(1600)
            ->format('webp')
            ->withResponsiveImages()
            ->queued();
    }
}

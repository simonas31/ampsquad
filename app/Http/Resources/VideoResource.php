<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Video
 */
class VideoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type->value,
            'embedUrl' => $this->embed_url,
            'videoUrl' => $this->getFirstMediaUrl('video') ?: null,
            'posterUrl' => $this->getFirstMediaUrl('poster', 'large') ?: null,
        ];
    }
}

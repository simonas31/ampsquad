<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ContentType;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shared by Project and Article public pages — same underlying model (see
 * App\Models\Project's docblock), only the route name differs.
 *
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $routePrefix = $this->content_type === ContentType::Project ? 'projects' : 'articles';

        return [
            'id' => $this->id,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'status' => $this->status->value,
            'publishedAt' => $this->published_at?->toIso8601String(),
            'isFeatured' => $this->is_featured,
            'location' => $this->location,
            'clientName' => $this->client_name,
            'completedAt' => $this->completed_at?->toDateString(),
            'url' => route("{$routePrefix}.show", $this->slug),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'author' => $this->whenLoaded('author', fn () => $this->author?->name),
            'featuredImageUrl' => $this->getFirstMediaUrl('featured_image', 'large') ?: null,
            'featuredImageThumbUrl' => $this->getFirstMediaUrl('featured_image', 'thumbnail') ?: null,
            'gallery' => $this->getMedia('gallery')->map(fn ($media) => [
                'large' => $media->getUrl('large'),
                'thumbnail' => $media->getUrl('thumbnail'),
            ]),
            'blocks' => $this->when($request->routeIs("{$routePrefix}.show"), fn () => $this->resolvedBlocks()),
        ];
    }
}

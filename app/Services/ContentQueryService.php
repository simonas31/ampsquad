<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContentType;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Shared filtered/eager-loaded queries for the Project/Article public
 * listing and detail pages — both content types live on the same table
 * (see Project's docblock), so the query shape only differs by which
 * ContentType is asked for.
 */
class ContentQueryService
{
    public function paginatePublished(
        ContentType $type,
        ?int $categoryId = null,
        ?int $tagId = null,
        int $perPage = 12,
    ): LengthAwarePaginator {
        return Project::query()
            ->ofType($type)
            ->published()
            ->with(['category', 'tags'])
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->when($tagId, fn ($query) => $query->whereHas(
                'tags',
                fn ($query) => $query->where('tags.id', $tagId),
            ))
            ->orderByDesc('published_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findPublishedBySlug(ContentType $type, string $slug): Project
    {
        $slugColumn = 'slug_'.app()->getLocale();

        return Project::query()
            ->ofType($type)
            ->published()
            ->with(['category', 'tags', 'author'])
            ->where($slugColumn, $slug)
            ->firstOrFail();
    }
}

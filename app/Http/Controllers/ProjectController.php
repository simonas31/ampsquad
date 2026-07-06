<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProjectResource;
use App\Models\Category;
use App\Models\Project;
use App\Support\Seo\Breadcrumbs;
use App\Support\Seo\SeoData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $categoryId = $request->integer('category') ?: null;
        $tagId = $request->integer('tag') ?: null;

        $projects = Project::query()
            ->published()
            ->with(['category', 'tags'])
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->when($tagId, fn ($query) => $query->whereHas(
                'tags',
                fn ($query) => $query->where('tags.id', $tagId),
            ))
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        $breadcrumbs = Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.projects'), 'url' => null],
        ]);

        return Inertia::render('Projects/Index', [
            'projects' => ProjectResource::collection($projects),
            'categories' => CategoryResource::collection(
                Category::query()->orderBy('order')->get(),
            ),
            'breadcrumbs' => $breadcrumbs->toArray(),
            'seo' => SeoData::make(
                pageTitle: __('nav.projects'),
                jsonLd: [$breadcrumbs->jsonLd()],
            ),
        ]);
    }

    public function show(string $slug): Response
    {
        $slugColumn = 'slug_'.app()->getLocale();

        $project = Project::query()
            ->published()
            ->with(['category', 'tags', 'author'])
            ->where($slugColumn, $slug)
            ->firstOrFail();

        $breadcrumbs = Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.projects'), 'url' => route('projects.index')],
            ['label' => $project->title, 'url' => null],
        ]);

        return Inertia::render('Projects/Show', [
            'project' => new ProjectResource($project),
            'breadcrumbs' => $breadcrumbs->toArray(),
            'seo' => SeoData::make(
                pageTitle: $project->title,
                description: $project->excerpt,
                ogImage: $project->getFirstMediaUrl('featured_image', 'large') ?: null,
                ogType: 'article',
                jsonLd: [$breadcrumbs->jsonLd(), $this->creativeWorkJsonLd($project)],
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function creativeWorkJsonLd(Project $project): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'CreativeWork',
            'name' => $project->title,
            'description' => $project->excerpt,
            'image' => $project->getFirstMediaUrl('featured_image', 'large') ?: null,
            'datePublished' => $project->published_at?->toIso8601String(),
            'locationCreated' => $project->location,
            'creator' => [
                '@type' => 'Organization',
                'name' => config('app.name'),
            ],
        ]);
    }
}

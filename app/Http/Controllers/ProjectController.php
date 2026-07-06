<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ContentType;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProjectResource;
use App\Models\Category;
use App\Services\ContentQueryService;
use App\Support\Seo\Breadcrumbs;
use App\Support\Seo\SeoData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(private readonly ContentQueryService $contentQuery) {}

    public function index(Request $request): Response
    {
        $projects = $this->contentQuery->paginatePublished(
            type: ContentType::Project,
            categoryId: $request->integer('category') ?: null,
            tagId: $request->integer('tag') ?: null,
        );

        $breadcrumbs = Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.projects'), 'url' => null],
        ]);

        return Inertia::render('Projects/Index', [
            'projects' => ProjectResource::collection($projects),
            'categories' => CategoryResource::collection(
                Category::query()->whereIn('applies_to', [ContentType::Project, null])->orderBy('order')->get(),
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
        $project = $this->contentQuery->findPublishedBySlug(ContentType::Project, $slug);

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
                jsonLd: [$breadcrumbs->jsonLd()],
            ),
        ]);
    }
}

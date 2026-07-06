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

class ArticleController extends Controller
{
    public function __construct(private readonly ContentQueryService $contentQuery) {}

    public function index(Request $request): Response
    {
        $articles = $this->contentQuery->paginatePublished(
            type: ContentType::Article,
            categoryId: $request->integer('category') ?: null,
            tagId: $request->integer('tag') ?: null,
        );

        $breadcrumbs = Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.articles'), 'url' => null],
        ]);

        return Inertia::render('Articles/Index', [
            'articles' => ProjectResource::collection($articles),
            'categories' => CategoryResource::collection(
                Category::query()->whereIn('applies_to', [ContentType::Article, null])->orderBy('order')->get(),
            ),
            'breadcrumbs' => $breadcrumbs->toArray(),
            'seo' => SeoData::make(
                pageTitle: __('nav.articles'),
                jsonLd: [$breadcrumbs->jsonLd()],
            ),
        ]);
    }

    public function show(string $slug): Response
    {
        $article = $this->contentQuery->findPublishedBySlug(ContentType::Article, $slug);

        $breadcrumbs = Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.articles'), 'url' => route('articles.index')],
            ['label' => $article->title, 'url' => null],
        ]);

        return Inertia::render('Articles/Show', [
            'article' => new ProjectResource($article),
            'breadcrumbs' => $breadcrumbs->toArray(),
            'seo' => SeoData::make(
                pageTitle: $article->title,
                description: $article->excerpt,
                ogImage: $article->getFirstMediaUrl('featured_image', 'large') ?: null,
                ogType: 'article',
                jsonLd: [$breadcrumbs->jsonLd()],
            ),
        ]);
    }
}

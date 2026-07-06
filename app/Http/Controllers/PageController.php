<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\PageResource;
use App\Models\Page;
use App\Support\Seo\Breadcrumbs;
use App\Support\Seo\SeoData;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function show(string $slug): Response
    {
        $slugColumn = 'slug_'.app()->getLocale();

        $page = Page::query()->where($slugColumn, $slug)->firstOrFail();

        $breadcrumbs = Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => $page->title, 'url' => null],
        ]);

        return Inertia::render('Pages/Show', [
            'page' => new PageResource($page),
            'breadcrumbs' => $breadcrumbs->toArray(),
            'seo' => SeoData::make(
                pageTitle: $page->title,
                ogImage: $page->getFirstMediaUrl('featured_image', 'large') ?: null,
                jsonLd: [$breadcrumbs->jsonLd()],
            ),
        ]);
    }
}

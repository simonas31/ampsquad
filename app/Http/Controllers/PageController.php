<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\PageResource;
use App\Models\Page;
use App\Support\Seo\Breadcrumbs;
use App\Support\Seo\LocalizedUrls;
use App\Support\Seo\SeoData;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function show(string $slug): Response|RedirectResponse
    {
        $slugColumn = 'slug_'.app()->getLocale();

        $page = Page::query()->where($slugColumn, $slug)->first();

        if ($page === null) {
            $moved = Page::query()->movedFromSlug($slug)->firstOrFail();

            return redirect()->route('pages.show', $moved->slug, 301);
        }

        LocalizedUrls::useSlugs($page->getTranslations('slug'));

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

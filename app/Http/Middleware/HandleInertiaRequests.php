<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'locale' => [
                'current' => app()->getLocale(),
                'available' => collect(LaravelLocalization::getSupportedLocales())
                    ->map(fn (array $properties, string $code) => [
                        'code' => $code,
                        'name' => $properties['native'],
                        'url' => LaravelLocalization::getLocalizedURL($code, null, [], true),
                    ])
                    ->values(),
            ],
            'navigation' => $this->navigationLinks(),
        ];
    }

    /**
     * Main nav links, resolved from named routes as they come online phase
     * by phase — an entry is silently omitted until its route exists, so
     * the header grows automatically instead of needing a manual update.
     *
     * @return array<int, array{labelKey: string, url: string}>
     */
    private function navigationLinks(): array
    {
        return collect([
            ['name' => 'home', 'params' => [], 'labelKey' => 'nav.home'],
            ['name' => 'projects.index', 'params' => [], 'labelKey' => 'nav.projects'],
            ['name' => 'contact', 'params' => [], 'labelKey' => 'nav.contact'],
        ])
            ->filter(fn (array $link) => Route::has($link['name']))
            ->map(fn (array $link) => [
                'labelKey' => $link['labelKey'],
                'url' => route($link['name'], $link['params']),
            ])
            ->push($this->aboutPageLink())
            ->filter()
            ->values()
            ->all();
    }

    /**
     * pages.show's route parameter is the page's current-locale slug, not
     * its stable key — "about" only happens to match the English slug, not
     * the Lithuanian one, so this needs a real lookup rather than a
     * hardcoded route() call like the entries above.
     *
     * @return array{labelKey: string, url: string}|null
     */
    private function aboutPageLink(): ?array
    {
        if (! Route::has('pages.show')) {
            return null;
        }

        $about = Page::query()->where('key', 'about')->first();

        if (! $about) {
            return null;
        }

        return [
            'labelKey' => 'nav.about',
            'url' => route('pages.show', ['slug' => $about->slug]),
        ];
    }
}

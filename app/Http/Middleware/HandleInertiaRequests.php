<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Page;
use App\Services\CartService;
use App\Settings\GeneralSettings;
use App\Settings\ShopSettings;
use App\Support\Seo\LocalizedUrls;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
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
     * @var array<int, array{labelKey: string, url: string}>|null
     */
    private ?array $navigation = null;

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
            'locale' => fn () => [
                'current' => app()->getLocale(),
                'available' => collect(LaravelLocalization::getSupportedLocales())
                    ->map(fn (array $properties, string $code) => [
                        'code' => $code,
                        'name' => $properties['native'],
                        'url' => LocalizedUrls::forLocale($code) ?? LocalizedUrls::homeForLocale($code),
                    ])
                    ->values(),
            ],
            'navigation' => fn () => $this->navigation(),
            'pageLinks' => fn () => $this->pageLinks($this->navigation()),
            'site' => fn () => $this->siteContact(),
            'cartSummary' => fn () => $this->cartSummary($request),
        ];
    }

    /**
     * The main navigation, worked out once per request however many props
     * need it, and not at all for a partial reload that asks for neither.
     *
     * @return array<int, array{labelKey: string, url: string}>
     */
    private function navigation(): array
    {
        return $this->navigation ??= $this->navigationLinks();
    }

    /**
     * Company contact details and social profiles shown in the header,
     * footer and contact page on every request, so they're shared once here
     * instead of each controller passing them separately.
     *
     * The logo URLs are null until a logo is uploaded in the admin panel; the
     * front end then falls back to the bundled artwork.
     *
     * @return array{contact: array{email: string, phone: string, address: string}, social: array{facebook: ?string, instagram: ?string, linkedin: ?string}, logo: array{light: ?string, dark: ?string}}
     */
    private function siteContact(): array
    {
        $general = app(GeneralSettings::class);

        return [
            'contact' => [
                'email' => $general->email,
                'phone' => $general->phone,
                'address' => $general->address,
            ],
            'social' => [
                'facebook' => $general->facebookUrl,
                'instagram' => $general->instagramUrl,
                'linkedin' => $general->linkedinUrl,
            ],
            'logo' => [
                'light' => $this->publicUrl($general->logo),
                'dark' => $this->publicUrl($general->logoDark),
            ],
        ];
    }

    private function publicUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    /**
     * Whether the public shop is switched on (and exists in this build).
     * The shop link, cart badge and every shop route depend on it.
     */
    private function shopEnabled(): bool
    {
        return Route::has('shop.index') && app(ShopSettings::class)->enabled;
    }

    /**
     * What the header's cart link needs: where the cart is, where lines are
     * added, and how many units are in it. Null while the shop is off, so
     * the link disappears with it. The URLs are relative because the page
     * sends requests to them: an absolute one built from APP_URL would be
     * cross-origin (and blocked) whenever the site is opened on another host.
     *
     * Shared as `cartSummary`, never `cart`: the cart page's own `cart` prop
     * (the full cart) would replace a shared one of that name, leaving the
     * header link without its URL on that page.
     *
     * @return array{url: string, itemsUrl: string, count: int}|null
     */
    private function cartSummary(Request $request): ?array
    {
        if (! $this->shopEnabled()) {
            return null;
        }

        return [
            'url' => route('cart.show', absolute: false),
            'itemsUrl' => route('cart.items.store', absolute: false),
            'count' => app(CartService::class)->totalQuantity($request),
        ];
    }

    /**
     * Admin-managed pages flagged for the header and/or footer, in creation
     * order. They are listed after the fixed navigation, which already links
     * some pages (About), so a page whose URL is already there is skipped
     * rather than shown twice.
     *
     * @param  array<int, array{labelKey: string, url: string}>  $navigation
     * @return array{header: list<array{title: string, url: string}>, footer: list<array{title: string, url: string}>}
     */
    private function pageLinks(array $navigation): array
    {
        $links = ['header' => [], 'footer' => []];

        if (! Route::has('pages.show')) {
            return $links;
        }

        $fixedUrls = array_column($navigation, 'url');

        $pages = Page::query()
            ->where(fn ($query) => $query->where('show_in_header', true)->orWhere('show_in_footer', true))
            ->orderBy('id')
            ->get();

        foreach ($pages as $page) {
            $link = ['title' => $page->title, 'url' => route('pages.show', ['slug' => $page->slug])];

            if (in_array($link['url'], $fixedUrls, true)) {
                continue;
            }

            if ($page->show_in_header) {
                $links['header'][] = $link;
            }

            if ($page->show_in_footer) {
                $links['footer'][] = $link;
            }
        }

        return $links;
    }

    /**
     * Main nav links in display order. A named-route entry is silently
     * omitted until its route exists, and the About entry until its page
     * does, so the header grows automatically instead of needing a manual
     * update.
     *
     * @return array<int, array{labelKey: string, url: string}>
     */
    private function navigationLinks(): array
    {
        return collect([
            $this->routeLink('home', 'nav.home'),
            $this->aboutPageLink(),
            $this->routeLink('projects.index', 'nav.projects'),
            $this->routeLink('price-calculator', 'nav.priceCalculator'),
            $this->shopEnabled() ? $this->routeLink('shop.index', 'nav.shop') : null,
            $this->routeLink('contact', 'nav.contact'),
        ])
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{labelKey: string, url: string}|null
     */
    private function routeLink(string $routeName, string $labelKey): ?array
    {
        if (! Route::has($routeName)) {
            return null;
        }

        return ['labelKey' => $labelKey, 'url' => route($routeName)];
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

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\Product;
use App\Models\Project;
use App\Settings\ShopSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

#[Signature('app:generate-sitemap')]
#[Description('Regenerate public/sitemap.xml for all locales.')]
class GenerateSitemap extends Command
{
    /**
     * @var array<int, string>
     */
    private array $locales;

    public function handle(): void
    {
        $this->locales = collect(LaravelLocalization::getSupportedLocales())->keys()->all();

        $sitemap = Sitemap::create();

        $this->addIfPresent($sitemap, $this->urlForAllLocales(fn (string $locale) => $this->localizedUrl($locale, '/'), priority: 1.0));
        $this->addIfPresent($sitemap, $this->urlForAllLocales(fn (string $locale) => $this->localizedUrl($locale, '/projects'), priority: 0.8));
        $this->addIfPresent($sitemap, $this->urlForAllLocales(
            fn (string $locale) => $this->localizedUrl($locale, '/'.trans('routes.price_calculator', [], $locale)),
            priority: 0.7,
        ));

        // Only while the shop is on, since its URLs 404 otherwise. The sitemap
        // is regenerated daily and on deploy, so a switch shows up by then.
        if (app(ShopSettings::class)->enabled) {
            $this->addIfPresent($sitemap, $this->urlForAllLocales(fn (string $locale) => $this->localizedUrl($locale, '/shop'), priority: 0.8));

            Product::query()
                ->active()
                ->each(function (Product $product) use ($sitemap): void {
                    $this->addIfPresent($sitemap, $this->urlForAllLocales(
                        fn (string $locale) => $this->localizedSlugUrl($locale, '/shop/', $product->getTranslation('slug', $locale, false)),
                        priority: 0.6,
                        lastModified: $product->updated_at,
                    ));
                });
        }

        Project::query()
            ->published()
            ->each(function (Project $project) use ($sitemap): void {
                $this->addIfPresent($sitemap, $this->urlForAllLocales(
                    fn (string $locale) => $this->localizedSlugUrl($locale, '/projects/', $project->getTranslation('slug', $locale, false)),
                    priority: 0.6,
                    lastModified: $project->updated_at,
                ));
            });

        Page::query()
            ->each(function (Page $page) use ($sitemap): void {
                $this->addIfPresent($sitemap, $this->urlForAllLocales(
                    fn (string $locale) => $this->localizedSlugUrl($locale, '/', $page->getTranslation('slug', $locale, false)),
                    priority: 0.5,
                    lastModified: $page->updated_at,
                ));
            });

        $sitemap->writeToFile(public_path('sitemap.xml'));

        $this->components->info('Sitemap written to public/sitemap.xml with '.count($sitemap->getTags()).' URLs.');
    }

    /**
     * A null from $urlForLocale means the page has no counterpart in that
     * locale, so it is left out of the alternates rather than advertised.
     *
     * @param  \Closure(string): ?string  $urlForLocale
     */
    private function urlForAllLocales(\Closure $urlForLocale, float $priority, ?Carbon $lastModified = null): ?Url
    {
        $urls = collect($this->locales)
            ->mapWithKeys(fn (string $locale) => [$locale => $urlForLocale($locale)])
            ->filter();

        $primary = $urls->get(LaravelLocalization::getDefaultLocale()) ?? $urls->first();

        if ($primary === null) {
            return null;
        }

        $url = Url::create($primary)->setPriority($priority);

        if ($lastModified !== null) {
            $url->setLastModificationDate($lastModified);
        }

        foreach ($urls as $locale => $localeUrl) {
            $url->addAlternate($localeUrl, $locale);
        }

        return $url;
    }

    private function addIfPresent(Sitemap $sitemap, ?Url $url): void
    {
        if ($url !== null) {
            $sitemap->add($url);
        }
    }

    private function localizedSlugUrl(string $locale, string $prefix, ?string $slug): ?string
    {
        return $slug === null || $slug === '' ? null : $this->localizedUrl($locale, $prefix.$slug);
    }

    /**
     * Mirrors SeoData::make()'s canonical-URL logic: the default locale's
     * prefix is stripped when hideDefaultLocaleInURL is enabled, so the
     * sitemap doesn't advertise a "/lt/..." URL that would 404-redirect.
     */
    private function localizedUrl(string $locale, string $path): string
    {
        $isDefaultHidden = $locale === LaravelLocalization::getDefaultLocale()
            && config('laravellocalization.hideDefaultLocaleInURL');

        $prefix = $isDefaultHidden ? '' : "/{$locale}";

        return rtrim(url($prefix.$path), '/');
    }
}

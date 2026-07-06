<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\Project;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
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

        $sitemap->add($this->urlForAllLocales(fn (string $locale) => $this->localizedUrl($locale, '/'), priority: 1.0));
        $sitemap->add($this->urlForAllLocales(fn (string $locale) => $this->localizedUrl($locale, '/projects'), priority: 0.8));

        Project::query()
            ->published()
            ->each(function (Project $project) use ($sitemap): void {
                $sitemap->add($this->urlForAllLocales(
                    fn (string $locale) => $this->localizedUrl($locale, '/projects/'.$project->getTranslation('slug', $locale)),
                    priority: 0.6,
                ));
            });

        Page::query()
            ->each(function (Page $page) use ($sitemap): void {
                $sitemap->add($this->urlForAllLocales(
                    fn (string $locale) => $this->localizedUrl($locale, '/'.$page->getTranslation('slug', $locale)),
                    priority: 0.5,
                ));
            });

        $sitemap->writeToFile(public_path('sitemap.xml'));
        $this->writeRobotsTxt();

        $this->components->info('Sitemap written to public/sitemap.xml with '.count($sitemap->getTags()).' URLs.');
    }

    /**
     * Regenerated alongside the sitemap (rather than committed as a static
     * file) so its Sitemap: line always reflects the current environment's
     * APP_URL instead of going stale across environments.
     */
    private function writeRobotsTxt(): void
    {
        file_put_contents(public_path('robots.txt'), implode("\n", [
            'User-agent: *',
            'Disallow: /admin',
            '',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ]));
    }

    private function urlForAllLocales(\Closure $urlForLocale, float $priority): Url
    {
        $defaultLocale = LaravelLocalization::getDefaultLocale();

        $url = Url::create($urlForLocale($defaultLocale))->setPriority($priority);

        foreach ($this->locales as $locale) {
            $url->addAlternate($urlForLocale($locale), $locale);
        }

        return $url;
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

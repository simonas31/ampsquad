<?php

declare(strict_types=1);

namespace App\Support\Seo;

use Illuminate\Support\Str;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/**
 * URL of the current page in each locale. Routes whose `{slug}` parameter is
 * a translated model slug (project and page detail pages) register that
 * model's slugs per locale via useSlugs(); without it getLocalizedURL() would
 * reuse the current locale's slug, which 404s in the other locale.
 */
class LocalizedUrls
{
    private const string REQUEST_ATTRIBUTE = 'localized_slugs';

    /**
     * @param  array<string, string>  $slugsByLocale
     */
    public static function useSlugs(array $slugsByLocale): void
    {
        request()->attributes->set(self::REQUEST_ATTRIBUTE, $slugsByLocale);
    }

    /**
     * Null when the current page has no counterpart in $locale.
     */
    public static function forLocale(string $locale): ?string
    {
        $url = LaravelLocalization::getLocalizedURL($locale, request()->fullUrl(), [], true);

        /** @var array<string, string>|null $slugs */
        $slugs = request()->attributes->get(self::REQUEST_ATTRIBUTE);

        if ($slugs === null) {
            return $url;
        }

        $slug = $slugs[$locale] ?? null;

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        [$path, $query] = array_pad(explode('?', $url, 2), 2, null);
        $path = Str::replaceLast('/'.request()->route('slug'), '/'.$slug, $path);

        return $query === null ? $path : $path.'?'.$query;
    }

    public static function homeForLocale(string $locale): string
    {
        return LaravelLocalization::getLocalizedURL($locale, url('/'), [], true);
    }
}

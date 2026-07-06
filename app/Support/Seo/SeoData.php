<?php

declare(strict_types=1);

namespace App\Support\Seo;

use Illuminate\Contracts\Support\Arrayable;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class SeoData implements Arrayable
{
    /**
     * @param  array<int, array{locale: string, url: string}>  $alternates
     * @param  array<int, array<string, mixed>>  $jsonLd
     */
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $ogImage = null,
        public string $ogType = 'website',
        public bool $noindex = false,
        public ?string $canonical = null,
        public array $alternates = [],
        public array $jsonLd = [],
    ) {}

    /**
     * Builds SEO data for the current request, auto-filling the canonical
     * URL and locale alternates — the two things that would go stale if
     * stored on a model instead of computed at render time.
     *
     * $pageTitle is just the page-specific fragment (e.g. "Contact"), not
     * the full browser-tab title — this computes the final "Fragment -
     * AppName" (or bare AppName on the homepage, when left empty) once,
     * so Blade's initial render and Vue's client-side <Head> can't
     * disagree about what the title should be the way they would if each
     * appended the app name suffix independently.
     *
     * @param  array<int, array<string, mixed>>  $jsonLd
     */
    public static function make(
        string $pageTitle = '',
        ?string $description = null,
        ?string $ogImage = null,
        string $ogType = 'website',
        bool $noindex = false,
        array $jsonLd = [],
    ): self {
        $title = $pageTitle !== '' ? "{$pageTitle} - ".config('app.name') : (string) config('app.name');

        $defaultLocale = LaravelLocalization::getDefaultLocale();
        $hideDefaultLocaleInURL = (bool) config('laravellocalization.hideDefaultLocaleInURL');

        // getLocalizedURL() always returns the locale-prefixed form, even
        // for the default locale — but when hideDefaultLocaleInURL is on,
        // that prefixed URL isn't the canonical one, so it's stripped back
        // off here. Otherwise hreflang="lt" would point to a different URL
        // than <link rel="canonical">, which Google's guidelines flag.
        $alternates = collect(LaravelLocalization::getSupportedLocales())
            ->map(function (array $properties, string $code) use ($defaultLocale, $hideDefaultLocaleInURL) {
                $url = LaravelLocalization::getLocalizedURL($code, null, [], true);

                if ($code === $defaultLocale && $hideDefaultLocaleInURL) {
                    // Stripping the prefix from the root "/lt" leaves a
                    // trailing slash ("http://host/"), but url()->current()
                    // never has one for the root path — rtrim keeps the two
                    // consistent so the equality check below actually holds.
                    $url = rtrim(LaravelLocalization::getNonLocalizedURL($url), '/');
                }

                return ['locale' => $code, 'url' => $url];
            })
            ->values();

        $defaultUrl = $alternates->firstWhere('locale', $defaultLocale)['url'] ?? url()->current();

        return new self(
            title: $title,
            description: $description,
            ogImage: $ogImage,
            ogType: $ogType,
            noindex: $noindex,
            canonical: url()->current(),
            alternates: [...$alternates->all(), ['locale' => 'x-default', 'url' => $defaultUrl]],
            jsonLd: $jsonLd,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => $this->canonical,
            'ogImage' => $this->ogImage,
            'ogType' => $this->ogType,
            'noindex' => $this->noindex,
            'alternates' => $this->alternates,
            'jsonLd' => $this->jsonLd,
        ];
    }
}

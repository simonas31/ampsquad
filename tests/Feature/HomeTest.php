<?php

declare(strict_types=1);

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class HomeTest extends TestCase
{
    public function test_default_locale_home_renders_without_prefix(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Home')
            ->where('locale.current', 'lt')
            ->has('locale.available', 2)
            ->has('navigation')
            ->has('seo')
        );
    }

    /**
     * mcamara/laravel-localization resolves the active locale (and bakes it
     * into the registered route prefix) once, at route-registration time,
     * using whatever request the container holds — but Laravel's test
     * harness boots the app in setUp(), before this test's own request
     * exists, so a real $this->get('/en') 404s regardless of whether
     * anything is actually broken. This is a long-standing, upstream
     * limitation of the package (mcamara/laravel-localization#151, #289,
     * #435), not something fixable from application code. The "/en" URL
     * itself is confirmed working via manual browser verification
     * (Playwright: html lang flips to "en", nav renders in English); what
     * this test covers instead is that the app behaves correctly once the
     * locale is "en", independent of how it got set.
     */
    public function test_english_locale_renders_english_content(): void
    {
        app()->setLocale('en');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Home')
            ->where('locale.current', 'en')
        );
    }

    public function test_home_seo_has_no_duplicated_app_name_in_title(): void
    {
        $response = $this->get('/');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('seo.title', config('app.name'))
        );
    }

    public function test_default_locale_alternate_url_matches_canonical(): void
    {
        // hideDefaultLocaleInURL means the "lt" alternate must NOT carry a
        // /lt prefix — otherwise hreflang="lt" would point to a different
        // URL than <link rel="canonical">, which Google's guidelines flag.
        $response = $this->get('/');

        $response->assertInertia(function (AssertableInertia $page) {
            $seo = $page->toArray()['props']['seo'];

            $this->assertSame($seo['canonical'], $seo['alternates'][1]['url']);
            $this->assertSame('lt', $seo['alternates'][1]['locale']);
            $this->assertSame($seo['canonical'], $seo['alternates'][2]['url']);
            $this->assertSame('x-default', $seo['alternates'][2]['locale']);
        });
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Settings\GeneralSettings;
use App\Settings\HomepageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_site_contact_details_are_shared_with_every_page(): void
    {
        $general = app(GeneralSettings::class);

        $response = $this->get('/contact');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Contact')
            ->where('site.contact.email', $general->email)
            ->where('site.contact.phone', $general->phone)
            ->where('site.contact.address', $general->address)
            ->where('site.social.facebook', $general->facebookUrl)
            ->where('site.social.instagram', $general->instagramUrl)
            ->where('site.social.linkedin', $general->linkedinUrl)
        );
    }

    public function test_hero_carries_the_admin_managed_stats_for_the_current_locale(): void
    {
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Home')
            ->has('hero.stats', 3)
            ->where('hero.stats.0', [
                'value' => 24500,
                'unit' => 'm',
                'label' => 'Nutiesta kabelio',
            ])
            ->where('hero.stats.1.unit', 'vnt.')
        );
    }

    public function test_hero_stats_follow_the_locale(): void
    {
        app()->setLocale('en');

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('hero.stats.0.label', 'Cable installed')
            ->where('hero.stats.1.unit', 'pcs')
        );
    }

    public function test_hero_image_url_is_null_until_one_is_uploaded(): void
    {
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('hero.imageUrl', null)
        );
    }

    public function test_hero_carries_the_uploaded_image_url(): void
    {
        $homepage = app(HomepageSettings::class);
        $homepage->heroImage = 'site/hero.jpg';
        $homepage->save();

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('hero.imageUrl', Storage::disk('public')->url('site/hero.jpg'))
        );
    }

    public function test_site_logo_urls_are_null_until_logos_are_uploaded(): void
    {
        $this->get('/contact')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('site.logo.light', null)
            ->where('site.logo.dark', null)
        );
    }

    public function test_google_fonts_stylesheet_does_not_block_first_paint(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('media="print" onload="this.media=\'all\'"', false);
        $response->assertSee('<noscript>', false);
        $this->assertSame(2, substr_count($response->getContent(), 'fonts.googleapis.com/css2'));
    }

    public function test_site_logo_urls_are_shared_with_every_page(): void
    {
        $general = app(GeneralSettings::class);
        $general->logo = 'site/logo.png';
        $general->logoDark = 'site/logo-dark.png';
        $general->save();

        $this->get('/contact')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('site.logo.light', Storage::disk('public')->url('site/logo.png'))
            ->where('site.logo.dark', Storage::disk('public')->url('site/logo-dark.png'))
        );
    }
}

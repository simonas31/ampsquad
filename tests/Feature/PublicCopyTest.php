<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CalculatorOption;
use App\Settings\HomepageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The public site's type system treats the em dash as a typographic
 * artefact: at display sizes it reads as a rule rather than as punctuation,
 * so marketing copy uses full stops, commas or parentheses instead. These
 * tests guard that rule at both of its sources - the admin-editable
 * homepage settings and the client-side translation files - because a
 * reintroduced dash is invisible in review but obvious on the page.
 *
 * The admin panel's own strings (lang/*\/admin.php) are deliberately out of
 * scope: they are not part of the public design system.
 */
class PublicCopyTest extends TestCase
{
    use RefreshDatabase;

    private const EM_DASH = "\u{2014}";

    private const EN_DASH = "\u{2013}";

    public function test_homepage_settings_copy_contains_no_dash_separators(): void
    {
        $homepage = app(HomepageSettings::class);

        $copy = [
            'heroTitle' => $homepage->heroTitle,
            'heroSubtitle' => $homepage->heroSubtitle,
            'introTitle' => $homepage->introTitle,
            'introContent' => $homepage->introContent,
            'ctaTitle' => $homepage->ctaTitle,
            'ctaButtonLabel' => $homepage->ctaButtonLabel,
        ];

        foreach ($copy as $setting => $translations) {
            foreach ($translations as $locale => $value) {
                $this->assertStringNotContainsString(
                    self::EM_DASH,
                    (string) $value,
                    "homepage.{$setting}.{$locale} contains an em dash.",
                );
                $this->assertStringNotContainsString(
                    self::EN_DASH,
                    (string) $value,
                    "homepage.{$setting}.{$locale} contains an en dash.",
                );
            }
        }
    }

    public function test_client_translation_files_contain_no_dash_separators(): void
    {
        foreach (['lt', 'en'] as $locale) {
            $contents = file_get_contents(resource_path("js/i18n/{$locale}.json"));

            $this->assertIsString($contents);
            $this->assertStringNotContainsString(
                self::EM_DASH,
                $contents,
                "resources/js/i18n/{$locale}.json contains an em dash.",
            );
            $this->assertStringNotContainsString(
                self::EN_DASH,
                $contents,
                "resources/js/i18n/{$locale}.json contains an en dash.",
            );
        }
    }

    public function test_home_exposes_the_capability_options_the_page_renders(): void
    {
        // The capabilities section presents each calculator category by name
        // and lists the inputs its estimate is based on, so the options must
        // stay eager-loaded on the homepage payload.
        CalculatorOption::factory()->create();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Home')
            ->has('calculatorCategories.0.name')
            ->has('calculatorCategories.0.options')
            ->has('cta.title')
        );
    }
}

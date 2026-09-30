<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function navigationLabelKeys(): array
    {
        $labelKeys = [];

        $this->get('/')->assertInertia(function (AssertableInertia $page) use (&$labelKeys) {
            $labelKeys = array_column($page->toArray()['props']['navigation'], 'labelKey');
        });

        return $labelKeys;
    }

    public function test_navigation_lists_about_projects_price_calculator_and_contact_in_order(): void
    {
        Page::factory()->about()->create();

        $this->assertSame(
            ['nav.home', 'nav.about', 'nav.projects', 'nav.priceCalculator', 'nav.contact'],
            $this->navigationLabelKeys(),
        );
    }

    public function test_about_link_is_left_out_until_the_about_page_exists(): void
    {
        $this->assertSame(
            ['nav.home', 'nav.projects', 'nav.priceCalculator', 'nav.contact'],
            $this->navigationLabelKeys(),
        );
    }

    public function test_navigation_urls_point_at_the_localized_routes(): void
    {
        $about = Page::factory()->about()->create();

        $this->get('/')->assertInertia(function (AssertableInertia $page) use ($about) {
            $urls = array_column($page->toArray()['props']['navigation'], 'url', 'labelKey');

            $this->assertSame(route('pages.show', ['slug' => $about->slug]), $urls['nav.about']);
            $this->assertSame(route('price-calculator'), $urls['nav.priceCalculator']);
            $this->assertStringEndsWith('/kainu-skaiciuokle', $urls['nav.priceCalculator']);
        });
    }
}

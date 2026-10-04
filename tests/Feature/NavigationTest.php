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

    /**
     * @return array{header: list<array{title: string, url: string}>, footer: list<array{title: string, url: string}>}
     */
    private function pageLinks(): array
    {
        $links = [];

        $this->get('/')->assertInertia(function (AssertableInertia $page) use (&$links) {
            $links = $page->toArray()['props']['pageLinks'];
        });

        return $links;
    }

    public function test_pages_flagged_for_the_header_or_footer_are_shared_as_menu_links(): void
    {
        $header = Page::factory()->inHeader()->create(['key' => 'faq', 'title' => ['lt' => 'DUK', 'en' => 'FAQ'], 'slug' => ['lt' => 'duk', 'en' => 'faq']]);
        $footer = Page::factory()->inFooter()->create(['key' => 'privacy', 'title' => ['lt' => 'Privatumas', 'en' => 'Privacy'], 'slug' => ['lt' => 'privatumas', 'en' => 'privacy']]);
        $both = Page::factory()->inHeader()->inFooter()->create(['key' => 'careers', 'title' => ['lt' => 'Karjera', 'en' => 'Careers'], 'slug' => ['lt' => 'karjera', 'en' => 'careers']]);
        Page::factory()->create(['key' => 'hidden']);

        $links = $this->pageLinks();

        $this->assertSame(
            [
                ['title' => 'DUK', 'url' => route('pages.show', ['slug' => $header->slug])],
                ['title' => 'Karjera', 'url' => route('pages.show', ['slug' => $both->slug])],
            ],
            $links['header'],
        );
        $this->assertSame(
            [
                ['title' => 'Privatumas', 'url' => route('pages.show', ['slug' => $footer->slug])],
                ['title' => 'Karjera', 'url' => route('pages.show', ['slug' => $both->slug])],
            ],
            $links['footer'],
        );
    }

    public function test_menu_link_titles_follow_the_locale(): void
    {
        Page::factory()->inHeader()->create(['key' => 'faq', 'title' => ['lt' => 'DUK', 'en' => 'FAQ'], 'slug' => ['lt' => 'duk', 'en' => 'faq']]);

        app()->setLocale('en');

        $this->assertSame('FAQ', $this->pageLinks()['header'][0]['title']);
    }

    public function test_no_menu_links_are_shared_when_no_page_is_flagged(): void
    {
        Page::factory()->create();

        $this->assertSame(['header' => [], 'footer' => []], $this->pageLinks());
    }

    public function test_the_about_page_is_not_listed_twice(): void
    {
        Page::factory()->about()->create();

        $this->assertSame(['header' => [], 'footer' => []], $this->pageLinks());
        $this->assertContains('nav.about', $this->navigationLabelKeys());
    }
}

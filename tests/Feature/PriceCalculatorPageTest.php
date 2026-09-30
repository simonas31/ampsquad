<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PriceCalculatorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_calculator_page_renders_with_breadcrumbs_and_seo(): void
    {
        $response = $this->get('/kainu-skaiciuokle');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('PriceCalculator')
            ->where('breadcrumbs.0.label', __('nav.home'))
            ->where('breadcrumbs.1.label', __('nav.price_calculator'))
            ->where('breadcrumbs.1.url', null)
            ->where('seo.title', __('nav.price_calculator').' - '.config('app.name'))
        );
    }

    public function test_price_calculator_url_segment_is_translated_per_locale(): void
    {
        $this->assertSame('kainu-skaiciuokle', trans('routes.price_calculator', [], 'lt'));
        $this->assertSame('price-calculator', trans('routes.price_calculator', [], 'en'));
    }
}

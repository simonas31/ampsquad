<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Seo\Breadcrumbs;
use App\Support\Seo\SeoData;
use Inertia\Inertia;
use Inertia\Response;

class PriceCalculatorController extends Controller
{
    public function show(): Response
    {
        $breadcrumbs = Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.price_calculator'), 'url' => null],
        ]);

        return Inertia::render('PriceCalculator', [
            'breadcrumbs' => $breadcrumbs->toArray(),
            'seo' => SeoData::make(
                pageTitle: __('nav.price_calculator'),
                jsonLd: [$breadcrumbs->jsonLd()],
            ),
        ]);
    }
}

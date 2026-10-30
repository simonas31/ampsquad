<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Support\Seo\Breadcrumbs;
use App\Support\Seo\SeoData;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $breadcrumbs = Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.account'), 'url' => null],
        ]);

        return Inertia::render('Account/Dashboard', [
            'breadcrumbs' => $breadcrumbs->toArray(),
            'seo' => SeoData::make(pageTitle: __('nav.account'), noindex: true),
        ]);
    }
}

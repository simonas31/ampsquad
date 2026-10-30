<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Support\Seo\Breadcrumbs;
use App\Support\Seo\SeoData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $customer = $request->user('customer');

        $breadcrumbs = Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.account'), 'url' => null],
        ]);

        return Inertia::render('Account/Dashboard', [
            'profile' => [
                'fullName' => $customer->full_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'locale' => $customer->locale,
            ],
            'breadcrumbs' => $breadcrumbs->toArray(),
            'seo' => SeoData::make(pageTitle: __('nav.account'), noindex: true),
        ]);
    }
}

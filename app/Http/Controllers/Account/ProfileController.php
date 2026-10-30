<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCustomerProfileRequest;
use App\Services\Customers\CustomerAccountService;
use App\Support\Seo\Breadcrumbs;
use App\Support\Seo\SeoData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(private readonly CustomerAccountService $accounts) {}

    public function show(Request $request): Response
    {
        $customer = $request->user('customer');

        $breadcrumbs = Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.account'), 'url' => route('account.dashboard')],
            ['label' => __('nav.profile'), 'url' => null],
        ]);

        return Inertia::render('Account/Profile', [
            'profile' => [
                'fullName' => $customer->full_name,
                'phone' => $customer->phone,
                'locale' => $customer->locale,
                'marketingConsent' => $customer->marketing_consent_at !== null,
            ],
            'updateUrl' => route('account.profile.update', absolute: false),
            'breadcrumbs' => $breadcrumbs->toArray(),
            'seo' => SeoData::make(pageTitle: __('nav.profile'), noindex: true),
        ]);
    }

    public function update(UpdateCustomerProfileRequest $request): RedirectResponse
    {
        $this->accounts->updateProfile($request->user('customer'), $request->validated());

        Inertia::flash('accountStatus', 'profile-updated');

        return back();
    }
}

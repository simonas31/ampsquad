<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterCustomerRequest;
use App\Services\Customers\CustomerAccountService;
use App\Support\Seo\Breadcrumbs;
use App\Support\Seo\SeoData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function __construct(private readonly CustomerAccountService $accounts) {}

    public function show(): Response
    {
        $breadcrumbs = Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.register'), 'url' => null],
        ]);

        return Inertia::render('Auth/Register', [
            'submitUrl' => route('customer.register.store', absolute: false),
            'breadcrumbs' => $breadcrumbs->toArray(),
            'seo' => SeoData::make(pageTitle: __('nav.register'), noindex: true),
        ]);
    }

    /**
     * Makes the account, signs the customer in and asks them to verify their
     * address. Until they do, the account area stays closed to them.
     */
    public function store(RegisterCustomerRequest $request): RedirectResponse
    {
        $customer = $this->accounts->register($request->validated(), app()->getLocale());

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect()->route('customer.verification.notice');
    }
}

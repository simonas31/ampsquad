<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateCustomerSession;
use App\Http\Requests\LoginCustomerRequest;
use App\Support\Seo\Breadcrumbs;
use App\Support\Seo\SeoData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    /**
     * Where checkout sends a guest who has an account, so they come back to
     * it after logging in. Only checkout is a place to come back to: the
     * address to return to is never taken from the request.
     */
    public function show(Request $request): Response
    {
        if ($request->query('return') === 'checkout') {
            redirect()->setIntendedUrl(route('checkout.show'));
        }

        $breadcrumbs = Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.login'), 'url' => null],
        ]);

        return Inertia::render('Auth/Login', [
            'submitUrl' => route('customer.login.store', absolute: false),
            'forgotPasswordUrl' => route('customer.password.request', absolute: false),
            'breadcrumbs' => $breadcrumbs->toArray(),
            'seo' => SeoData::make(pageTitle: __('nav.login'), noindex: true),
        ]);
    }

    /**
     * Signs the customer in and sends them to where they were going, or to
     * their account. A customer who has not verified their address yet is
     * sent on from there to the page that asks for it.
     */
    public function store(LoginCustomerRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        AuthenticateCustomerSession::stamp($request);

        return redirect()->intended(route('account.dashboard'));
    }
}

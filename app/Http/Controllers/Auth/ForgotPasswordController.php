<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Support\Seo\Breadcrumbs;
use App\Support\Seo\SeoData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class ForgotPasswordController extends Controller
{
    public function show(): Response
    {
        $breadcrumbs = Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.login'), 'url' => route('customer.login')],
            ['label' => __('nav.forgot_password'), 'url' => null],
        ]);

        return Inertia::render('Auth/ForgotPassword', [
            'submitUrl' => route('customer.password.email', absolute: false),
            'breadcrumbs' => $breadcrumbs->toArray(),
            'seo' => SeoData::make(pageTitle: __('nav.forgot_password'), noindex: true),
        ]);
    }

    /**
     * Mails the link if the address has an account. The answer is the same
     * either way, and so is how long it takes (the mail is queued), so the
     * form cannot be used to find out who has an account.
     */
    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        Password::broker('customers')->sendResetLink($request->only('email'));

        Inertia::flash('accountStatus', 'reset-link-sent');

        return back();
    }
}

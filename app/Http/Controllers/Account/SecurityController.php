<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Support\Seo\Breadcrumbs;
use App\Support\Seo\SeoData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    /**
     * Where a customer changes their email address and their password.
     */
    public function __invoke(Request $request): Response
    {
        $breadcrumbs = Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.account'), 'url' => route('account.dashboard')],
            ['label' => __('nav.security'), 'url' => null],
        ]);

        return Inertia::render('Account/Security', [
            'pendingEmail' => $request->user('customer')->pending_email,
            'emailUrl' => route('account.email.update', absolute: false),
            'passwordUrl' => route('account.password.update', absolute: false),
            'breadcrumbs' => $breadcrumbs->toArray(),
            'seo' => SeoData::make(pageTitle: __('nav.security'), noindex: true),
        ]);
    }
}

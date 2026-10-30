<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\Seo\Breadcrumbs;
use App\Support\Seo\SeoData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class VerifyEmailController extends Controller
{
    /**
     * Where a customer who has not verified their address waits. One who has
     * has no business here.
     */
    public function notice(Request $request): Response|RedirectResponse
    {
        if ($request->user('customer')->hasVerifiedEmail()) {
            return redirect()->route('account.dashboard');
        }

        $breadcrumbs = Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.verify_email'), 'url' => null],
        ]);

        return Inertia::render('Auth/VerifyEmail', [
            'resendUrl' => route('customer.verification.send', absolute: false),
            'breadcrumbs' => $breadcrumbs->toArray(),
            'seo' => SeoData::make(pageTitle: __('nav.verify_email'), noindex: true),
        ]);
    }

    public function resend(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');

        if ($customer->hasVerifiedEmail()) {
            return redirect()->route('account.dashboard');
        }

        $customer->sendEmailVerificationNotification();

        Inertia::flash('accountStatus', 'verification-link-sent');

        return back();
    }

    /**
     * The link in the mail. The signature (checked before this runs) shows
     * the mail was made here and has not run out; the hash shows it was made
     * for the address the account has now. Whoever holds the link has read
     * the mail, so it needs no sign-in, and it opens the account area for
     * nobody: it only verifies. It goes through the language the customer
     * registered in, as the link itself has none.
     */
    public function verify(int $id, string $hash): RedirectResponse
    {
        $customer = Customer::query()->findOrFail($id);

        abort_unless(hash_equals(sha1($customer->getEmailForVerification()), $hash), 403);

        if (! $customer->hasVerifiedEmail()) {
            $customer->markEmailAsVerified();
        }

        Inertia::flash('accountStatus', 'email-verified');

        $isSignedInAsThem = Auth::guard('customer')->id() === $customer->getKey();
        $destination = route($isSignedInAsThem ? 'account.dashboard' : 'customer.login');

        return redirect(LaravelLocalization::getLocalizedURL($customer->locale, $destination));
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\Customer;
use App\Services\Customers\CustomerAccountService;
use App\Support\Seo\Breadcrumbs;
use App\Support\Seo\SeoData;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ResetPasswordController extends Controller
{
    public function __construct(private readonly CustomerAccountService $accounts) {}

    /**
     * The page the link in the mail opens. The token is in its address, so
     * the page tells the browser to send no Referer: the token must not
     * leave with a click on a link in the footer.
     */
    public function show(Request $request, string $token): SymfonyResponse
    {
        $breadcrumbs = Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.reset_password'), 'url' => null],
        ]);

        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->string('email')->lower()->toString(),
            'submitUrl' => route('customer.password.store', absolute: false),
            'breadcrumbs' => $breadcrumbs->toArray(),
            'seo' => SeoData::make(pageTitle: __('nav.reset_password'), noindex: true),
        ])
            ->toResponse($request)
            ->header('Referrer-Policy', 'no-referrer');
    }

    /**
     * Sets the new password, and sends the customer to log in with it: the
     * link proves they read their mail, not that they are signed in. A wrong
     * token and an address with no account get the same answer.
     */
    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::broker('customers')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Customer $customer, string $password): void {
                $this->accounts->changePassword($customer, $password);

                event(new PasswordReset($customer));
            },
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            return back()->withErrors(['email' => __('passwords.token')]);
        }

        Inertia::flash('accountStatus', 'password-reset');

        return redirect()->route('customer.login');
    }
}

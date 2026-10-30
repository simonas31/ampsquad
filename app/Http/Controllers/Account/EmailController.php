<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCustomerEmailRequest;
use App\Models\Customer;
use App\Services\Customers\CustomerAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class EmailController extends Controller
{
    public function __construct(private readonly CustomerAccountService $accounts) {}

    /**
     * Mails a link to the new address. The account keeps its address until
     * the link is followed.
     */
    public function update(UpdateCustomerEmailRequest $request): RedirectResponse
    {
        $this->accounts->requestEmailChange($request->user('customer'), $request->validated('email'));

        Inertia::flash('accountStatus', 'email-change-requested');

        return back();
    }

    /**
     * The link in that mail. Like the verification link it needs no sign-in:
     * the signature (checked before this runs) shows the mail was made here
     * and has not run out, and the hash that it was made for the address now
     * waiting. Following it again after it worked changes nothing.
     */
    public function confirm(int $id, string $hash): RedirectResponse
    {
        $customer = Customer::query()->findOrFail($id);

        $alreadyDone = hash_equals(sha1((string) $customer->email), $hash);
        $isWaiting = $customer->pending_email !== null && hash_equals(sha1($customer->pending_email), $hash);

        abort_unless($alreadyDone || $isWaiting, 403);

        $changed = $alreadyDone || $this->accounts->confirmEmailChange($customer);

        Inertia::flash('accountStatus', $changed ? 'email-changed' : 'email-change-failed');

        $isSignedInAsThem = Auth::guard('customer')->id() === $customer->getKey();
        $destination = route($isSignedInAsThem ? 'account.security' : 'customer.login');

        return redirect(LaravelLocalization::getLocalizedURL($customer->locale, $destination));
    }
}

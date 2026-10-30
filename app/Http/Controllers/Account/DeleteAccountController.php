<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteCustomerAccountRequest;
use App\Services\Customers\CustomerAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DeleteAccountController extends Controller
{
    public function __construct(private readonly CustomerAccountService $accounts) {}

    /**
     * Closes the account and signs the customer out of this browser. Any
     * other browser is signed out the next time it asks: a deleted account
     * can no longer be found by the guard.
     */
    public function __invoke(DeleteCustomerAccountRequest $request): RedirectResponse
    {
        $this->accounts->deleteAccount($request->user('customer'));

        Auth::guard('customer')->logout();
        $request->session()->regenerate();

        Inertia::flash('accountStatus', 'account-deleted');

        return redirect()->route('customer.login');
    }
}

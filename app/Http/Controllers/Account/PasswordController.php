<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCustomerPasswordRequest;
use App\Services\Customers\CustomerAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PasswordController extends Controller
{
    public function __construct(private readonly CustomerAccountService $accounts) {}

    /**
     * Changes the password and signs the customer out of every browser,
     * this one included, to log in again with the new one. The form says so
     * before it is sent.
     */
    public function update(UpdateCustomerPasswordRequest $request): RedirectResponse
    {
        $this->accounts->changePassword($request->user('customer'), $request->validated('password'));

        Auth::guard('customer')->logout();
        $request->session()->regenerate();

        Inertia::flash('accountStatus', 'password-changed');

        return redirect()->route('customer.login');
    }
}

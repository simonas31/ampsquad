<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Services\Customers\CustomerAddressBook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DefaultAddressController extends Controller
{
    public function __construct(private readonly CustomerAddressBook $addresses) {}

    /**
     * Makes the address the one checkout offers first.
     */
    public function __invoke(Request $request, CustomerAddress $customerAddress): RedirectResponse
    {
        Gate::forUser($request->user('customer'))->authorize('update', $customerAddress);

        $this->addresses->makeDefault($customerAddress);

        Inertia::flash('accountStatus', 'address-default-set');

        return back();
    }
}

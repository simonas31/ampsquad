<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\Customers\CustomerOrderLinker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LinkOrdersController extends Controller
{
    public function __construct(private readonly CustomerOrderLinker $linker) {}

    /**
     * Brings the guest orders placed with the customer's address into their
     * account, and says how many there were.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $count = $this->linker->link($request->user('customer'));

        Inertia::flash('accountStatus', 'orders-linked');
        Inertia::flash('linkedOrders', $count);

        return redirect()->route('account.orders.index');
    }
}

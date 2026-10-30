<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCustomerAddressRequest;
use App\Http\Resources\CustomerAddressResource;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\Customers\CustomerAddressBook;
use App\Support\Seo\Breadcrumbs;
use App\Support\Seo\SeoData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A customer's saved addresses. Which ones are the customer's own is decided
 * by the route binding (a stranger's address id is a plain 404), and the
 * policy is asked as well before anything is changed.
 */
class AddressController extends Controller
{
    public function __construct(private readonly CustomerAddressBook $addresses) {}

    public function index(Request $request): Response
    {
        $customer = $request->user('customer');

        return Inertia::render('Account/Addresses/Index', [
            'addresses' => CustomerAddressResource::collection($customer->addressesDefaultFirst()),
            'limit' => CustomerAddress::MAX_PER_CUSTOMER,
            'createUrl' => route('account.addresses.create', absolute: false),
            'breadcrumbs' => $this->breadcrumbs(null)->toArray(),
            'seo' => SeoData::make(pageTitle: __('nav.addresses'), noindex: true),
        ]);
    }

    /**
     * The form for a new address, with the customer's own name and phone as
     * a start, as most addresses are their own. A customer who already keeps
     * the most is sent back to the list, which says so.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $customer = $request->user('customer');

        if ($customer->addresses()->count() >= CustomerAddress::MAX_PER_CUSTOMER) {
            return redirect()->route('account.addresses.index');
        }

        return Inertia::render('Account/Addresses/Create', [
            'initial' => [
                'recipientName' => $customer->full_name,
                'phone' => $customer->phone,
            ],
            'isFirst' => ! $customer->addresses()->exists(),
            'storeUrl' => route('account.addresses.store', absolute: false),
            'indexUrl' => route('account.addresses.index', absolute: false),
            'breadcrumbs' => $this->breadcrumbs(__('nav.new_address'))->toArray(),
            'seo' => SeoData::make(pageTitle: __('nav.new_address'), noindex: true),
        ]);
    }

    public function store(SaveCustomerAddressRequest $request): RedirectResponse
    {
        $this->addresses->add(
            $request->user('customer'),
            $request->safe()->except('make_default'),
            $request->boolean('make_default'),
        );

        Inertia::flash('accountStatus', 'address-saved');

        return redirect()->route('account.addresses.index');
    }

    public function edit(Request $request, CustomerAddress $customerAddress): Response
    {
        $customer = $request->user('customer');
        $this->authorizeFor($customer, 'update', $customerAddress);

        return Inertia::render('Account/Addresses/Edit', [
            'address' => new CustomerAddressResource($customerAddress),
            'indexUrl' => route('account.addresses.index', absolute: false),
            'breadcrumbs' => $this->breadcrumbs(__('nav.edit_address'))->toArray(),
            'seo' => SeoData::make(pageTitle: __('nav.edit_address'), noindex: true),
        ]);
    }

    public function update(SaveCustomerAddressRequest $request, CustomerAddress $customerAddress): RedirectResponse
    {
        $this->authorizeFor($request->user('customer'), 'update', $customerAddress);

        $this->addresses->update(
            $customerAddress,
            $request->safe()->except('make_default'),
            $request->boolean('make_default'),
        );

        Inertia::flash('accountStatus', 'address-saved');

        return redirect()->route('account.addresses.index');
    }

    public function destroy(Request $request, CustomerAddress $customerAddress): RedirectResponse
    {
        $this->authorizeFor($request->user('customer'), 'delete', $customerAddress);

        $this->addresses->delete($customerAddress);

        Inertia::flash('accountStatus', 'address-deleted');

        return redirect()->route('account.addresses.index');
    }

    private function authorizeFor(Customer $customer, string $ability, CustomerAddress $address): void
    {
        Gate::forUser($customer)->authorize($ability, $address);
    }

    private function breadcrumbs(?string $leaf): Breadcrumbs
    {
        return Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.account'), 'url' => route('account.dashboard')],
            ['label' => __('nav.addresses'), 'url' => $leaf === null ? null : route('account.addresses.index')],
            ...($leaf === null ? [] : [['label' => $leaf, 'url' => null]]),
        ]);
    }
}

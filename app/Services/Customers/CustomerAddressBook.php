<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A customer's saved addresses, and which of them is the default.
 *
 * Every change that depends on how many addresses there are, or on which one
 * is the default, locks the customer's row first. That serialises concurrent
 * requests (a double-clicked button, two tabs), so the limit and the one
 * default hold without retry loops. The caller has checked that the address
 * is the customer's own (see CustomerAddressPolicy).
 */
class CustomerAddressBook
{
    /**
     * Saves a new address. The first one a customer saves is their default,
     * whatever they asked for, so checkout always has one to offer.
     *
     * @param  array{label?: ?string, recipient_name: string, phone: string, street: string, city: string, postal_code: string, country: string}  $data
     *
     * @throws ValidationException when the customer already keeps the most they may
     */
    public function add(Customer $customer, array $data, bool $makeDefault): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $data, $makeDefault): CustomerAddress {
            $locked = Customer::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->addresses()->count() >= CustomerAddress::MAX_PER_CUSTOMER) {
                throw ValidationException::withMessages([
                    'limit' => __('account.address_limit', ['max' => CustomerAddress::MAX_PER_CUSTOMER]),
                ]);
            }

            $address = $locked->addresses()->create($data);

            if ($makeDefault || $locked->default_shipping_address_id === null) {
                $locked->forceFill(['default_shipping_address_id' => $address->getKey()])->save();
            }

            return $address;
        });
    }

    /**
     * @param  array{label?: ?string, recipient_name: string, phone: string, street: string, city: string, postal_code: string, country: string}  $data
     */
    public function update(CustomerAddress $address, array $data, bool $makeDefault): void
    {
        DB::transaction(function () use ($address, $data, $makeDefault): void {
            $address->update($data);

            if ($makeDefault) {
                $this->makeDefault($address);
            }
        });
    }

    public function makeDefault(CustomerAddress $address): void
    {
        DB::transaction(function () use ($address): void {
            $customer = Customer::query()->whereKey($address->customer_id)->lockForUpdate()->firstOrFail();

            $customer->forceFill(['default_shipping_address_id' => $address->getKey()])->save();
        });
    }

    /**
     * Deletes the address. If it was the default, the customer's oldest
     * remaining address takes its place, so a customer with addresses always
     * has a default.
     */
    public function delete(CustomerAddress $address): void
    {
        DB::transaction(function () use ($address): void {
            $customer = Customer::query()->whereKey($address->customer_id)->lockForUpdate()->firstOrFail();
            $wasDefault = $customer->default_shipping_address_id === $address->getKey();

            $address->delete();

            if ($wasDefault) {
                $customer->forceFill([
                    'default_shipping_address_id' => $customer->addresses()->oldest('id')->value('id'),
                ])->save();
            }
        });
    }
}

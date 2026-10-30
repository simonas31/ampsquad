<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\CustomerAddress;

/**
 * A customer's addresses are theirs alone. Nobody else sees or changes them,
 * the admin panel included: it has no page for them and needs none, as an
 * order carries the address it was sent to.
 */
class CustomerAddressPolicy
{
    public function update(Customer $customer, CustomerAddress $address): bool
    {
        return $address->customer_id === $customer->getKey();
    }

    public function delete(Customer $customer, CustomerAddress $address): bool
    {
        return $this->update($customer, $address);
    }
}

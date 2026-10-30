<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/**
 * Brings a customer's earlier guest orders into their account, when they ask.
 *
 * What proves an order is theirs is the address: the account's is verified,
 * so whoever holds the account has read mail sent to it, and an order placed
 * with that address was placed by someone who named it. An order of another
 * address is not touched, and neither is one that already belongs to an
 * account. Only the link is added; nothing on the order itself changes.
 */
class CustomerOrderLinker
{
    /**
     * How many guest orders carry the customer's address.
     */
    public function linkableCount(Customer $customer): int
    {
        return $this->linkable($customer)->count();
    }

    /**
     * Links them, and returns how many. Done as one statement, straight on
     * the table: an order is a record of a sale, so its own timestamps are
     * left as they are.
     */
    public function link(Customer $customer): int
    {
        return $this->linkable($customer)->toBase()->update(['customer_id' => $customer->getKey()]);
    }

    /**
     * @return Builder<Order>
     */
    private function linkable(Customer $customer): Builder
    {
        // An order whose details were wiped (see AnonymizeStaleOrders) has an
        // empty address, which no account can have; the check keeps that so.
        return Order::query()
            ->whereNull('customer_id')
            ->where('customer_email', '!=', '')
            ->where('customer_email', $customer->email);
    }
}

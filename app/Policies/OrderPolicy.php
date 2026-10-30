<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;

/**
 * Anyone who can use the admin panel works the orders, as with the rest of
 * the shop. Nothing deletes an order, from anywhere: an order is a record of
 * a sale, and the stock and payments hang off it. Orders come from checkout
 * only, and their contents never change; what an admin can do is move one
 * along (update).
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return true;
    }

    /**
     * A customer sees the orders that belong to their account, and no
     * others. Named apart from view(): that one is for the admin panel's
     * User, and a policy method only answers to the kind of user it names.
     */
    public function viewOwn(Customer $customer, Order $order): bool
    {
        return $order->customer_id === $customer->getKey();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Order $order): bool
    {
        return true;
    }

    public function delete(User $user, Order $order): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Order $order): bool
    {
        return false;
    }

    public function forceDelete(User $user, Order $order): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}

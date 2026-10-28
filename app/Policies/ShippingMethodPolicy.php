<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ShippingMethod;
use App\Models\User;

/**
 * Anyone who can use the admin panel manages shipping, as with the rest of
 * the shop. Deleting is allowed: orders keep their own copy.
 */
class ShippingMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ShippingMethod $shippingMethod): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ShippingMethod $shippingMethod): bool
    {
        return true;
    }

    public function delete(User $user, ShippingMethod $shippingMethod): bool
    {
        return true;
    }
}

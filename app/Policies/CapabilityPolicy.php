<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Capability;
use App\Models\User;

class CapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Capability $capability): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Capability $capability): bool
    {
        return true;
    }

    public function delete(User $user, Capability $capability): bool
    {
        return true;
    }
}

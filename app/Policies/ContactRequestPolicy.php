<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContactRequest;
use App\Models\User;

class ContactRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ContactRequest $contactRequest): bool
    {
        return true;
    }

    /**
     * Contact requests are only ever created by the public contact form,
     * never through the admin panel.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ContactRequest $contactRequest): bool
    {
        return true;
    }

    public function delete(User $user, ContactRequest $contactRequest): bool
    {
        return true;
    }

    public function restore(User $user, ContactRequest $contactRequest): bool
    {
        return true;
    }

    public function forceDelete(User $user, ContactRequest $contactRequest): bool
    {
        return true;
    }
}

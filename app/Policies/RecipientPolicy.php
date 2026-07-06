<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Recipient;
use App\Models\User;

class RecipientPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Recipient $recipient): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Recipient $recipient): bool
    {
        return true;
    }

    public function delete(User $user, Recipient $recipient): bool
    {
        return true;
    }
}

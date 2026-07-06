<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Page $page): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Page $page): bool
    {
        return true;
    }

    /**
     * Seeded system pages (about/privacy-policy/terms-and-conditions) are
     * guarded here too, not just in the form UI — hardcoded nav/footer
     * links point at them, so deleting one would 404 a real link.
     */
    public function delete(User $user, Page $page): bool
    {
        return ! $page->isProtected();
    }

    public function restore(User $user, Page $page): bool
    {
        return true;
    }

    public function forceDelete(User $user, Page $page): bool
    {
        return ! $page->isProtected();
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CatalogImport;
use App\Models\User;

/**
 * Runs are history: started from the Products list, then only read. Old ones
 * are pruned by the scheduler, together with their files.
 */
class CatalogImportPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CatalogImport $catalogImport): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CatalogImport $catalogImport): bool
    {
        return false;
    }

    public function delete(User $user, CatalogImport $catalogImport): bool
    {
        return false;
    }
}

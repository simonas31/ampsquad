<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductCategory $category): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ProductCategory $category): bool
    {
        return true;
    }

    /**
     * Not while the category holds products or sub-categories: deleting it
     * would strip those products of their category and lift the
     * sub-categories to the top level. The message is what the admin panel
     * shows on the disabled delete button.
     */
    public function delete(User $user, ProductCategory $category): Response
    {
        return $category->isInUse()
            ? Response::deny(__('admin.product_categories.delete_blocked'))
            : Response::allow();
    }
}

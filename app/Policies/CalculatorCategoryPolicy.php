<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CalculatorCategory;
use App\Models\User;

class CalculatorCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CalculatorCategory $calculatorCategory): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CalculatorCategory $calculatorCategory): bool
    {
        return true;
    }

    public function delete(User $user, CalculatorCategory $calculatorCategory): bool
    {
        return true;
    }
}

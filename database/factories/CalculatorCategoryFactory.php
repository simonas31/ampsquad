<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CalculatorCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalculatorCategory>
 */
class CalculatorCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'name' => ['lt' => $name, 'en' => $name],
            'icon' => 'heroicon-o-bolt',
            'order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}

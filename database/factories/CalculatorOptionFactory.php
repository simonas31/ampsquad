<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CalculatorCategory;
use App\Models\CalculatorOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalculatorOption>
 */
class CalculatorOptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'calculator_category_id' => CalculatorCategory::factory(),
            'name' => ['lt' => $name, 'en' => $name],
            'order' => fake()->numberBetween(0, 20),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'parent_id' => null,
            'name' => ['lt' => $name, 'en' => $name],
            'slug' => ['lt' => Str::slug($name), 'en' => Str::slug($name)],
            'description' => null,
            'order' => fake()->numberBetween(0, 20),
        ];
    }
}

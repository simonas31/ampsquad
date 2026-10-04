<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Capability;
use App\Models\CapabilityOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CapabilityOption>
 */
class CapabilityOptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'capability_id' => Capability::factory(),
            'name' => ['lt' => $name, 'en' => $name],
            'order' => fake()->numberBetween(0, 20),
        ];
    }
}

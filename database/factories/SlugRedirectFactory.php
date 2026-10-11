<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Page;
use App\Models\SlugRedirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SlugRedirect>
 */
class SlugRedirectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'redirectable_type' => Page::class,
            'redirectable_id' => Page::factory(),
            'locale' => fake()->randomElement(['lt', 'en']),
            'old_slug' => fake()->unique()->slug(3),
        ];
    }
}

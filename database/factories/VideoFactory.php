<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VideoType;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = ucfirst(fake()->words(3, true));

        return [
            'title' => ['lt' => $title, 'en' => $title],
            'type' => VideoType::InstagramEmbed,
            'embed_url' => 'https://www.instagram.com/p/'.fake()->bothify('??????????').'/',
            'order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function upload(): static
    {
        return $this->state(fn () => [
            'type' => VideoType::Upload,
            'embed_url' => null,
        ]);
    }
}

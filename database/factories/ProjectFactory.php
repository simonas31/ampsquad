<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Category;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @var array<int, array{lt: string, en: string}>
     */
    private static array $projectTitles = [
        ['lt' => 'Gyvenamojo namo elektros instaliacijos modernizavimas', 'en' => 'Residential Electrical Rewiring'],
        ['lt' => 'Pramoninio sandėlio elektros skydinės montavimas', 'en' => 'Industrial Warehouse Switchboard Installation'],
        ['lt' => 'Saulės elektrinės įrengimas privačiam namui', 'en' => 'Solar Power System Installation for a Private Home'],
        ['lt' => 'Biuro pastato apšvietimo sistemos atnaujinimas', 'en' => 'Office Building Lighting System Upgrade'],
        ['lt' => 'Elektromobilių įkrovimo stotelių instaliacija', 'en' => 'EV Charging Station Installation'],
        ['lt' => 'Prekybos centro elektros tinklo rekonstrukcija', 'en' => 'Shopping Center Electrical Network Reconstruction'],
        ['lt' => 'Automatizuotos pastato valdymo sistemos diegimas', 'en' => 'Building Management System Automation'],
        ['lt' => 'Gamyklos elektros saugos audito atlikimas', 'en' => 'Factory Electrical Safety Audit'],
    ];

    private static int $projectTitleIndex = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // A round-robin index instead of fake()->unique(): the latter throws
        // OverflowException once the pool is exhausted.
        $pair = self::$projectTitles[self::$projectTitleIndex % count(self::$projectTitles)];
        self::$projectTitleIndex++;

        return [
            'category_id' => Category::factory(),
            'author_id' => null,
            'title' => ['lt' => $pair['lt'], 'en' => $pair['en']],
            'slug' => ['lt' => Str::slug($pair['lt']), 'en' => Str::slug($pair['en'])],
            'excerpt' => [
                'lt' => fake('lt_LT')->sentence(18),
                'en' => fake('en_US')->sentence(18),
            ],
            'blocks' => $this->defaultBlocks(),
            'status' => ProjectStatus::Published,
            'published_at' => fake()->dateTimeBetween('-1 year'),
            'is_featured' => false,
            'order' => fake()->numberBetween(0, 50),
            'location' => ['lt' => fake('lt_LT')->city(), 'en' => fake('lt_LT')->city()],
            'client_name' => fake()->company(),
            'completed_at' => fake()->dateTimeBetween('-1 year'),
        ];
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    /**
     * The real-world project is still in progress — distinct from
     * ProjectStatus, which is the admin's publishing workflow state (is
     * this content approved to show on the site at all), not whether the
     * work itself is finished.
     */
    public function ongoing(): static
    {
        return $this->state(fn () => ['completed_at' => null]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => ProjectStatus::Draft, 'published_at' => null]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultBlocks(): array
    {
        return [
            [
                'type' => 'heading',
                'data' => [
                    'text' => ['lt' => 'Projekto apžvalga', 'en' => 'Project overview'],
                    'level' => 'h2',
                ],
            ],
            [
                'type' => 'rich_text',
                'data' => [
                    'content' => [
                        'lt' => '<p>'.fake('lt_LT')->paragraph(6).'</p>',
                        'en' => '<p>'.fake('en_US')->paragraph(6).'</p>',
                    ],
                ],
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => [
                        'lt' => fake('lt_LT')->sentence(12),
                        'en' => fake('en_US')->sentence(12),
                    ],
                    'author' => fake()->name(),
                ],
            ],
        ];
    }
}

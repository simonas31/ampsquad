<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = ucfirst(fake()->unique()->words(3, true));

        return [
            'key' => Str::slug($title),
            'title' => ['lt' => $title, 'en' => $title],
            'slug' => ['lt' => Str::slug($title), 'en' => Str::slug($title)],
            'blocks' => [
                [
                    'type' => 'rich_text',
                    'data' => [
                        'content' => [
                            'lt' => '<p>'.fake('lt_LT')->paragraph(8).'</p>',
                            'en' => '<p>'.fake('en_US')->paragraph(8).'</p>',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function about(): static
    {
        return $this->state(fn () => [
            'key' => 'about',
            'title' => ['lt' => 'Apie mus', 'en' => 'About Us'],
            'slug' => ['lt' => 'apie-mus', 'en' => 'about'],
            'blocks' => [
                [
                    'type' => 'heading',
                    'data' => [
                        'text' => ['lt' => 'Elektros inžinerijos ekspertai', 'en' => 'Electrical engineering experts'],
                        'level' => 'h2',
                    ],
                ],
                [
                    'type' => 'rich_text',
                    'data' => [
                        'content' => [
                            'lt' => '<p>'.fake('lt_LT')->paragraph(10).'</p>',
                            'en' => '<p>'.fake('en_US')->paragraph(10).'</p>',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function privacyPolicy(): static
    {
        return $this->state(fn () => [
            'key' => 'privacy-policy',
            'title' => ['lt' => 'Privatumo politika', 'en' => 'Privacy Policy'],
            'slug' => ['lt' => 'privatumo-politika', 'en' => 'privacy-policy'],
            'blocks' => [
                [
                    'type' => 'rich_text',
                    'data' => [
                        'content' => [
                            'lt' => '<p>'.fake('lt_LT')->paragraph(12).'</p>',
                            'en' => '<p>'.fake('en_US')->paragraph(12).'</p>',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function termsAndConditions(): static
    {
        return $this->state(fn () => [
            'key' => 'terms-and-conditions',
            'title' => ['lt' => 'Taisyklės ir sąlygos', 'en' => 'Terms & Conditions'],
            'slug' => ['lt' => 'taisykles-ir-salygos', 'en' => 'terms-and-conditions'],
            'blocks' => [
                [
                    'type' => 'rich_text',
                    'data' => [
                        'content' => [
                            'lt' => '<p>'.fake('lt_LT')->paragraph(12).'</p>',
                            'en' => '<p>'.fake('en_US')->paragraph(12).'</p>',
                        ],
                    ],
                ],
            ],
        ]);
    }
}

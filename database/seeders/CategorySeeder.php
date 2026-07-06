<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ContentType;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['lt' => 'Baigti', 'en' => 'Completed', 'applies_to' => ContentType::Project],
            ['lt' => 'Vykdomi', 'en' => 'Ongoing', 'applies_to' => ContentType::Project],
            ['lt' => 'Naujienos', 'en' => 'News', 'applies_to' => ContentType::Article],
        ];

        foreach ($categories as $order => $category) {
            Category::query()->create([
                'applies_to' => $category['applies_to'],
                'order' => $order,
                'name' => ['lt' => $category['lt'], 'en' => $category['en']],
                'slug' => ['lt' => Str::slug($category['lt']), 'en' => Str::slug($category['en'])],
            ]);
        }
    }
}

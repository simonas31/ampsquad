<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['lt' => 'Baigti', 'en' => 'Completed'],
            ['lt' => 'Vykdomi', 'en' => 'Ongoing'],
        ];

        foreach ($categories as $order => $category) {
            Category::query()->create([
                'order' => $order,
                'name' => ['lt' => $category['lt'], 'en' => $category['en']],
            ]);
        }
    }
}

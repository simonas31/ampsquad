<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CalculatorCategory;
use Illuminate\Database\Seeder;

class CalculatorCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['lt' => 'Elektros instaliacija', 'en' => 'Electrical wiring', 'icon' => 'heroicon-o-bolt'],
            ['lt' => 'Saulės elektrinės', 'en' => 'Solar power systems', 'icon' => 'heroicon-o-sun'],
            ['lt' => 'Elektromobilių įkrovimas', 'en' => 'EV charging', 'icon' => 'heroicon-o-battery-100'],
        ];

        foreach ($categories as $order => $category) {
            CalculatorCategory::query()->create([
                'name' => ['lt' => $category['lt'], 'en' => $category['en']],
                'icon' => $category['icon'],
                'order' => $order,
            ])->options()->createMany([
                ['name' => ['lt' => 'Būsto tipas', 'en' => 'Property type'], 'order' => 0],
                ['name' => ['lt' => 'Ploto dydis', 'en' => 'Area size'], 'order' => 1],
            ]);
        }
    }
}

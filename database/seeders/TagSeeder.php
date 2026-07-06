<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            ['lt' => 'Gyvenamieji namai', 'en' => 'Residential'],
            ['lt' => 'Pramonė', 'en' => 'Industrial'],
            ['lt' => 'Komercija', 'en' => 'Commercial'],
            ['lt' => 'Saulės energija', 'en' => 'Solar'],
            ['lt' => 'Elektromobiliai', 'en' => 'EV Charging'],
            ['lt' => 'Išmanieji namai', 'en' => 'Smart Home'],
        ];

        foreach ($tags as $tag) {
            Tag::query()->create([
                'name' => ['lt' => $tag['lt'], 'en' => $tag['en']],
                'slug' => ['lt' => Str::slug($tag['lt']), 'en' => Str::slug($tag['en'])],
            ]);
        }
    }
}

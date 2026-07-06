<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Video;
use Illuminate\Database\Seeder;

class VideoSeeder extends Seeder
{
    public function run(): void
    {
        Video::factory()->count(4)->sequence(
            ['order' => 0],
            ['order' => 1],
            ['order' => 2],
            ['order' => 3],
        )->create();
    }
}

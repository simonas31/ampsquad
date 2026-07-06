<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        Page::factory()->about()->create();
        Page::factory()->privacyPolicy()->create();
        Page::factory()->termsAndConditions()->create();
    }
}

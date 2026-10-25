<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            CategorySeeder::class,
            TagSeeder::class,
            ProjectSeeder::class,
            PageSeeder::class,
            VideoSeeder::class,
            CapabilitySeeder::class,
            ProductSeeder::class,
        ]);

        // Thousands of rows and a minute or so of importing: for trying the
        // shop out, never for a server.
        if (app()->isLocal()) {
            $this->call(CatalogDemoSeeder::class);
        }
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Local-dev-only fixed credentials — this project has no public
     * registration route, so seeding is the only way to get a first admin.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@ampsquad.test'],
            [
                'name' => 'Admin',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );
    }
}

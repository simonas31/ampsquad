<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Local-dev-only fixed credentials, to sign in to the shop's account
     * area while developing. DatabaseSeeder only calls this locally.
     */
    public function run(): void
    {
        Customer::unguarded(fn () => Customer::query()->firstOrCreate(
            ['email' => 'customer@ampsquad.test'],
            [
                'full_name' => 'Demo Customer',
                'password' => 'password',
                'phone' => '+37060000000',
                'email_verified_at' => now(),
            ],
        ));
    }
}

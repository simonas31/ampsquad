<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public const string EMAIL = 'customer@ampsquad.test';

    /**
     * Local-dev-only fixed credentials, to sign in to the shop's account
     * area while developing. DatabaseSeeder only calls this locally.
     */
    public function run(): void
    {
        $customer = Customer::unguarded(fn () => Customer::query()->firstOrCreate(
            ['email' => self::EMAIL],
            [
                'full_name' => 'Demo Customer',
                'password' => 'password',
                'phone' => '+37060000000',
                'email_verified_at' => now(),
            ],
        ));

        if (! $customer->addresses()->exists()) {
            CustomerAddress::factory()->for($customer)->labelled('Home')->asDefault()->create();
            CustomerAddress::factory()->for($customer)->labelled('Office')->create();
        }
    }
}

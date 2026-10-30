<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use Database\Seeders\CustomerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_customer_is_verified_and_has_two_addresses_one_of_them_the_default(): void
    {
        $this->seed(CustomerSeeder::class);

        $customer = Customer::query()->where('email', 'customer@ampsquad.test')->sole();

        $this->assertTrue($customer->hasVerifiedEmail());
        $this->assertCount(2, $customer->addresses);
        $this->assertSame('Home', $customer->defaultShippingAddress->label);
    }

    public function test_seeding_again_changes_nothing(): void
    {
        $this->seed(CustomerSeeder::class);
        $this->seed(CustomerSeeder::class);

        $this->assertSame(1, Customer::query()->count());
        $this->assertSame(2, Customer::query()->sole()->addresses()->count());
    }
}

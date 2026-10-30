<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Database\Seeders\CustomerSeeder;
use Database\Seeders\OrderSeeder;
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

    public function test_the_seeded_orders_belong_to_the_demo_customer_but_earlier_ones_do_not(): void
    {
        Product::factory()->count(5)->inStock(100)->create();
        $earlier = Order::factory()->create();

        $this->seed(CustomerSeeder::class);
        $this->seed(OrderSeeder::class);

        $demo = Customer::query()->where('email', CustomerSeeder::EMAIL)->sole();

        $this->assertNull($earlier->fresh()->customer_id);
        $this->assertSame(7, $demo->orders()->count());
        $this->assertSame(0, Order::query()->where('id', '>', $earlier->getKey())->whereNull('customer_id')->count());
    }

    public function test_without_a_demo_customer_the_seeded_orders_are_guests(): void
    {
        Product::factory()->count(5)->inStock(100)->create();

        $this->seed(OrderSeeder::class);

        $this->assertSame(0, Order::query()->whereNotNull('customer_id')->count());
    }
}

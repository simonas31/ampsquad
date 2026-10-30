<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_customer_is_unverified_without_consent_and_writes_in_lithuanian(): void
    {
        $customer = Customer::query()->create([
            'full_name' => 'Jonas Jonaitis',
            'email' => 'jonas@example.com',
            'password' => 'a-long-enough-password',
        ])->fresh();

        $this->assertSame('lt', $customer->locale);
        $this->assertNull($customer->email_verified_at);
        $this->assertFalse($customer->hasVerifiedEmail());
        $this->assertNull($customer->marketing_consent_at);
        $this->assertNull($customer->pending_email);
    }

    public function test_the_password_is_hashed_and_never_serialised(): void
    {
        $customer = Customer::factory()->create(['password' => 'a-long-enough-password']);

        $this->assertNotSame('a-long-enough-password', $customer->password);
        $this->assertTrue(Hash::check('a-long-enough-password', $customer->password));
        $this->assertArrayNotHasKey('password', $customer->toArray());
        $this->assertArrayNotHasKey('remember_token', $customer->toArray());
    }

    public function test_only_the_profile_fields_can_be_mass_assigned(): void
    {
        $customer = Customer::query()->create([
            'full_name' => 'Jonas Jonaitis',
            'email' => 'jonas@example.com',
            'password' => 'a-long-enough-password',
            'email_verified_at' => now(),
            'marketing_consent_at' => now(),
            'pending_email' => 'other@example.com',
            'retained_email' => 'other@example.com',
        ])->fresh();

        $this->assertNull($customer->email_verified_at);
        $this->assertNull($customer->marketing_consent_at);
        $this->assertNull($customer->pending_email);
        $this->assertNull($customer->retained_email);
    }

    public function test_the_language_of_the_emails_is_the_customers_locale(): void
    {
        $this->assertSame('lt', Customer::factory()->create()->preferredLocale());
        $this->assertSame('en', Customer::factory()->inLocale('en')->create()->preferredLocale());
    }

    public function test_two_customers_cannot_share_an_email_address(): void
    {
        Customer::factory()->create(['email' => 'jonas@example.com']);

        $this->expectException(QueryException::class);

        Customer::factory()->create(['email' => 'jonas@example.com']);
    }

    public function test_deleted_accounts_do_not_hold_their_email_address(): void
    {
        $first = Customer::factory()->deleted()->create();
        Customer::factory()->deleted()->create();

        $again = Customer::factory()->create(['email' => $first->retained_email]);

        $this->assertNull($first->email);
        $this->assertNotNull($first->retained_email);
        $this->assertSame($first->retained_email, $again->email);
    }

    public function test_deleted_customers_are_left_out_of_queries_unless_asked_for(): void
    {
        $kept = Customer::factory()->create();
        $deleted = Customer::factory()->deleted()->create();

        $this->assertSame([$kept->getKey()], Customer::query()->pluck('id')->all());
        $this->assertEqualsCanonicalizing(
            [$kept->getKey(), $deleted->getKey()],
            Customer::query()->withTrashed()->pluck('id')->all(),
        );
    }

    public function test_a_customer_has_orders_and_an_order_has_its_customer(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->getKey()]);
        Order::factory()->create();

        $this->assertTrue($customer->orders->contains($order));
        $this->assertCount(1, $customer->orders);
        $this->assertTrue($order->customer->is($customer));
    }

    public function test_a_guest_order_has_no_customer(): void
    {
        $order = Order::factory()->create();

        $this->assertNull($order->customer_id);
        $this->assertNull($order->customer);
    }

    public function test_an_order_still_finds_its_customer_after_the_account_is_deleted(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->getKey()]);

        $customer->delete();

        $this->assertTrue($order->fresh()->customer->is($customer));
        $this->assertTrue($order->fresh()->customer->trashed());
    }

    public function test_removing_a_customer_for_good_leaves_their_orders_without_an_owner(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->getKey()]);

        $customer->forceDelete();

        $this->assertModelMissing($customer);
        $this->assertModelExists($order);
        $this->assertNull($order->fresh()->customer_id);
    }

    public function test_the_prune_command_removes_stale_unverified_accounts_only(): void
    {
        $stale = Customer::factory()->unverified()->create(['created_at' => now()->subDays(Customer::UNVERIFIED_LIFETIME_DAYS)->subMinute()]);
        $recent = Customer::factory()->unverified()->create(['created_at' => now()->subDays(Customer::UNVERIFIED_LIFETIME_DAYS)->addHour()]);
        $oldButVerified = Customer::factory()->create(['created_at' => now()->subYears(5)]);

        $this->artisan('model:prune', ['--model' => [Customer::class]])->assertSuccessful();

        $this->assertModelMissing($stale);
        $this->assertModelExists($recent);
        $this->assertModelExists($oldButVerified);
    }

    public function test_the_prune_command_removes_deleted_accounts_only_after_the_retention_period(): void
    {
        $expired = Customer::factory()->deleted(now()->subYears(Customer::RETENTION_YEARS)->subDay())->create();
        $retained = Customer::factory()->deleted(now()->subYears(Customer::RETENTION_YEARS)->addDay())->create();
        $order = Order::factory()->create(['customer_id' => $expired->getKey()]);

        $this->artisan('model:prune', ['--model' => [Customer::class]])->assertSuccessful();

        $this->assertModelMissing($expired);
        $this->assertModelExists($retained);
        $this->assertModelExists($order);
        $this->assertNull($order->fresh()->customer_id);
    }
}

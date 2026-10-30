<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Customers and the people who work in the admin panel are two separate
 * kinds of account: signing in as one never signs anyone in as the other.
 */
class CustomerGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_is_signed_in_on_the_customer_guard_only(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer');

        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertGuest('web');
    }

    public function test_an_admin_is_signed_in_on_the_web_guard_only(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertGuest('customer');
    }

    public function test_a_customer_cannot_open_the_admin_panel(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer');

        $this->get('/admin')->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_a_customers_credentials_do_not_sign_in_to_the_admin_panel(): void
    {
        $customer = Customer::factory()->create();

        $this->assertFalse(Auth::guard('web')->validate(['email' => $customer->email, 'password' => 'password']));
        $this->assertTrue(Auth::guard('customer')->validate(['email' => $customer->email, 'password' => 'password']));
    }

    public function test_an_admins_credentials_do_not_sign_in_a_customer(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(Auth::guard('customer')->validate(['email' => $user->email, 'password' => 'password']));
        $this->assertTrue(Auth::guard('web')->validate(['email' => $user->email, 'password' => 'password']));
    }

    public function test_a_customer_and_an_admin_may_share_an_email_address(): void
    {
        $user = User::factory()->create(['email' => 'same@example.com']);
        $customer = Customer::factory()->create(['email' => 'same@example.com']);

        $this->assertTrue(Auth::guard('web')->validate(['email' => 'same@example.com', 'password' => 'password']));
        $this->assertTrue(Auth::guard('customer')->validate(['email' => 'same@example.com', 'password' => 'password']));
        $this->assertNotSame($user->getAuthIdentifier(), $customer->getAuthIdentifier());
    }

    public function test_a_deleted_customer_cannot_sign_in(): void
    {
        $customer = Customer::factory()->create();
        $credentials = ['email' => $customer->email, 'password' => 'password'];

        $customer->delete();

        $this->assertFalse(Auth::guard('customer')->validate($credentials));
        $this->assertFalse(Auth::guard('customer')->validate(['email' => $customer->retained_email, 'password' => 'password']));
    }

    public function test_a_deleted_customer_stored_the_way_the_delete_leaves_it_cannot_sign_in(): void
    {
        $customer = Customer::factory()->deleted()->create();

        $this->assertNull($customer->email);
        $this->assertFalse(Auth::guard('customer')->validate(['email' => $customer->retained_email, 'password' => 'password']));
    }

    public function test_customers_have_their_own_password_reset_tokens(): void
    {
        $customer = Customer::factory()->create();

        Password::broker('customers')->createToken($customer);

        $this->assertSame(1, DB::table('customer_password_reset_tokens')->where('email', $customer->email)->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    public function test_the_password_broker_for_customers_does_not_find_an_admin(): void
    {
        $user = User::factory()->create();

        $this->assertNull(Password::broker('customers')->getUser(['email' => $user->email]));
    }
}

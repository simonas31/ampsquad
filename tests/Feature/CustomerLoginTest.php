<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CustomerLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_page_opens_and_is_not_stored(): void
    {
        $response = $this->get('/login')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/Login', false)
            ->where('submitUrl', '/login')
            ->where('seo.noindex', true)
            ->where('seo.title', 'Prisijungti - '.config('app.name'))
        );

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_a_customer_can_log_in(): void
    {
        $customer = Customer::factory()->create();

        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])
            ->assertRedirect(route('account.dashboard'));

        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertGuest('web');
    }

    public function test_the_email_is_not_case_sensitive(): void
    {
        $customer = Customer::factory()->create(['email' => 'jonas@example.com']);

        $this->post('/login', ['email' => ' JONAS@example.com ', 'password' => 'password'])->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $customer = Customer::factory()->create();

        $this->from('/login')->post('/login', ['email' => $customer->email, 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Neteisingas el. paštas arba slaptažodis.']);

        $this->assertGuest('customer');
    }

    public function test_an_unknown_address_gets_the_same_answer_as_a_wrong_password(): void
    {
        $customer = Customer::factory()->create();

        $unknown = $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $unknownMessage = session('errors')->first('email');

        $wrong = $this->post('/login', ['email' => $customer->email, 'password' => 'wrong'])->assertSessionHasErrors('email');

        $this->assertSame($unknownMessage, session('errors')->first('email'));
    }

    public function test_the_error_is_in_the_language_of_the_page(): void
    {
        app()->setLocale('en');

        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
    }

    public function test_a_deleted_customer_cannot_log_in(): void
    {
        $customer = Customer::factory()->create();
        $email = $customer->email;
        $customer->delete();

        $this->post('/login', ['email' => $email, 'password' => 'password'])->assertSessionHasErrors('email');

        $this->assertGuest('customer');
    }

    public function test_an_admin_cannot_log_in_as_a_customer(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');

        $this->assertGuest('customer');
    }

    public function test_the_login_form_needs_both_fields(): void
    {
        $this->post('/login', [])->assertSessionHasErrors(['email', 'password']);
    }

    public function test_remember_me_leaves_a_cookie_that_keeps_them_logged_in(): void
    {
        $customer = Customer::factory()->create();

        $without = $this->post('/login', ['email' => $customer->email, 'password' => 'password']);
        $this->assertEmpty(collect($without->headers->getCookies())->filter(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_customer_')));

        $this->app['auth']->guard('customer')->logout();

        $with = $this->post('/login', ['email' => $customer->email, 'password' => 'password', 'remember' => true]);
        $this->assertCount(1, collect($with->headers->getCookies())->filter(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_customer_')));
    }

    public function test_five_wrong_tries_lock_that_address_for_a_while(): void
    {
        $customer = Customer::factory()->create();

        foreach (range(1, 5) as $try) {
            $this->post('/login', ['email' => $customer->email, 'password' => 'wrong'])
                ->assertSessionHasErrors(['email' => 'Neteisingas el. paštas arba slaptažodis.']);
        }

        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString('Per daug prisijungimo bandymų', session('errors')->first('email'));
        $this->assertGuest('customer');
    }

    public function test_a_lock_on_one_address_leaves_the_others_alone(): void
    {
        $locked = Customer::factory()->create();
        $other = Customer::factory()->create();

        foreach (range(1, 5) as $try) {
            $this->post('/login', ['email' => $locked->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $other->email, 'password' => 'password'])->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($other, 'customer');
    }

    public function test_a_customer_is_taken_back_to_the_page_they_were_turned_away_from(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $this->get('/account/verify-email')->assertRedirect(route('customer.login'));

        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])
            ->assertRedirect(route('customer.verification.notice'));
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get('/account')->assertRedirect('/login');
    }

    public function test_a_signed_in_customer_is_sent_to_their_account_instead_of_the_login_page(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer');

        $this->get('/login')->assertRedirect(route('account.dashboard'));
    }

    public function test_someone_signed_in_to_the_admin_panel_still_sees_the_login_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/login')->assertOk();
    }

    public function test_a_customer_can_log_out(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer');

        $this->post('/logout')->assertRedirect(route('home'));

        $this->assertGuest('customer');
    }

    public function test_logging_out_does_not_sign_out_the_admin_panel(): void
    {
        $this->actingAs(User::factory()->create());
        $this->actingAs(Customer::factory()->create(), 'customer');

        $this->post('/logout')->assertRedirect();

        $this->assertGuest('customer');
        $this->assertAuthenticated('web');
    }

    public function test_a_guest_cannot_log_out(): void
    {
        $this->post('/logout')->assertRedirect(route('customer.login'));
    }

    public function test_the_header_is_told_who_is_signed_in(): void
    {
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('account.customer', null)
            ->where('account.loginUrl', '/login')
            ->where('account.registerUrl', '/register')
            ->where('account.dashboardUrl', '/account')
            ->where('account.logoutUrl', '/logout')
        );

        $customer = Customer::factory()->create(['full_name' => 'Jonas Jonaitis']);
        $this->actingAs($customer, 'customer');

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('account.customer.fullName', 'Jonas Jonaitis')
            ->where('account.customer.email', $customer->email)
            ->where('account.customer.isVerified', true)
        );
    }

    public function test_a_guest_costs_the_shared_account_prop_no_query(): void
    {
        $this->get('/');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/login');
        $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => str_contains($sql, '`customers`'));
        DB::disableQueryLog();

        $this->assertCount(0, $queries);
    }
}

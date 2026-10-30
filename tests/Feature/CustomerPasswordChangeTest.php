<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\CustomerPasswordChangedMail;
use App\Models\Customer;
use App\Models\User;
use App\Services\Customers\CustomerAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CustomerPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'current_password' => 'password',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ], $overrides);
    }

    /**
     * A change made by some other request. The guard keeps the customer it
     * loaded for the last request, as it never does between two real
     * requests, so it is made to forget them.
     */
    private function changePasswordFromAnotherBrowser(Customer $customer): void
    {
        app(CustomerAccountService::class)->changePassword($customer->fresh(), 'brand-new-password');

        Auth::forgetGuards();
    }

    public function test_the_security_page_opens_for_a_verified_customer(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer');

        $this->get('/account/security')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Account/Security', false)
            ->where('pendingEmail', null)
            ->where('emailUrl', '/account/email')
            ->where('passwordUrl', '/account/password')
            ->where('seo.noindex', true)
        );
    }

    public function test_the_security_page_is_closed_to_guests_and_unverified_customers(): void
    {
        $this->get('/account/security')->assertRedirect(route('customer.login'));
        $this->put('/account/password', $this->payload())->assertRedirect(route('customer.login'));

        $this->actingAs(Customer::factory()->unverified()->create(), 'customer');

        $this->get('/account/security')->assertRedirect(route('customer.verification.notice'));
        $this->put('/account/password', $this->payload())->assertRedirect(route('customer.verification.notice'));
    }

    public function test_the_password_is_changed_and_the_customer_is_logged_out_to_log_in_again(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create();
        $oldToken = $customer->remember_token;
        $this->actingAs($customer, 'customer');

        $this->put('/account/password', $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('customer.login'));

        $customer->refresh();

        $this->assertGuest('customer');
        $this->assertTrue(Hash::check('brand-new-password', $customer->password));
        $this->assertNotSame($oldToken, $customer->remember_token);
    }

    public function test_the_login_page_says_why_the_customer_is_logged_out(): void
    {
        Mail::fake();
        $this->actingAs(Customer::factory()->create(), 'customer');

        $this->followingRedirects()->put('/account/password', $this->payload())
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/Login', false)
                ->hasFlash('accountStatus', 'password-changed')
            );
    }

    public function test_the_customer_is_told_by_mail_in_their_language(): void
    {
        Mail::fake();
        $customer = Customer::factory()->inLocale('en')->create();
        $this->actingAs($customer, 'customer');

        $this->put('/account/password', $this->payload());

        Mail::assertQueued(CustomerPasswordChangedMail::class, 1);
        Mail::assertQueued(CustomerPasswordChangedMail::class, fn (CustomerPasswordChangedMail $mail) => $mail->hasTo($customer->email) && $mail->locale === 'en');
    }

    public function test_the_current_password_must_be_right(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $this->put('/account/password', $this->payload(['current_password' => 'not-my-password']))
            ->assertSessionHasErrors(['current_password' => 'Neteisingas slaptažodis.']);

        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertTrue(Hash::check('password', $customer->fresh()->password));
        Mail::assertNothingQueued();
    }

    public function test_the_new_password_must_be_new_long_enough_and_repeated(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer');

        $this->put('/account/password', $this->payload(['password' => 'password', 'password_confirmation' => 'password']))
            ->assertSessionHasErrors('password');
        $this->put('/account/password', $this->payload(['password' => 'too-short', 'password_confirmation' => 'too-short']))
            ->assertSessionHasErrors('password');
        $this->put('/account/password', $this->payload(['password_confirmation' => 'something-else-entirely']))
            ->assertSessionHasErrors('password');
        $this->put('/account/password', [])->assertSessionHasErrors(['current_password', 'password']);
    }

    public function test_the_form_is_limited_to_a_few_tries_a_minute(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer');

        foreach (range(1, 5) as $try) {
            $this->put('/account/password', $this->payload(['current_password' => 'wrong']))->assertSessionHasErrors();
        }

        $this->put('/account/password', $this->payload())->assertTooManyRequests();
    }

    public function test_another_browser_is_logged_out_when_the_password_changes(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create();

        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])->assertRedirect();
        $this->get('/account')->assertOk();

        // The change is made from some other browser.
        $this->changePasswordFromAnotherBrowser($customer);

        $this->get('/account')->assertRedirect(route('customer.login'));
        $this->assertGuest('customer');
    }

    public function test_a_session_that_never_made_a_request_is_ended_too(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create();

        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])->assertRedirect();

        $this->changePasswordFromAnotherBrowser($customer);

        $this->get('/account')->assertRedirect(route('customer.login'));
        $this->assertGuest('customer');
    }

    public function test_a_customer_whose_password_did_not_change_stays_logged_in(): void
    {
        $customer = Customer::factory()->create();

        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])->assertRedirect();

        $this->get('/account')->assertOk();
        $this->get('/account/security')->assertOk();
        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_ending_a_customers_session_leaves_the_admin_panel_session_alone(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create();
        $admin = User::factory()->create();
        // Signed in the way a browser is, through the session, so it survives the guards being reset.
        $this->withSession([Auth::guard('web')->getName() => $admin->getKey()]);

        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])->assertRedirect();
        $this->changePasswordFromAnotherBrowser($customer);

        $this->get('/account')->assertRedirect(route('customer.login'));

        $this->assertGuest('customer');
        $this->assertAuthenticatedAs($admin, 'web');
    }
}

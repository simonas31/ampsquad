<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\CustomerPasswordChangedMail;
use App\Mail\CustomerPasswordResetMail;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CustomerPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(Customer $customer): string
    {
        return Password::broker('customers')->createToken($customer);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function resetPayload(Customer $customer, string $token, array $overrides = []): array
    {
        return array_merge([
            'token' => $token,
            'email' => $customer->email,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ], $overrides);
    }

    public function test_the_forgot_password_page_opens_and_the_login_page_links_to_it(): void
    {
        $this->get('/forgot-password')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/ForgotPassword', false)
            ->where('submitUrl', '/forgot-password')
            ->where('seo.noindex', true)
        );

        $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('forgotPasswordUrl', '/forgot-password')
        );
    }

    public function test_a_customer_is_mailed_a_link_in_their_language(): void
    {
        Mail::fake();
        $customer = Customer::factory()->inLocale('en')->create();

        $this->from('/forgot-password')->followingRedirects()->post('/forgot-password', ['email' => $customer->email])
            ->assertInertia(fn (AssertableInertia $page) => $page->hasFlash('accountStatus', 'reset-link-sent'));

        Mail::assertQueued(CustomerPasswordResetMail::class, 1);
        Mail::assertQueued(CustomerPasswordResetMail::class, fn (CustomerPasswordResetMail $mail) => $mail->hasTo($customer->email) && $mail->locale === 'en');
    }

    public function test_the_answer_is_the_same_for_an_address_with_no_account(): void
    {
        Mail::fake();

        $this->from('/forgot-password')->followingRedirects()->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertInertia(fn (AssertableInertia $page) => $page->hasFlash('accountStatus', 'reset-link-sent'));

        Mail::assertNothingQueued();
    }

    public function test_a_deleted_account_is_not_mailed(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create();
        $email = $customer->email;
        $customer->delete();

        $this->post('/forgot-password', ['email' => $email])->assertSessionHasNoErrors();

        Mail::assertNothingQueued();
    }

    public function test_the_address_is_matched_whatever_its_case(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create(['email' => 'jonas@example.com']);

        $this->post('/forgot-password', ['email' => ' JONAS@Example.com '])->assertSessionHasNoErrors();

        Mail::assertQueued(CustomerPasswordResetMail::class, fn (CustomerPasswordResetMail $mail) => $mail->hasTo($customer->email));
    }

    public function test_the_address_must_be_an_email_address(): void
    {
        $this->post('/forgot-password', ['email' => 'not-an-email'])->assertSessionHasErrors('email');
        $this->post('/forgot-password', [])->assertSessionHasErrors('email');
    }

    public function test_asking_for_links_is_limited(): void
    {
        Mail::fake();

        foreach (range(1, 3) as $try) {
            $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertRedirect();
        }

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertTooManyRequests();
    }

    public function test_the_mail_links_to_the_reset_page_in_the_customers_language(): void
    {
        $lithuanian = new CustomerPasswordResetMail(Customer::factory()->create(['email' => 'jonas@example.com']), 'the-token');
        $english = new CustomerPasswordResetMail(Customer::factory()->inLocale('en')->create(['email' => 'john@example.com']), 'the-token');

        // Whatever host the application runs on (APP_URL), not a fixed one.
        $root = rtrim(url('/'), '/');

        $this->assertSame($root.'/reset-password/the-token?email=jonas%40example.com', $lithuanian->resetUrl());
        $this->assertSame($root.'/en/reset-password/the-token?email=john%40example.com', $english->resetUrl());
        $this->assertStringContainsString($lithuanian->resetUrl(), $lithuanian->render());
    }

    public function test_the_reset_page_is_given_the_token_and_the_address_and_keeps_the_token_to_itself(): void
    {
        $response = $this->get('/reset-password/the-token?email=Jonas@Example.com')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/ResetPassword', false)
            ->where('token', 'the-token')
            ->where('email', 'jonas@example.com')
            ->where('submitUrl', '/reset-password')
            ->where('seo.noindex', true)
        );

        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_a_signed_in_customer_has_no_use_for_the_reset_page(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer');

        $this->get('/reset-password/the-token')->assertRedirect(route('account.dashboard'));
    }

    public function test_the_link_lets_the_customer_choose_a_new_password(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create();
        $oldToken = $customer->remember_token;

        $this->post('/reset-password', $this->resetPayload($customer, $this->tokenFor($customer)))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('customer.login'));

        $customer->refresh();

        $this->assertTrue(Hash::check('brand-new-password', $customer->password));
        $this->assertNotSame($oldToken, $customer->remember_token);
        $this->assertGuest('customer');
        $this->assertFalse(Auth::guard('customer')->validate(['email' => $customer->email, 'password' => 'password']));
        $this->assertTrue(Auth::guard('customer')->validate(['email' => $customer->email, 'password' => 'brand-new-password']));
    }

    public function test_the_customer_is_told_by_mail_and_on_the_login_page(): void
    {
        Mail::fake();
        $customer = Customer::factory()->inLocale('en')->create();

        $this->followingRedirects()->post('/reset-password', $this->resetPayload($customer, $this->tokenFor($customer)))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/Login', false)
                ->hasFlash('accountStatus', 'password-reset')
            );

        Mail::assertQueued(CustomerPasswordChangedMail::class, 1);
        Mail::assertQueued(CustomerPasswordChangedMail::class, fn (CustomerPasswordChangedMail $mail) => $mail->hasTo($customer->email) && $mail->locale === 'en');
    }

    public function test_a_link_works_once(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create();
        $token = $this->tokenFor($customer);

        $this->post('/reset-password', $this->resetPayload($customer, $token))->assertSessionHasNoErrors();
        $this->post('/reset-password', $this->resetPayload($customer, $token, ['password' => 'another-new-password', 'password_confirmation' => 'another-new-password']))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('brand-new-password', $customer->fresh()->password));
    }

    public function test_a_wrong_token_changes_nothing(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create();
        $this->tokenFor($customer);

        $this->post('/reset-password', $this->resetPayload($customer, 'not-the-token'))
            ->assertSessionHasErrors(['email' => 'Ši slaptažodžio atstatymo nuoroda negalioja arba jau panaudota.']);

        $this->assertTrue(Hash::check('password', $customer->fresh()->password));
        Mail::assertNothingQueued();
    }

    public function test_an_address_with_no_account_gets_the_same_answer_as_a_wrong_token(): void
    {
        $this->post('/reset-password', [
            'token' => 'whatever',
            'email' => 'nobody@example.com',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertSessionHasErrors(['email' => 'Ši slaptažodžio atstatymo nuoroda negalioja arba jau panaudota.']);
    }

    public function test_a_link_cannot_reset_another_customers_password(): void
    {
        Mail::fake();
        $first = Customer::factory()->create();
        $second = Customer::factory()->create();

        $this->post('/reset-password', $this->resetPayload($second, $this->tokenFor($first)))->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $second->fresh()->password));
    }

    public function test_a_link_stops_working_after_an_hour(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create();
        $token = $this->tokenFor($customer);

        $this->travel(61)->minutes();

        $this->post('/reset-password', $this->resetPayload($customer, $token))->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $customer->fresh()->password));
    }

    public function test_the_new_password_must_be_long_enough_and_repeated_and_a_mistake_keeps_the_link_alive(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create();
        $token = $this->tokenFor($customer);

        $this->post('/reset-password', $this->resetPayload($customer, $token, ['password' => 'too-short', 'password_confirmation' => 'too-short']))
            ->assertSessionHasErrors('password');
        $this->post('/reset-password', $this->resetPayload($customer, $token, ['password_confirmation' => 'something-else-entirely']))
            ->assertSessionHasErrors('password');
        $this->post('/reset-password', [])->assertSessionHasErrors(['token', 'email', 'password']);

        $this->post('/reset-password', $this->resetPayload($customer, $token))->assertSessionHasNoErrors();
    }

    public function test_a_reset_does_not_verify_an_address(): void
    {
        Mail::fake();
        $customer = Customer::factory()->unverified()->create();

        $this->post('/reset-password', $this->resetPayload($customer, $this->tokenFor($customer)))->assertSessionHasNoErrors();

        $this->assertFalse($customer->fresh()->hasVerifiedEmail());
    }
}

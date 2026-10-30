<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\VerifyCustomerEmailMail;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CustomerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Jonas Jonaitis',
            'email' => 'jonas@example.com',
            'phone' => '+37060000000',
            'password' => 'a-long-enough-password',
            'password_confirmation' => 'a-long-enough-password',
            'marketing_consent' => false,
            'company' => '',
        ], $overrides);
    }

    public function test_the_register_page_opens(): void
    {
        $this->get('/register')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/Register', false)
            ->where('locale.current', 'lt')
            ->where('submitUrl', '/register')
            ->where('seo.noindex', true)
            ->where('seo.title', 'Sukurti paskyrą - '.config('app.name'))
        );
    }

    /**
     * A real "/en/register" 404s in the test harness (see HomeTest), so the
     * language is set directly, as there.
     */
    public function test_the_register_page_opens_in_english(): void
    {
        app()->setLocale('en');

        $this->get('/register')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/Register', false)
            ->where('locale.current', 'en')
            ->where('seo.title', 'Create an account - '.config('app.name'))
        );
    }

    public function test_a_visitor_can_register_and_is_signed_in_but_not_verified(): void
    {
        Mail::fake();

        $this->post('/register', $this->validPayload())->assertRedirect(route('customer.verification.notice'));

        $customer = Customer::query()->where('email', 'jonas@example.com')->sole();

        $this->assertSame('Jonas Jonaitis', $customer->full_name);
        $this->assertSame('+37060000000', $customer->phone);
        $this->assertSame('lt', $customer->locale);
        $this->assertNull($customer->email_verified_at);
        $this->assertNull($customer->marketing_consent_at);
        $this->assertNotSame('a-long-enough-password', $customer->password);
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertGuest('web');
    }

    public function test_the_verification_mail_is_queued_in_lithuanian_on_the_lithuanian_site(): void
    {
        Mail::fake();

        $this->post('/register', $this->validPayload())->assertRedirect();

        Mail::assertQueued(VerifyCustomerEmailMail::class, 1);
        Mail::assertQueued(VerifyCustomerEmailMail::class, fn (VerifyCustomerEmailMail $mail) => $mail->hasTo('jonas@example.com') && $mail->locale === 'lt');
    }

    public function test_the_account_and_its_mail_are_in_english_on_the_english_site(): void
    {
        Mail::fake();
        app()->setLocale('en');

        $this->post('/register', $this->validPayload())->assertRedirect();

        Mail::assertQueued(VerifyCustomerEmailMail::class, fn (VerifyCustomerEmailMail $mail) => $mail->hasTo('jonas@example.com') && $mail->locale === 'en');
        $this->assertSame('en', Customer::query()->where('email', 'jonas@example.com')->value('locale'));
    }

    public function test_marketing_consent_is_recorded_only_when_given(): void
    {
        Mail::fake();

        $this->post('/register', $this->validPayload(['marketing_consent' => true]))->assertRedirect();

        $this->assertNotNull(Customer::query()->where('email', 'jonas@example.com')->value('marketing_consent_at'));
    }

    public function test_the_phone_is_optional(): void
    {
        Mail::fake();

        $this->post('/register', $this->validPayload(['phone' => '']))->assertRedirect(route('customer.verification.notice'));

        $this->assertNull(Customer::query()->where('email', 'jonas@example.com')->value('phone'));
    }

    public function test_the_email_is_kept_in_lower_case_without_spaces(): void
    {
        Mail::fake();

        $this->post('/register', $this->validPayload(['email' => '  Jonas@Example.COM ']))->assertRedirect();

        $this->assertSame(1, Customer::query()->where('email', 'jonas@example.com')->count());
    }

    public function test_nothing_but_the_asked_for_fields_can_set_up_the_account(): void
    {
        Mail::fake();

        $this->post('/register', $this->validPayload([
            'email_verified_at' => now()->toDateTimeString(),
            'locale' => 'en',
            'pending_email' => 'other@example.com',
            'id' => 999,
        ]))->assertRedirect();

        $customer = Customer::query()->where('email', 'jonas@example.com')->sole();

        $this->assertNull($customer->email_verified_at);
        $this->assertSame('lt', $customer->locale);
        $this->assertNull($customer->pending_email);
        $this->assertNotSame(999, $customer->getKey());
    }

    public function test_the_required_fields_are_required(): void
    {
        $this->post('/register', [])->assertSessionHasErrors(['full_name', 'email', 'password']);

        $this->assertSame(0, Customer::query()->count());
    }

    public function test_a_password_of_nine_characters_is_too_short(): void
    {
        $this->post('/register', $this->validPayload(['password' => 'too-short', 'password_confirmation' => 'too-short']))
            ->assertSessionHasErrors('password');

        $this->assertSame(0, Customer::query()->count());
    }

    public function test_a_password_of_ten_characters_is_long_enough(): void
    {
        Mail::fake();

        $this->post('/register', $this->validPayload(['password' => 'short-pass', 'password_confirmation' => 'short-pass']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Customer::query()->count());
    }

    public function test_the_password_must_be_repeated(): void
    {
        $this->post('/register', $this->validPayload(['password_confirmation' => 'something-else-entirely']))
            ->assertSessionHasErrors('password');

        $this->assertSame(0, Customer::query()->count());
    }

    public function test_an_address_that_has_an_account_cannot_register_again(): void
    {
        Mail::fake();
        Customer::factory()->create(['email' => 'jonas@example.com']);

        $this->post('/register', $this->validPayload(['email' => 'Jonas@example.com']))->assertSessionHasErrors('email');

        $this->assertSame(1, Customer::query()->count());
    }

    public function test_the_address_of_a_deleted_account_can_register_again(): void
    {
        Mail::fake();
        $deleted = Customer::factory()->deleted()->create();

        $this->post('/register', $this->validPayload(['email' => $deleted->retained_email]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('customer.verification.notice'));

        $this->assertSame(1, Customer::query()->count());
        $this->assertSame(2, Customer::query()->withTrashed()->count());
    }

    public function test_the_phone_must_look_like_a_phone_number(): void
    {
        $this->post('/register', $this->validPayload(['phone' => 'call me maybe']))->assertSessionHasErrors('phone');
    }

    public function test_a_filled_in_honeypot_registers_nobody(): void
    {
        Mail::fake();

        $this->post('/register', $this->validPayload(['company' => 'Spam Ltd']))->assertSessionHasErrors('company');

        $this->assertSame(0, Customer::query()->count());
        Mail::assertNothingQueued();
    }

    public function test_a_signed_in_customer_is_sent_to_their_account_instead(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer');

        $this->get('/register')->assertRedirect(route('account.dashboard'));
        $this->post('/register', $this->validPayload())->assertRedirect(route('account.dashboard'));
    }

    public function test_someone_signed_in_to_the_admin_panel_can_still_register_as_a_customer(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->create());

        $this->get('/register')->assertOk();
        $this->post('/register', $this->validPayload())->assertRedirect(route('customer.verification.notice'));

        $this->assertAuthenticated('web');
        $this->assertAuthenticated('customer');
    }

    public function test_registering_is_limited_to_a_few_tries_a_minute(): void
    {
        foreach (range(1, 3) as $try) {
            $this->post('/register', ['company' => 'x'])->assertSessionHasErrors();
        }

        $this->post('/register', ['company' => 'x'])->assertTooManyRequests();
    }
}

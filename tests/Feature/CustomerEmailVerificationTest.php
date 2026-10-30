<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\VerifyCustomerEmailMail;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CustomerEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function linkFor(Customer $customer): string
    {
        return (new VerifyCustomerEmailMail($customer))->verificationUrl();
    }

    public function test_an_unverified_customer_is_asked_to_verify_their_address(): void
    {
        $customer = Customer::factory()->unverified()->create();
        $this->actingAs($customer, 'customer');

        $this->get('/account/verify-email')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/VerifyEmail', false)
            ->where('resendUrl', '/account/verify-email/resend')
            ->where('account.customer.email', $customer->email)
            ->where('account.customer.isVerified', false)
            ->where('seo.noindex', true)
        );
    }

    public function test_the_account_area_stays_closed_until_the_address_is_verified(): void
    {
        $this->actingAs(Customer::factory()->unverified()->create(), 'customer');

        $this->get('/account')->assertRedirect(route('customer.verification.notice'));
    }

    public function test_a_verified_customer_reaches_their_account_and_not_the_waiting_page(): void
    {
        $this->actingAs(Customer::factory()->create(['full_name' => 'Jonas Jonaitis']), 'customer');

        $this->get('/account')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Account/Dashboard', false)
            ->where('account.customer.fullName', 'Jonas Jonaitis')
            ->where('seo.noindex', true)
        );

        $this->get('/account/verify-email')->assertRedirect(route('account.dashboard'));
    }

    public function test_a_guest_cannot_open_the_waiting_page(): void
    {
        $this->get('/account/verify-email')->assertRedirect(route('customer.login'));
        $this->post('/account/verify-email/resend')->assertRedirect(route('customer.login'));
    }

    public function test_the_link_can_be_sent_again(): void
    {
        Mail::fake();
        $customer = Customer::factory()->unverified()->inLocale('en')->create();
        $this->actingAs($customer, 'customer');

        $this->from('/account/verify-email')->post('/account/verify-email/resend')->assertRedirect('/account/verify-email');

        Mail::assertQueued(VerifyCustomerEmailMail::class, fn (VerifyCustomerEmailMail $mail) => $mail->hasTo($customer->email) && $mail->locale === 'en');
    }

    public function test_a_verified_customer_is_not_sent_the_link_again(): void
    {
        Mail::fake();
        $this->actingAs(Customer::factory()->create(), 'customer');

        $this->post('/account/verify-email/resend')->assertRedirect(route('account.dashboard'));

        Mail::assertNothingQueued();
    }

    public function test_sending_the_link_again_is_limited(): void
    {
        Mail::fake();
        $this->actingAs(Customer::factory()->unverified()->create(), 'customer');

        foreach (range(1, 6) as $try) {
            $this->post('/account/verify-email/resend')->assertRedirect();
        }

        $this->post('/account/verify-email/resend')->assertTooManyRequests();
    }

    public function test_the_link_verifies_the_address_and_sends_the_customer_to_log_in(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $this->get($this->linkFor($customer))->assertRedirect('/login');

        $this->assertTrue($customer->fresh()->hasVerifiedEmail());
        $this->assertGuest('customer');
    }

    public function test_the_customer_is_sent_on_in_the_language_they_registered_in(): void
    {
        $customer = Customer::factory()->unverified()->inLocale('en')->create();

        $this->get($this->linkFor($customer))->assertRedirect('/en/login');
    }

    public function test_a_customer_who_is_already_signed_in_goes_straight_to_their_account(): void
    {
        $customer = Customer::factory()->unverified()->create();
        $this->actingAs($customer, 'customer');

        $this->get($this->linkFor($customer))->assertRedirect('/account');

        $this->assertTrue($customer->fresh()->hasVerifiedEmail());
    }

    public function test_the_login_page_says_the_address_was_verified(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $this->followingRedirects()->get($this->linkFor($customer))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/Login', false)
            ->hasFlash('accountStatus', 'email-verified')
        );
    }

    public function test_the_link_has_no_language_in_it(): void
    {
        $customer = Customer::factory()->unverified()->inLocale('en')->create();

        $this->assertStringContainsString('/account/verify-email/'.$customer->getKey().'/', $this->linkFor($customer));
        $this->assertStringNotContainsString('/en/', $this->linkFor($customer));
    }

    public function test_following_the_link_twice_changes_nothing(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $this->get($this->linkFor($customer))->assertRedirect();
        $verifiedAt = $customer->fresh()->email_verified_at;

        $this->travel(5)->minutes();
        $this->get($this->linkFor($customer))->assertRedirect();

        $this->assertEquals($verifiedAt, $customer->fresh()->email_verified_at);
    }

    public function test_a_link_with_a_changed_signature_does_nothing(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $this->get($this->linkFor($customer).'0')->assertForbidden();

        $this->assertFalse($customer->fresh()->hasVerifiedEmail());
    }

    public function test_a_link_without_a_signature_does_nothing(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $this->get('/account/verify-email/'.$customer->getKey().'/'.sha1($customer->email))->assertForbidden();

        $this->assertFalse($customer->fresh()->hasVerifiedEmail());
    }

    public function test_a_link_stops_working_after_its_time(): void
    {
        $customer = Customer::factory()->unverified()->create();
        $link = $this->linkFor($customer);

        $this->travel(Customer::VERIFICATION_LINK_HOURS)->hours();
        $this->travel(1)->minutes();

        $this->get($link)->assertForbidden();
        $this->assertFalse($customer->fresh()->hasVerifiedEmail());
    }

    public function test_a_link_made_for_another_address_does_nothing(): void
    {
        $customer = Customer::factory()->unverified()->create();
        $link = $this->linkFor($customer);

        $customer->forceFill(['email' => 'new-address@example.com'])->save();

        $this->get($link)->assertForbidden();
        $this->assertFalse($customer->fresh()->hasVerifiedEmail());
    }

    public function test_a_link_cannot_verify_another_customer(): void
    {
        $first = Customer::factory()->unverified()->create();
        $second = Customer::factory()->unverified()->create();

        $forged = URL::temporarySignedRoute('customer.verification.verify', now()->addHour(), [
            'id' => $second->getKey(),
            'hash' => sha1($first->email),
        ]);

        $this->get($forged)->assertForbidden();
        $this->assertFalse($second->fresh()->hasVerifiedEmail());
    }

    public function test_a_link_for_nobody_is_not_found(): void
    {
        $link = URL::temporarySignedRoute('customer.verification.verify', now()->addHour(), ['id' => 99999, 'hash' => sha1('x')]);

        $this->get($link)->assertNotFound();
    }

    public function test_a_deleted_account_cannot_be_verified(): void
    {
        $customer = Customer::factory()->unverified()->create();
        $link = $this->linkFor($customer);

        $customer->delete();

        $this->get($link)->assertNotFound();
    }
}

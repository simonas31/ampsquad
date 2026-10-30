<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CustomerProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Petras Petraitis',
            'phone' => '+37061111111',
            'locale' => 'en',
            'marketing_consent' => false,
        ], $overrides);
    }

    public function test_the_dashboard_shows_the_customers_details(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'Jonas Jonaitis', 'phone' => '+37060000000']);
        $this->actingAs($customer, 'customer');

        $this->get('/account')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Account/Dashboard', false)
            ->where('profile.fullName', 'Jonas Jonaitis')
            ->where('profile.email', $customer->email)
            ->where('profile.phone', '+37060000000')
            ->where('profile.locale', 'lt')
            ->where('account.profileUrl', '/account/profile')
            ->where('account.securityUrl', '/account/security')
        );
    }

    public function test_the_profile_page_opens_with_what_the_customer_has_now(): void
    {
        $this->actingAs(Customer::factory()->withMarketingConsent()->create(['full_name' => 'Jonas Jonaitis', 'phone' => null]), 'customer');

        $this->get('/account/profile')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Account/Profile', false)
            ->where('profile.fullName', 'Jonas Jonaitis')
            ->where('profile.phone', null)
            ->where('profile.locale', 'lt')
            ->where('profile.marketingConsent', true)
            ->where('updateUrl', '/account/profile')
            ->where('seo.noindex', true)
        );
    }

    public function test_the_customer_can_change_their_details(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'Jonas Jonaitis']);
        $this->actingAs($customer, 'customer');

        $this->patch('/account/profile', $this->payload())->assertSessionHasNoErrors();

        $customer->refresh();

        $this->assertSame('Petras Petraitis', $customer->full_name);
        $this->assertSame('+37061111111', $customer->phone);
        $this->assertSame('en', $customer->locale);
    }

    public function test_the_page_says_the_changes_are_saved(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer');

        $this->from('/account/profile')->followingRedirects()->patch('/account/profile', $this->payload())
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Account/Profile', false)
                ->hasFlash('accountStatus', 'profile-updated')
            );
    }

    public function test_the_phone_can_be_taken_away(): void
    {
        $customer = Customer::factory()->create(['phone' => '+37060000000']);
        $this->actingAs($customer, 'customer');

        $this->patch('/account/profile', $this->payload(['phone' => '']))->assertSessionHasNoErrors();

        $this->assertNull($customer->fresh()->phone);
    }

    public function test_the_emails_follow_the_language_the_customer_chose(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $this->patch('/account/profile', $this->payload(['locale' => 'en']));

        $this->assertSame('en', $customer->fresh()->preferredLocale());
    }

    public function test_giving_consent_records_when_and_keeps_that_moment_while_it_stands(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(10, 0));
        $this->patch('/account/profile', $this->payload(['marketing_consent' => true]))->assertSessionHasNoErrors();
        $given = $customer->fresh()->marketing_consent_at;

        $this->assertEquals(now(), $given);

        $this->travelTo(now()->addDays(10));
        $this->patch('/account/profile', $this->payload(['marketing_consent' => true, 'full_name' => 'Petras P.']))->assertSessionHasNoErrors();

        $this->assertEquals($given, $customer->fresh()->marketing_consent_at);
    }

    public function test_withdrawing_consent_clears_it(): void
    {
        $customer = Customer::factory()->withMarketingConsent()->create();
        $this->actingAs($customer, 'customer');

        $this->patch('/account/profile', $this->payload(['marketing_consent' => false]))->assertSessionHasNoErrors();

        $this->assertNull($customer->fresh()->marketing_consent_at);
    }

    public function test_leaving_the_box_out_withdraws_consent_too(): void
    {
        $customer = Customer::factory()->withMarketingConsent()->create();
        $this->actingAs($customer, 'customer');

        $this->patch('/account/profile', ['full_name' => 'Petras Petraitis', 'locale' => 'lt'])->assertSessionHasNoErrors();

        $this->assertNull($customer->fresh()->marketing_consent_at);
    }

    public function test_the_name_and_language_are_required_and_must_be_valid(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer');

        $this->patch('/account/profile', [])->assertSessionHasErrors(['full_name', 'locale']);
        $this->patch('/account/profile', $this->payload(['locale' => 'de']))->assertSessionHasErrors('locale');
        $this->patch('/account/profile', $this->payload(['full_name' => str_repeat('a', 151)]))->assertSessionHasErrors('full_name');
        $this->patch('/account/profile', $this->payload(['phone' => 'call me maybe']))->assertSessionHasErrors('phone');
    }

    public function test_nothing_but_the_profile_fields_can_be_changed_here(): void
    {
        $customer = Customer::factory()->create(['email' => 'jonas@example.com']);
        $this->actingAs($customer, 'customer');

        $this->patch('/account/profile', $this->payload([
            'email' => 'someone-else@example.com',
            'email_verified_at' => null,
            'password' => 'a-different-password',
            'pending_email' => 'pending@example.com',
            'retained_email' => 'retained@example.com',
        ]))->assertSessionHasNoErrors();

        $customer->refresh();

        $this->assertSame('jonas@example.com', $customer->email);
        $this->assertTrue($customer->hasVerifiedEmail());
        $this->assertNull($customer->pending_email);
        $this->assertNull($customer->retained_email);
        $this->assertTrue(password_verify('password', $customer->password));
    }

    public function test_one_customer_cannot_change_another(): void
    {
        $other = Customer::factory()->create(['full_name' => 'Ona Onaitė']);
        $this->actingAs(Customer::factory()->create(), 'customer');

        $this->patch('/account/profile', $this->payload(['id' => $other->getKey()]))->assertSessionHasNoErrors();

        $this->assertSame('Ona Onaitė', $other->fresh()->full_name);
    }

    public function test_the_pages_are_closed_to_guests_and_unverified_customers(): void
    {
        $this->get('/account/profile')->assertRedirect(route('customer.login'));
        $this->patch('/account/profile', $this->payload())->assertRedirect(route('customer.login'));

        $this->actingAs(Customer::factory()->unverified()->create(), 'customer');

        $this->get('/account/profile')->assertRedirect(route('customer.verification.notice'));
        $this->patch('/account/profile', $this->payload())->assertRedirect(route('customer.verification.notice'));
    }
}

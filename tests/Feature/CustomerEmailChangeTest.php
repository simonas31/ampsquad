<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\CustomerConfirmNewEmailMail;
use App\Mail\CustomerEmailChangedMail;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CustomerEmailChangeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge(['email' => 'new-address@example.com', 'current_password' => 'password'], $overrides);
    }

    private function linkFor(Customer $customer): string
    {
        return (new CustomerConfirmNewEmailMail($customer->fresh()))->confirmationUrl();
    }

    /**
     * A customer who has asked for new-address@example.com and not followed
     * the link yet.
     */
    private function customerWaitingForTheLink(string $locale = 'lt'): Customer
    {
        $customer = Customer::factory()->inLocale($locale)->create(['email' => 'old-address@example.com']);
        $customer->forceFill(['pending_email' => 'new-address@example.com'])->save();

        return $customer;
    }

    public function test_asking_for_a_new_address_mails_a_link_to_it_and_changes_nothing_yet(): void
    {
        Mail::fake();
        $customer = Customer::factory()->inLocale('en')->create(['email' => 'old-address@example.com']);
        $this->actingAs($customer, 'customer');

        $this->post('/account/email', $this->payload())->assertSessionHasNoErrors();

        $customer->refresh();

        $this->assertSame('old-address@example.com', $customer->email);
        $this->assertSame('new-address@example.com', $customer->pending_email);
        Mail::assertQueued(CustomerConfirmNewEmailMail::class, 1);
        Mail::assertQueued(CustomerConfirmNewEmailMail::class, fn (CustomerConfirmNewEmailMail $mail) => $mail->hasTo('new-address@example.com') && $mail->locale === 'en');
        Mail::assertNotQueued(CustomerConfirmNewEmailMail::class, fn (CustomerConfirmNewEmailMail $mail) => $mail->hasTo('old-address@example.com'));
    }

    public function test_the_security_page_shows_the_address_that_is_waiting(): void
    {
        $this->actingAs($this->customerWaitingForTheLink(), 'customer');

        $this->get('/account/security')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pendingEmail', 'new-address@example.com')
        );
    }

    public function test_the_page_says_the_link_was_sent(): void
    {
        Mail::fake();
        $this->actingAs(Customer::factory()->create(), 'customer');

        $this->from('/account/security')->followingRedirects()->post('/account/email', $this->payload())
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Account/Security', false)
                ->hasFlash('accountStatus', 'email-change-requested')
            );
    }

    public function test_the_password_must_be_right(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $this->post('/account/email', $this->payload(['current_password' => 'not-my-password']))
            ->assertSessionHasErrors(['current_password' => 'Neteisingas slaptažodis.']);

        $this->assertNull($customer->fresh()->pending_email);
        Mail::assertNothingQueued();
    }

    public function test_the_address_must_be_new_and_free(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create(['email' => 'old-address@example.com']);
        Customer::factory()->create(['email' => 'taken@example.com']);
        $this->actingAs($customer, 'customer');

        $this->post('/account/email', $this->payload(['email' => 'OLD-address@example.com']))
            ->assertSessionHasErrors(['email' => 'Tai jau jūsų paskyros el. pašto adresas.']);
        $this->post('/account/email', $this->payload(['email' => 'Taken@Example.com']))->assertSessionHasErrors('email');
        $this->post('/account/email', $this->payload(['email' => 'not-an-email']))->assertSessionHasErrors('email');
        $this->post('/account/email', ['current_password' => 'password'])->assertSessionHasErrors('email');

        $this->assertNull($customer->fresh()->pending_email);
        Mail::assertNothingQueued();
    }

    public function test_the_address_of_a_deleted_account_is_free(): void
    {
        Mail::fake();
        $deleted = Customer::factory()->deleted()->create();
        $this->actingAs(Customer::factory()->create(), 'customer');

        $this->post('/account/email', $this->payload(['email' => $deleted->retained_email]))->assertSessionHasNoErrors();
    }

    public function test_the_form_is_closed_to_guests_and_unverified_customers(): void
    {
        $this->post('/account/email', $this->payload())->assertRedirect(route('customer.login'));

        $this->actingAs(Customer::factory()->unverified()->create(), 'customer');

        $this->post('/account/email', $this->payload())->assertRedirect(route('customer.verification.notice'));
    }

    public function test_the_link_moves_the_account_to_the_new_address(): void
    {
        Mail::fake();
        $customer = $this->customerWaitingForTheLink();
        $link = $this->linkFor($customer);

        $this->get($link)->assertRedirect('/login');

        $customer->refresh();

        $this->assertSame('new-address@example.com', $customer->email);
        $this->assertNull($customer->pending_email);
        $this->assertTrue($customer->hasVerifiedEmail());
        $this->assertTrue(Auth::guard('customer')->validate(['email' => 'new-address@example.com', 'password' => 'password']));
        $this->assertFalse(Auth::guard('customer')->validate(['email' => 'old-address@example.com', 'password' => 'password']));
    }

    public function test_the_new_address_counts_as_verified_even_if_the_old_one_never_was(): void
    {
        Mail::fake();
        $customer = $this->customerWaitingForTheLink();
        $customer->forceFill(['email_verified_at' => null])->save();

        $this->get($this->linkFor($customer))->assertRedirect();

        $this->assertTrue($customer->fresh()->hasVerifiedEmail());
    }

    public function test_the_old_address_is_told_in_the_customers_language_without_the_whole_new_address(): void
    {
        Mail::fake();
        $customer = $this->customerWaitingForTheLink('en');

        $this->get($this->linkFor($customer))->assertRedirect();

        Mail::assertQueued(CustomerEmailChangedMail::class, 1);
        Mail::assertQueued(CustomerEmailChangedMail::class, fn (CustomerEmailChangedMail $mail) => $mail->hasTo('old-address@example.com') && $mail->locale === 'en');
        Mail::assertNotQueued(CustomerEmailChangedMail::class, fn (CustomerEmailChangedMail $mail) => $mail->hasTo('new-address@example.com'));

        $html = (new CustomerEmailChangedMail($customer->fresh()))->render();

        $this->assertStringContainsString('n**********@example.com', $html);
        $this->assertStringNotContainsString('new-address@example.com', $html);
    }

    public function test_the_customer_lands_on_the_login_page_or_on_the_security_page_if_signed_in(): void
    {
        Mail::fake();
        $guest = $this->customerWaitingForTheLink('en');

        $this->get($this->linkFor($guest))->assertRedirect('/en/login');

        $signedIn = Customer::factory()->create(['email' => 'signed-in@example.com']);
        $signedIn->forceFill(['pending_email' => 'second-new@example.com'])->save();
        $this->actingAs($signedIn, 'customer');

        $this->get($this->linkFor($signedIn))->assertRedirect('/account/security');
    }

    public function test_the_login_page_says_the_address_was_changed(): void
    {
        Mail::fake();
        $customer = $this->customerWaitingForTheLink();

        $this->followingRedirects()->get($this->linkFor($customer))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/Login', false)
            ->hasFlash('accountStatus', 'email-changed')
        );
    }

    public function test_following_the_link_again_changes_nothing_and_mails_nobody_twice(): void
    {
        Mail::fake();
        $customer = $this->customerWaitingForTheLink();
        $link = $this->linkFor($customer);

        $this->get($link)->assertRedirect();
        $this->get($link)->assertRedirect('/login');

        $this->assertSame('new-address@example.com', $customer->fresh()->email);
        Mail::assertQueued(CustomerEmailChangedMail::class, 1);
    }

    public function test_an_address_someone_else_took_meanwhile_is_not_taken_over(): void
    {
        Mail::fake();
        $customer = $this->customerWaitingForTheLink();
        $link = $this->linkFor($customer);
        $other = Customer::factory()->create(['email' => 'new-address@example.com']);

        $this->followingRedirects()->get($link)->assertInertia(fn (AssertableInertia $page) => $page
            ->hasFlash('accountStatus', 'email-change-failed')
        );

        $this->assertSame('old-address@example.com', $customer->fresh()->email);
        $this->assertSame('new-address@example.com', $other->fresh()->email);
        Mail::assertNothingQueued();
    }

    public function test_asking_again_makes_the_first_link_useless(): void
    {
        Mail::fake();
        $customer = $this->customerWaitingForTheLink();
        $firstLink = $this->linkFor($customer);

        $customer->forceFill(['pending_email' => 'another-address@example.com'])->save();

        $this->get($firstLink)->assertForbidden();

        $this->assertSame('old-address@example.com', $customer->fresh()->email);

        $this->get($this->linkFor($customer))->assertRedirect();

        $this->assertSame('another-address@example.com', $customer->fresh()->email);
    }

    public function test_a_link_with_a_changed_signature_does_nothing(): void
    {
        $customer = $this->customerWaitingForTheLink();

        $this->get($this->linkFor($customer).'0')->assertForbidden();
        $this->get('/account/email/confirm/'.$customer->getKey().'/'.sha1('new-address@example.com'))->assertForbidden();

        $this->assertSame('old-address@example.com', $customer->fresh()->email);
    }

    public function test_a_link_stops_working_after_its_time(): void
    {
        $customer = $this->customerWaitingForTheLink();
        $link = $this->linkFor($customer);

        $this->travel(Customer::VERIFICATION_LINK_HOURS)->hours();
        $this->travel(1)->minutes();

        $this->get($link)->assertForbidden();
        $this->assertSame('old-address@example.com', $customer->fresh()->email);
    }

    public function test_a_link_for_another_address_or_another_customer_does_nothing(): void
    {
        $customer = $this->customerWaitingForTheLink();
        $stranger = Customer::factory()->create();
        $stranger->forceFill(['pending_email' => 'stranger-new@example.com'])->save();

        $forged = URL::temporarySignedRoute('customer.email.confirm', now()->addHour(), [
            'id' => $stranger->getKey(),
            'hash' => sha1('new-address@example.com'),
        ]);
        $unrelated = URL::temporarySignedRoute('customer.email.confirm', now()->addHour(), [
            'id' => $customer->getKey(),
            'hash' => sha1('something-else@example.com'),
        ]);

        $this->get($forged)->assertForbidden();
        $this->get($unrelated)->assertForbidden();

        $this->assertNotSame('new-address@example.com', $stranger->fresh()->email);
        $this->assertNotSame('stranger-new@example.com', $stranger->fresh()->email);
        $this->assertSame('old-address@example.com', $customer->fresh()->email);
    }

    public function test_a_link_for_nobody_or_a_deleted_account_is_not_found(): void
    {
        $nobody = URL::temporarySignedRoute('customer.email.confirm', now()->addHour(), ['id' => 99999, 'hash' => sha1('x')]);
        $this->get($nobody)->assertNotFound();

        $customer = $this->customerWaitingForTheLink();
        $link = $this->linkFor($customer);
        $customer->delete();

        $this->get($link)->assertNotFound();
    }
}

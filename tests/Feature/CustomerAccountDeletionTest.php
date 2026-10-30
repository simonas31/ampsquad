<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\CustomerAccountDeletedMail;
use App\Mail\VerifyCustomerEmailMail;
use App\Models\Customer;
use App\Models\Order;
use App\Services\Customers\CustomerAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CustomerAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge(['current_password' => 'password', 'confirm' => true], $overrides);
    }

    public function test_the_security_page_says_what_deleting_the_account_means(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer');

        $this->get('/account/security')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('deleteUrl', '/account')
            ->where('retentionYears', Customer::RETENTION_YEARS)
        );
    }

    public function test_the_account_is_closed_but_its_data_is_kept(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create(['email' => 'jonas@example.com', 'full_name' => 'Jonas Jonaitis']);
        $this->actingAs($customer, 'customer');

        $this->delete('/account', $this->payload())->assertSessionHasNoErrors()->assertRedirect(route('customer.login'));

        $this->assertSoftDeleted($customer);

        $kept = Customer::withTrashed()->findOrFail($customer->getKey());

        $this->assertNull($kept->email);
        $this->assertSame('jonas@example.com', $kept->retained_email);
        $this->assertSame('Jonas Jonaitis', $kept->full_name);
        $this->assertNull($kept->pending_email);
        $this->assertGuest('customer');
    }

    public function test_nobody_can_log_in_to_it_afterwards(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create(['email' => 'jonas@example.com']);
        $this->actingAs($customer, 'customer');

        $this->delete('/account', $this->payload());

        $this->assertFalse(Auth::guard('customer')->validate(['email' => 'jonas@example.com', 'password' => 'password']));
        $this->assertFalse(Auth::guard('customer')->validate(['email' => null, 'password' => 'password']));
        $this->assertFalse(password_verify('password', Customer::withTrashed()->findOrFail($customer->getKey())->password));
    }

    public function test_the_customer_lands_on_the_login_page_which_says_it_is_done(): void
    {
        Mail::fake();
        $this->actingAs(Customer::factory()->create(), 'customer');

        $this->followingRedirects()->delete('/account', $this->payload())->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/Login', false)
            ->hasFlash('accountStatus', 'account-deleted')
        );
    }

    public function test_the_customer_is_told_by_mail_in_their_language(): void
    {
        Mail::fake();
        $customer = Customer::factory()->inLocale('en')->create(['email' => 'jonas@example.com', 'full_name' => 'Jonas Jonaitis']);
        $this->actingAs($customer, 'customer');

        $this->delete('/account', $this->payload());

        Mail::assertQueued(CustomerAccountDeletedMail::class, 1);
        Mail::assertQueued(CustomerAccountDeletedMail::class, fn (CustomerAccountDeletedMail $mail) => $mail->hasTo('jonas@example.com')
            && $mail->locale === 'en'
            && $mail->fullName === 'Jonas Jonaitis');
    }

    public function test_the_mail_names_how_long_the_data_is_kept(): void
    {
        $html = (new CustomerAccountDeletedMail('Jonas Jonaitis'))->render();

        $this->assertStringContainsString('Jonas Jonaitis', $html);
        $this->assertStringContainsString((string) Customer::RETENTION_YEARS, $html);
    }

    public function test_a_wrong_password_deletes_nothing(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $this->delete('/account', $this->payload(['current_password' => 'not-my-password']))
            ->assertSessionHasErrors(['current_password' => 'Neteisingas slaptažodis.']);

        $this->assertNotSoftDeleted($customer);
        $this->assertAuthenticatedAs($customer, 'customer');
        Mail::assertNothingQueued();
    }

    public function test_the_customer_must_say_they_understand(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $this->delete('/account', $this->payload(['confirm' => false]))->assertSessionHasErrors('confirm');
        $this->delete('/account', ['current_password' => 'password'])->assertSessionHasErrors('confirm');

        $this->assertNotSoftDeleted($customer);
    }

    public function test_the_orders_stay_with_their_snapshot_and_still_point_at_the_kept_account(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create();
        $order = Order::factory()->paid()->create(['customer_id' => $customer->getKey(), 'customer_email' => 'jonas@example.com']);
        $this->actingAs($customer, 'customer');

        $this->delete('/account', $this->payload());

        $order->refresh();

        $this->assertSame($customer->getKey(), $order->customer_id);
        $this->assertSame('jonas@example.com', $order->customer_email);
        $this->assertTrue($order->customer->trashed());
    }

    public function test_a_waiting_password_reset_dies_with_the_account(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create();
        Password::broker('customers')->createToken($customer);
        $this->actingAs($customer, 'customer');

        $this->delete('/account', $this->payload());

        $this->assertSame(0, DB::table('customer_password_reset_tokens')->count());
    }

    public function test_the_address_can_be_used_for_a_new_account_at_once(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create(['email' => 'jonas@example.com']);
        $this->actingAs($customer, 'customer');
        $this->delete('/account', $this->payload());
        Auth::forgetGuards();

        $this->post('/register', [
            'full_name' => 'Jonas Jonaitis',
            'email' => 'jonas@example.com',
            'password' => 'a-long-enough-password',
            'password_confirmation' => 'a-long-enough-password',
            'company' => '',
        ])->assertSessionHasNoErrors()->assertRedirect(route('customer.verification.notice'));

        $new = Customer::query()->where('email', 'jonas@example.com')->sole();

        $this->assertNotSame($customer->getKey(), $new->getKey());
        Mail::assertQueued(VerifyCustomerEmailMail::class, 1);
    }

    public function test_another_browser_is_signed_out_when_it_asks_again(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create();

        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])->assertRedirect();
        $this->get('/account')->assertOk();

        // The account is deleted from some other browser.
        app(CustomerAccountService::class)->deleteAccount($customer->fresh());
        Auth::forgetGuards();

        $this->get('/account')->assertRedirect(route('customer.login'));
        $this->assertGuest('customer');
    }

    public function test_the_deletion_is_closed_to_guests_and_unverified_customers(): void
    {
        $this->delete('/account', $this->payload())->assertRedirect(route('customer.login'));

        $unverified = Customer::factory()->unverified()->create();
        $this->actingAs($unverified, 'customer');

        $this->delete('/account', $this->payload())->assertRedirect(route('customer.verification.notice'));
        $this->assertNotSoftDeleted($unverified);
    }

    public function test_the_form_is_limited_to_a_few_tries_a_minute(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer');

        foreach (range(1, 5) as $try) {
            $this->delete('/account', $this->payload(['current_password' => 'wrong']))->assertSessionHasErrors();
        }

        $this->delete('/account', $this->payload())->assertTooManyRequests();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CustomerAddressTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Home',
            'recipient_name' => 'Jonas Jonaitis',
            'phone' => '+37060000000',
            'street' => 'Gedimino pr. 1-2',
            'city' => 'Vilnius',
            'postal_code' => '01103',
            'country' => 'LT',
            'make_default' => false,
        ], $overrides);
    }

    private function signedIn(?Customer $customer = null): Customer
    {
        $customer ??= Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        return $customer;
    }

    private function defaultOf(Customer $customer): ?int
    {
        return $customer->fresh()->default_shipping_address_id;
    }

    public function test_an_address_is_written_out_as_one_line_in_the_given_language(): void
    {
        $address = CustomerAddress::factory()->create([
            'recipient_name' => 'Jonas Jonaitis',
            'phone' => '+37060000000',
            'street' => 'Gedimino pr. 1-2',
            'city' => 'Vilnius',
            'postal_code' => '01103',
        ]);

        $this->assertSame('Jonas Jonaitis, +37060000000, Gedimino pr. 1-2, 01103 Vilnius, Lietuva', $address->formatted('lt'));
        $this->assertSame('Jonas Jonaitis, +37060000000, Gedimino pr. 1-2, 01103 Vilnius, Lithuania', $address->formatted('en'));
    }

    public function test_the_longest_address_the_form_allows_still_fits_an_orders_delivery_address(): void
    {
        $address = new CustomerAddress([
            'recipient_name' => str_repeat('a', 120),
            'phone' => str_repeat('1', 30),
            'street' => str_repeat('b', 160),
            'city' => str_repeat('c', 80),
            'postal_code' => str_repeat('2', 20),
        ]);

        $this->assertLessThanOrEqual(500, mb_strlen($address->formatted('lt')));
    }

    public function test_the_list_shows_the_default_first_and_flags_it(): void
    {
        $customer = Customer::factory()->create();
        $first = CustomerAddress::factory()->for($customer)->labelled('Office')->create();
        $second = CustomerAddress::factory()->for($customer)->labelled('Home')->asDefault()->create();
        // Reloaded, as the default was set in the database behind this copy.
        $this->signedIn($customer->fresh());

        $this->get('/account/addresses')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Account/Addresses/Index', false)
            ->has('addresses', 2)
            ->where('addresses.0.id', $second->getKey())
            ->where('addresses.0.isDefault', true)
            ->where('addresses.0.label', 'Home')
            ->where('addresses.0.countryName', 'Lietuva')
            ->where('addresses.0.editUrl', "/account/addresses/{$second->getKey()}/edit")
            ->where('addresses.0.updateUrl', "/account/addresses/{$second->getKey()}")
            ->where('addresses.0.deleteUrl', "/account/addresses/{$second->getKey()}")
            ->where('addresses.0.defaultUrl', "/account/addresses/{$second->getKey()}/default")
            ->where('addresses.1.id', $first->getKey())
            ->where('addresses.1.isDefault', false)
            ->where('limit', CustomerAddress::MAX_PER_CUSTOMER)
            ->where('createUrl', '/account/addresses/create')
            ->where('account.addressesUrl', '/account/addresses')
            ->where('seo.noindex', true)
        );
    }

    public function test_the_list_holds_only_the_customers_own_addresses(): void
    {
        $this->signedIn();
        CustomerAddress::factory()->count(3)->create();

        $this->get('/account/addresses')->assertInertia(fn (AssertableInertia $page) => $page->has('addresses', 0));
    }

    public function test_the_new_address_form_starts_with_the_customers_own_name_and_phone(): void
    {
        $this->signedIn(Customer::factory()->create(['full_name' => 'Jonas Jonaitis', 'phone' => '+37060000000']));

        $this->get('/account/addresses/create')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Account/Addresses/Create', false)
            ->where('initial.recipientName', 'Jonas Jonaitis')
            ->where('initial.phone', '+37060000000')
            ->where('isFirst', true)
            ->where('storeUrl', '/account/addresses')
            ->where('indexUrl', '/account/addresses')
        );
    }

    public function test_the_first_address_becomes_the_default_whatever_the_customer_asked(): void
    {
        $customer = $this->signedIn();

        $this->post('/account/addresses', $this->payload(['make_default' => false]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('account.addresses.index'));

        $address = $customer->addresses()->sole();

        $this->assertSame($address->getKey(), $this->defaultOf($customer));
        $this->assertSame('Home', $address->label);
        $this->assertSame('LT', $address->country);
    }

    public function test_a_later_address_is_the_default_only_when_asked(): void
    {
        $customer = $this->signedIn();
        $first = CustomerAddress::factory()->for($customer)->asDefault()->create();

        $this->post('/account/addresses', $this->payload(['make_default' => false]))->assertSessionHasNoErrors();
        $this->assertSame($first->getKey(), $this->defaultOf($customer));

        $this->post('/account/addresses', $this->payload(['street' => 'Other g. 5', 'make_default' => true]))->assertSessionHasNoErrors();
        $this->assertSame($customer->addresses()->where('street', 'Other g. 5')->value('id'), $this->defaultOf($customer));
    }

    public function test_the_list_says_the_address_is_saved(): void
    {
        $this->signedIn();

        $this->followingRedirects()->post('/account/addresses', $this->payload())->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Account/Addresses/Index', false)
            ->hasFlash('accountStatus', 'address-saved')
        );
    }

    public function test_the_label_is_optional_and_the_rest_is_required(): void
    {
        $customer = $this->signedIn();

        $this->post('/account/addresses', $this->payload(['label' => '']))->assertSessionHasNoErrors();
        $this->assertNull($customer->addresses()->sole()->label);

        $this->post('/account/addresses', [])->assertSessionHasErrors(['recipient_name', 'phone', 'street', 'city', 'postal_code', 'country']);
    }

    public function test_the_fields_must_be_valid(): void
    {
        $this->signedIn();

        $this->post('/account/addresses', $this->payload(['phone' => 'call me maybe']))->assertSessionHasErrors('phone');
        $this->post('/account/addresses', $this->payload(['postal_code' => '<script>']))->assertSessionHasErrors('postal_code');
        $this->post('/account/addresses', $this->payload(['country' => 'DE']))->assertSessionHasErrors('country');
        $this->post('/account/addresses', $this->payload(['street' => str_repeat('a', 161)]))->assertSessionHasErrors('street');
        $this->post('/account/addresses', $this->payload(['recipient_name' => str_repeat('a', 121)]))->assertSessionHasErrors('recipient_name');
        $this->post('/account/addresses', $this->payload(['city' => str_repeat('a', 81)]))->assertSessionHasErrors('city');
        $this->post('/account/addresses', $this->payload(['label' => str_repeat('a', 51)]))->assertSessionHasErrors('label');
    }

    public function test_an_address_cannot_be_given_to_another_customer(): void
    {
        $customer = $this->signedIn();
        $other = Customer::factory()->create();

        $this->post('/account/addresses', $this->payload(['customer_id' => $other->getKey(), 'id' => 999]))->assertSessionHasNoErrors();

        $this->assertSame(1, $customer->addresses()->count());
        $this->assertSame(0, $other->addresses()->count());
    }

    public function test_a_customer_keeps_at_most_ten_addresses(): void
    {
        $customer = $this->signedIn();
        CustomerAddress::factory()->count(CustomerAddress::MAX_PER_CUSTOMER)->for($customer)->create();

        $this->post('/account/addresses', $this->payload())->assertSessionHasErrors(['limit' => 'Galite išsaugoti daugiausia 10 adresų. Ištrinkite vieną, kad pridėtumėte naują.']);
        $this->assertSame(CustomerAddress::MAX_PER_CUSTOMER, $customer->addresses()->count());

        $this->get('/account/addresses/create')->assertRedirect(route('account.addresses.index'));
    }

    public function test_one_deleted_address_makes_room_for_a_new_one(): void
    {
        $customer = $this->signedIn();
        $addresses = CustomerAddress::factory()->count(CustomerAddress::MAX_PER_CUSTOMER)->for($customer)->create();

        $this->delete("/account/addresses/{$addresses->first()->getKey()}")->assertRedirect();
        $this->post('/account/addresses', $this->payload())->assertSessionHasNoErrors();

        $this->assertSame(CustomerAddress::MAX_PER_CUSTOMER, $customer->addresses()->count());
    }

    public function test_the_edit_form_has_the_address_in_it(): void
    {
        $customer = Customer::factory()->create();
        $address = CustomerAddress::factory()->for($customer)->asDefault()->create(['street' => 'Gedimino pr. 1-2']);
        $this->signedIn($customer->fresh());

        $this->get("/account/addresses/{$address->getKey()}/edit")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Account/Addresses/Edit', false)
            ->where('address.id', $address->getKey())
            ->where('address.street', 'Gedimino pr. 1-2')
            ->where('address.isDefault', true)
            ->where('indexUrl', '/account/addresses')
        );
    }

    public function test_an_address_can_be_changed(): void
    {
        $customer = $this->signedIn();
        $address = CustomerAddress::factory()->for($customer)->create();

        $this->put("/account/addresses/{$address->getKey()}", $this->payload(['street' => 'New g. 7', 'city' => 'Kaunas']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('account.addresses.index'));

        $address->refresh();

        $this->assertSame('New g. 7', $address->street);
        $this->assertSame('Kaunas', $address->city);
    }

    public function test_changing_an_address_can_make_it_the_default_but_never_takes_the_default_away(): void
    {
        $customer = $this->signedIn();
        $default = CustomerAddress::factory()->for($customer)->asDefault()->create();
        $other = CustomerAddress::factory()->for($customer)->create();

        $this->put("/account/addresses/{$default->getKey()}", $this->payload(['make_default' => false]))->assertSessionHasNoErrors();
        $this->assertSame($default->getKey(), $this->defaultOf($customer));

        $this->put("/account/addresses/{$other->getKey()}", $this->payload(['make_default' => true]))->assertSessionHasNoErrors();
        $this->assertSame($other->getKey(), $this->defaultOf($customer));
    }

    public function test_an_address_can_be_made_the_default_on_its_own(): void
    {
        $customer = $this->signedIn();
        CustomerAddress::factory()->for($customer)->asDefault()->create();
        $other = CustomerAddress::factory()->for($customer)->create();

        $this->from('/account/addresses')->followingRedirects()->patch("/account/addresses/{$other->getKey()}/default")
            ->assertInertia(fn (AssertableInertia $page) => $page->hasFlash('accountStatus', 'address-default-set'));

        $this->assertSame($other->getKey(), $this->defaultOf($customer));
    }

    public function test_deleting_the_default_hands_it_to_the_oldest_address_left(): void
    {
        $customer = $this->signedIn();
        $oldest = CustomerAddress::factory()->for($customer)->create();
        $default = CustomerAddress::factory()->for($customer)->asDefault()->create();
        CustomerAddress::factory()->for($customer)->create();

        $this->delete("/account/addresses/{$default->getKey()}")->assertRedirect(route('account.addresses.index'));

        $this->assertModelMissing($default);
        $this->assertSame($oldest->getKey(), $this->defaultOf($customer));
    }

    public function test_deleting_another_address_leaves_the_default_alone(): void
    {
        $customer = $this->signedIn();
        $default = CustomerAddress::factory()->for($customer)->asDefault()->create();
        $other = CustomerAddress::factory()->for($customer)->create();

        $this->delete("/account/addresses/{$other->getKey()}")->assertRedirect();

        $this->assertModelMissing($other);
        $this->assertSame($default->getKey(), $this->defaultOf($customer));
    }

    public function test_deleting_the_last_address_leaves_no_default(): void
    {
        $customer = $this->signedIn();
        $only = CustomerAddress::factory()->for($customer)->asDefault()->create();

        $this->followingRedirects()->delete("/account/addresses/{$only->getKey()}")->assertInertia(fn (AssertableInertia $page) => $page
            ->hasFlash('accountStatus', 'address-deleted')
            ->has('addresses', 0)
        );

        $this->assertNull($this->defaultOf($customer));
    }

    public function test_orders_already_placed_keep_the_address_they_were_sent_to(): void
    {
        $customer = $this->signedIn();
        $address = CustomerAddress::factory()->for($customer)->asDefault()->create();
        $order = Order::factory()->create(['customer_id' => $customer->getKey(), 'delivery_address' => $address->formatted('lt')]);
        $before = $order->fresh()->delivery_address;

        $this->put("/account/addresses/{$address->getKey()}", $this->payload(['street' => 'Changed g. 1']))->assertSessionHasNoErrors();
        $this->delete("/account/addresses/{$address->getKey()}");

        $this->assertSame($before, $order->fresh()->delivery_address);
    }

    public function test_another_customers_address_cannot_be_seen_or_touched(): void
    {
        $customer = $this->signedIn();
        $theirs = CustomerAddress::factory()->create(['street' => 'Their g. 1']);

        $this->get("/account/addresses/{$theirs->getKey()}/edit")->assertNotFound();
        $this->put("/account/addresses/{$theirs->getKey()}", $this->payload(['street' => 'Mine now g. 2']))->assertNotFound();
        $this->patch("/account/addresses/{$theirs->getKey()}/default")->assertNotFound();
        $this->delete("/account/addresses/{$theirs->getKey()}")->assertNotFound();

        $this->assertSame('Their g. 1', $theirs->fresh()->street);
        $this->assertNull($this->defaultOf($theirs->customer));
        $this->assertNull($this->defaultOf($customer));
    }

    public function test_an_address_that_does_not_exist_or_is_not_a_number_is_not_found(): void
    {
        $this->signedIn();

        $this->get('/account/addresses/99999/edit')->assertNotFound();
        $this->get('/account/addresses/1abc/edit')->assertNotFound();
        $this->delete('/account/addresses/abc')->assertNotFound();
    }

    public function test_the_policy_lets_a_customer_change_only_their_own(): void
    {
        $customer = Customer::factory()->create();
        $own = CustomerAddress::factory()->for($customer)->create();
        $foreign = CustomerAddress::factory()->create();

        $this->assertTrue(Gate::forUser($customer)->allows('update', $own));
        $this->assertTrue(Gate::forUser($customer)->allows('delete', $own));
        $this->assertFalse(Gate::forUser($customer)->allows('update', $foreign));
        $this->assertFalse(Gate::forUser($customer)->allows('delete', $foreign));
    }

    public function test_the_pages_are_closed_to_guests_and_unverified_customers(): void
    {
        $address = CustomerAddress::factory()->create();

        $this->get('/account/addresses')->assertRedirect(route('customer.login'));
        $this->post('/account/addresses', $this->payload())->assertRedirect(route('customer.login'));
        $this->delete("/account/addresses/{$address->getKey()}")->assertRedirect(route('customer.login'));

        $this->actingAs(Customer::factory()->unverified()->create(), 'customer');

        $this->get('/account/addresses')->assertRedirect(route('customer.verification.notice'));
        $this->post('/account/addresses', $this->payload())->assertRedirect(route('customer.verification.notice'));
    }

    public function test_the_address_pages_are_not_stored_by_a_browser(): void
    {
        $this->signedIn();

        $this->assertStringContainsString('no-store', (string) $this->get('/account/addresses')->headers->get('Cache-Control'));
    }

    public function test_the_addresses_are_limited_to_a_burst_a_minute(): void
    {
        $this->signedIn();

        foreach (range(1, 30) as $try) {
            $this->get('/account/addresses')->assertOk();
        }

        $this->get('/account/addresses')->assertTooManyRequests();
    }

    public function test_a_deleted_account_keeps_its_addresses_until_the_account_itself_goes(): void
    {
        $customer = Customer::factory()->create();
        $address = CustomerAddress::factory()->for($customer)->asDefault()->create();

        $customer->delete();

        $this->assertModelExists($address);

        $customer->forceDelete();

        $this->assertModelMissing($address);
    }

    public function test_the_prune_command_takes_the_addresses_with_an_expired_account_default_included(): void
    {
        $customer = Customer::factory()->deleted(now()->subYears(Customer::RETENTION_YEARS)->subDay())->create();
        $default = CustomerAddress::factory()->for($customer)->asDefault()->create();
        $other = CustomerAddress::factory()->for($customer)->create();

        $this->artisan('model:prune', ['--model' => [Customer::class]])->assertSuccessful();

        $this->assertModelMissing($customer);
        $this->assertModelMissing($default);
        $this->assertModelMissing($other);
    }
}

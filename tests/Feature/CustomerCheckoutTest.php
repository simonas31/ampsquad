<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CheckoutScenario;
use Tests\TestCase;

/**
 * Checkout for a customer who is signed in: their details and saved addresses
 * are offered, and the order is linked to their account. For everyone else it
 * is the checkout it always was.
 */
class CustomerCheckoutTest extends TestCase
{
    use CheckoutScenario;
    use RefreshDatabase;

    private ShippingMethod $pickup;

    private ShippingMethod $courier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->switchCheckoutOn();

        $this->pickup = ShippingMethod::factory()->pickup()->create();
        $this->courier = ShippingMethod::factory()->named('DPD')->priced(450)->create();
    }

    private function filledCart(): Cart
    {
        return $this->cartWith([[Product::factory()->priced(1000)->inStock(10)->create(), 1]]);
    }

    /**
     * A verified customer, signed in.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function customer(array $attributes = []): Customer
    {
        $customer = Customer::factory()->create($attributes);
        $this->actingAs($customer, 'customer');

        return $customer;
    }

    /**
     * Places an order for the cart by courier, with the form's own fields
     * overridden, and returns whether the server took it.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function placeByCourier(Cart $cart, array $overrides = []): TestResponse
    {
        return $this->visitorWith($cart)->post('/checkout', $this->checkoutForm($cart, $this->courier, $overrides));
    }

    public function test_the_checkout_page_of_a_guest_has_no_customer_and_no_saved_addresses(): void
    {
        $cart = $this->filledCart();

        $this->visitorWith($cart)->get('/checkout')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Checkout', false)
            ->where('customer', null)
            ->has('savedAddresses', 0)
        );
    }

    public function test_the_checkout_page_gives_a_customer_their_details_and_addresses_default_first(): void
    {
        $cart = $this->filledCart();
        $customer = Customer::factory()->create(['full_name' => 'Jonas Jonaitis', 'phone' => '+37060000000']);
        $other = CustomerAddress::factory()->for($customer)->labelled('Office')->create();
        $home = CustomerAddress::factory()->for($customer)->labelled('Home')->asDefault()->create();
        $this->actingAs($customer->fresh(), 'customer');

        $this->visitorWith($cart)->get('/checkout')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('customer.fullName', 'Jonas Jonaitis')
            ->where('customer.email', $customer->email)
            ->where('customer.phone', '+37060000000')
            ->has('savedAddresses', 2)
            ->where('savedAddresses.0.id', $home->getKey())
            ->where('savedAddresses.0.isDefault', true)
            ->where('savedAddresses.1.id', $other->getKey())
        );
    }

    public function test_a_customer_with_an_unverified_address_is_a_guest_at_checkout(): void
    {
        $cart = $this->filledCart();
        $customer = Customer::factory()->unverified()->create();
        CustomerAddress::factory()->for($customer)->create();
        $this->actingAs($customer, 'customer');

        $this->visitorWith($cart)->get('/checkout')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('customer', null)
            ->has('savedAddresses', 0)
        );
    }

    public function test_an_order_placed_by_a_customer_belongs_to_their_account(): void
    {
        $cart = $this->filledCart();
        $customer = $this->customer();

        $this->placeByCourier($cart)->assertSessionHasNoErrors();

        $order = Order::query()->sole();

        $this->assertSame($customer->getKey(), $order->customer_id);
        $this->assertTrue($customer->orders()->whereKey($order->getKey())->exists());
    }

    public function test_the_order_keeps_what_was_typed_at_checkout_not_what_the_account_says(): void
    {
        $cart = $this->filledCart();
        $this->customer(['full_name' => 'Account Name', 'email' => 'account@example.test', 'phone' => '+37061111111']);

        $this->placeByCourier($cart, ['name' => 'Gift Giver', 'email' => 'gift@example.test', 'phone' => '+37062222222'])->assertSessionHasNoErrors();

        $order = Order::query()->sole();

        $this->assertSame('Gift Giver', $order->customer_name);
        $this->assertSame('gift@example.test', $order->customer_email);
        $this->assertSame('+37062222222', $order->customer_phone);
        $this->assertNotNull($order->customer_id);
    }

    public function test_an_order_placed_by_a_guest_belongs_to_no_account(): void
    {
        $cart = $this->filledCart();

        $this->placeByCourier($cart)->assertSessionHasNoErrors();

        $this->assertNull(Order::query()->sole()->customer_id);
    }

    public function test_an_order_placed_by_a_customer_with_an_unverified_address_belongs_to_no_account(): void
    {
        $cart = $this->filledCart();
        $this->actingAs(Customer::factory()->unverified()->create(), 'customer');

        $this->placeByCourier($cart)->assertSessionHasNoErrors();

        $this->assertNull(Order::query()->sole()->customer_id);
    }

    public function test_the_customer_cannot_choose_whose_account_the_order_goes_to(): void
    {
        $cart = $this->filledCart();
        $customer = $this->customer();
        $other = Customer::factory()->create();

        $this->placeByCourier($cart, ['customer_id' => $other->getKey()])->assertSessionHasNoErrors();

        $this->assertSame($customer->getKey(), Order::query()->sole()->customer_id);
    }

    public function test_a_saved_address_is_written_into_the_order_by_the_server(): void
    {
        $cart = $this->filledCart();
        $customer = $this->customer();
        $address = CustomerAddress::factory()->for($customer)->create([
            'recipient_name' => 'Ona Onaitė',
            'phone' => '+37063333333',
            'street' => 'Gedimino pr. 1-2',
            'city' => 'Vilnius',
            'postal_code' => '01103',
        ]);

        $this->placeByCourier($cart, ['address_choice' => 'saved', 'address_id' => $address->getKey(), 'delivery_address' => null])->assertSessionHasNoErrors();

        $this->assertSame('Ona Onaitė, +37063333333, Gedimino pr. 1-2, 01103 Vilnius, Lietuva', Order::query()->sole()->delivery_address);
    }

    public function test_a_saved_address_is_written_in_the_language_of_the_order(): void
    {
        app()->setLocale('en');
        $cart = $this->filledCart();
        $customer = $this->customer();
        $address = CustomerAddress::factory()->for($customer)->create(['recipient_name' => 'Ona', 'phone' => '+37063333333', 'street' => 'Main st. 1', 'city' => 'Kaunas', 'postal_code' => '44001']);

        $this->placeByCourier($cart, ['address_choice' => 'saved', 'address_id' => $address->getKey()])->assertSessionHasNoErrors();

        $order = Order::query()->sole();

        $this->assertSame('en', $order->locale);
        $this->assertSame('Ona, +37063333333, Main st. 1, 44001 Kaunas, Lithuania', $order->delivery_address);
    }

    public function test_text_sent_along_with_a_saved_address_is_never_used(): void
    {
        $cart = $this->filledCart();
        $customer = $this->customer();
        $address = CustomerAddress::factory()->for($customer)->create(['street' => 'Real g. 1']);

        $this->placeByCourier($cart, ['address_choice' => 'saved', 'address_id' => $address->getKey(), 'delivery_address' => 'Tampered text'])->assertSessionHasNoErrors();

        $stored = Order::query()->sole()->delivery_address;

        $this->assertStringContainsString('Real g. 1', $stored);
        $this->assertStringNotContainsString('Tampered text', $stored);
    }

    public function test_another_customers_saved_address_cannot_be_used(): void
    {
        $cart = $this->filledCart();
        $this->customer();
        $theirs = CustomerAddress::factory()->create(['street' => 'Their g. 1']);

        $this->placeByCourier($cart, ['address_choice' => 'saved', 'address_id' => $theirs->getKey()])->assertSessionHasErrors('address_id');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_a_saved_address_must_be_chosen_when_the_customer_says_they_use_one(): void
    {
        $cart = $this->filledCart();
        $this->customer();

        $this->placeByCourier($cart, ['address_choice' => 'saved', 'address_id' => null])->assertSessionHasErrors('address_id');
        $this->placeByCourier($cart, ['address_choice' => 'saved', 'address_id' => 99999])->assertSessionHasErrors('address_id');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_a_pickup_needs_no_address_whatever_the_customer_chose(): void
    {
        $cart = $this->filledCart();
        $customer = $this->customer();
        $address = CustomerAddress::factory()->for($customer)->create();

        $this->visitorWith($cart)->post('/checkout', $this->checkoutForm($cart, $this->pickup, ['address_choice' => 'saved', 'address_id' => $address->getKey()]))
            ->assertSessionHasNoErrors();

        $this->assertNull(Order::query()->sole()->delivery_address);
    }

    public function test_a_new_address_typed_in_parts_is_written_into_the_order(): void
    {
        $cart = $this->filledCart();
        $this->customer();

        $this->placeByCourier($cart, [
            'address_choice' => 'new',
            'new_street' => 'Naujoji g. 5-6',
            'new_city' => 'Klaipėda',
            'new_postal_code' => '91001',
            'delivery_address' => null,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Jonas Jonaitis, +37060000000, Naujoji g. 5-6, 91001 Klaipėda, Lietuva', Order::query()->sole()->delivery_address);
    }

    public function test_a_new_address_needs_all_its_parts(): void
    {
        $cart = $this->filledCart();
        $this->customer();

        $this->placeByCourier($cart, ['address_choice' => 'new', 'new_street' => '', 'new_city' => '', 'new_postal_code' => ''])
            ->assertSessionHasErrors(['new_street', 'new_city', 'new_postal_code']);
        $this->placeByCourier($cart, ['address_choice' => 'new', 'new_street' => 'g. 1', 'new_city' => 'Vilnius', 'new_postal_code' => '<script>'])
            ->assertSessionHasErrors('new_postal_code');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_a_new_address_is_kept_in_the_account_only_when_asked(): void
    {
        $cart = $this->filledCart();
        $customer = $this->customer();
        $parts = ['address_choice' => 'new', 'new_street' => 'Naujoji g. 5-6', 'new_city' => 'Klaipėda', 'new_postal_code' => '91001'];

        $this->placeByCourier($cart, [...$parts, 'save_address' => false])->assertSessionHasNoErrors();

        $this->assertSame(0, $customer->addresses()->count());

        $cart = $this->filledCart();
        $this->placeByCourier($cart, [...$parts, 'save_address' => true])->assertSessionHasNoErrors();

        $saved = $customer->addresses()->sole();

        $this->assertSame('Naujoji g. 5-6', $saved->street);
        $this->assertSame('Klaipėda', $saved->city);
        $this->assertSame('91001', $saved->postal_code);
        $this->assertSame('Jonas Jonaitis', $saved->recipient_name);
        $this->assertSame('+37060000000', $saved->phone);
        $this->assertSame('LT', $saved->country);
        $this->assertSame($saved->getKey(), $customer->fresh()->default_shipping_address_id);
    }

    public function test_a_second_kept_address_does_not_take_the_default(): void
    {
        $cart = $this->filledCart();
        $customer = Customer::factory()->create();
        $first = CustomerAddress::factory()->for($customer)->asDefault()->create();
        $this->actingAs($customer->fresh(), 'customer');

        $this->placeByCourier($cart, ['address_choice' => 'new', 'new_street' => 'g. 1', 'new_city' => 'Vilnius', 'new_postal_code' => '01103', 'save_address' => true])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $customer->addresses()->count());
        $this->assertSame($first->getKey(), $customer->fresh()->default_shipping_address_id);
    }

    public function test_a_customer_with_the_most_addresses_still_gets_their_order(): void
    {
        $cart = $this->filledCart();
        $customer = $this->customer();
        CustomerAddress::factory()->count(CustomerAddress::MAX_PER_CUSTOMER)->for($customer)->create();

        $this->placeByCourier($cart, ['address_choice' => 'new', 'new_street' => 'g. 1', 'new_city' => 'Vilnius', 'new_postal_code' => '01103', 'save_address' => true])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(CustomerAddress::MAX_PER_CUSTOMER, $customer->addresses()->count());
    }

    public function test_nothing_is_kept_when_the_order_is_refused(): void
    {
        $cart = $this->filledCart();
        $customer = $this->customer();

        $this->placeByCourier($cart, [
            'address_choice' => 'new',
            'new_street' => 'g. 1',
            'new_city' => 'Vilnius',
            'new_postal_code' => '01103',
            'save_address' => true,
            'fingerprint' => str_repeat('a', 64),
        ]);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, $customer->addresses()->count());
    }

    public function test_an_address_is_only_kept_for_the_new_choice_and_a_delivery_that_needs_one(): void
    {
        $customer = $this->customer();

        $cart = $this->filledCart();
        $this->placeByCourier($cart, ['address_choice' => 'text', 'delivery_address' => 'Somewhere 1', 'save_address' => true, 'new_street' => 'g. 1', 'new_city' => 'Vilnius', 'new_postal_code' => '01103'])
            ->assertSessionHasNoErrors();

        $cart = $this->filledCart();
        $this->visitorWith($cart)->post('/checkout', $this->checkoutForm($cart, $this->pickup, ['address_choice' => 'new', 'save_address' => true, 'new_street' => 'g. 1', 'new_city' => 'Vilnius', 'new_postal_code' => '01103']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Order::query()->count());
        $this->assertSame(0, $customer->addresses()->count());
    }

    public function test_a_customer_can_still_give_a_parcel_locker_as_text(): void
    {
        $cart = $this->filledCart();
        $this->customer();

        $this->placeByCourier($cart, ['address_choice' => 'text', 'delivery_address' => 'Omniva locker, Gedimino pr. 9'])->assertSessionHasNoErrors();

        $this->assertSame('Omniva locker, Gedimino pr. 9', Order::query()->sole()->delivery_address);
    }

    public function test_text_is_required_when_the_customer_chose_it(): void
    {
        $cart = $this->filledCart();
        $this->customer();

        $this->placeByCourier($cart, ['address_choice' => 'text', 'delivery_address' => ''])->assertSessionHasErrors('delivery_address');
    }

    public function test_a_customer_who_sends_no_choice_gives_text_as_a_guest_does(): void
    {
        $cart = $this->filledCart();
        $this->customer();

        $this->placeByCourier($cart, ['delivery_address' => 'Gedimino pr. 1, Vilnius'])->assertSessionHasNoErrors();

        $this->assertSame('Gedimino pr. 1, Vilnius', Order::query()->sole()->delivery_address);
    }

    public function test_a_choice_that_is_not_one_of_the_three_is_refused(): void
    {
        $cart = $this->filledCart();
        $this->customer();

        $this->placeByCourier($cart, ['address_choice' => 'somewhere-else'])->assertSessionHasErrors('address_choice');
    }

    public function test_a_guest_cannot_use_anyones_saved_address_and_their_text_is_what_counts(): void
    {
        $cart = $this->filledCart();
        $theirs = CustomerAddress::factory()->create(['street' => 'Their g. 1']);

        $this->placeByCourier($cart, [
            'address_choice' => 'saved',
            'address_id' => $theirs->getKey(),
            'delivery_address' => 'Gedimino pr. 1, Vilnius',
            'save_address' => true,
            'new_street' => 'g. 1',
        ])->assertSessionHasNoErrors();

        $order = Order::query()->sole();

        $this->assertSame('Gedimino pr. 1, Vilnius', $order->delivery_address);
        $this->assertNull($order->customer_id);
        $this->assertSame(1, CustomerAddress::query()->count());
    }

    public function test_the_order_of_a_customer_shows_in_their_account(): void
    {
        $cart = $this->filledCart();
        $this->customer();

        $this->placeByCourier($cart)->assertSessionHasNoErrors();
        $order = Order::query()->sole();

        $this->get('/account/orders')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.number', $order->number)
            ->where('orders.data.0.status', 'pending_payment')
        );
        $this->get("/account/orders/{$order->number}")->assertOk();
    }

    public function test_checkout_sends_a_guest_who_logs_in_back_to_checkout(): void
    {
        $customer = Customer::factory()->create();

        $this->get('/login?return=checkout')->assertOk();

        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])->assertRedirect(route('checkout.show'));
    }

    public function test_only_checkout_is_a_place_to_return_to(): void
    {
        $customer = Customer::factory()->create();

        $this->get('/login?return=https://evil.example.test');

        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])->assertRedirect(route('account.dashboard'));
    }
}

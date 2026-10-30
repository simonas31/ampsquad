<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CustomerOrderTest extends TestCase
{
    use RefreshDatabase;

    private function signedIn(?Customer $customer = null): Customer
    {
        $customer ??= Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        return $customer;
    }

    private function orderOf(Customer $customer): Order
    {
        return Order::factory()->placedBy($customer)->create();
    }

    public function test_the_list_holds_the_customers_own_orders_newest_first(): void
    {
        $customer = $this->signedIn();
        $older = Order::factory()->placedBy($customer)->paid()->create(['created_at' => now()->subDays(3)]);
        $newer = Order::factory()->placedBy($customer)->shipped()->create(['created_at' => now()->subDay()]);
        Order::factory()->create();
        Order::factory()->placedBy(Customer::factory()->create())->create();

        $this->get('/account/orders')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Account/Orders/Index', false)
            ->has('orders.data', 2)
            ->where('orders.data.0.number', $newer->number)
            ->where('orders.data.0.status', 'shipped')
            ->where('orders.data.0.url', "/account/orders/{$newer->number}")
            ->where('orders.data.0.totalInclVatCents', $newer->total_incl_vat_cents)
            ->where('orders.data.0.payUrl', null)
            ->where('orders.data.1.number', $older->number)
            ->where('orders.data.1.status', 'paid')
            ->where('linkUrl', '/account/orders/link')
            ->where('account.ordersUrl', '/account/orders')
            ->where('seo.noindex', true)
        );
    }

    public function test_the_list_counts_the_units_on_each_order(): void
    {
        $customer = $this->signedIn();
        $order = Order::factory()->placedBy($customer)->withLine(Product::factory()->create(), 3)->withLine(Product::factory()->create(), 2)->create();

        $this->get('/account/orders')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('orders.data.0.number', $order->number)
            ->where('orders.data.0.itemCount', 5)
        );
    }

    public function test_every_status_is_listed_the_way_the_order_has_it(): void
    {
        $customer = $this->signedIn();

        foreach ([
            OrderStatus::PendingPayment->value => Order::factory()->placedBy($customer)->awaitingPayment(),
            OrderStatus::Paid->value => Order::factory()->placedBy($customer)->paid(),
            OrderStatus::Shipped->value => Order::factory()->placedBy($customer)->shipped(),
            OrderStatus::Cancelled->value => Order::factory()->placedBy($customer)->cancelled(),
            OrderStatus::Expired->value => Order::factory()->placedBy($customer)->expired(),
            OrderStatus::Refunded->value => Order::factory()->placedBy($customer)->refunded(),
        ] as $factory) {
            $factory->create();
        }

        $this->get('/account/orders')->assertInertia(function (AssertableInertia $page) {
            $listed = collect($page->toArray()['props']['orders']['data'])->pluck('status')->sort()->values()->all();

            $this->assertSame(collect(OrderStatus::cases())->pluck('value')->sort()->values()->all(), $listed);
        });
    }

    public function test_an_order_waiting_for_its_payment_offers_the_way_to_pay(): void
    {
        $customer = $this->signedIn();
        $order = Order::factory()->placedBy($customer)->awaitingPayment()->create();
        Payment::factory()->for($order)->create(['redirect_url' => 'https://pay.example.test/abc']);

        $this->get('/account/orders')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('orders.data.0.payUrl', 'https://pay.example.test/abc')
        );
    }

    public function test_an_order_whose_time_to_pay_ran_out_offers_no_way_to_pay(): void
    {
        $customer = $this->signedIn();
        $order = Order::factory()->placedBy($customer)->pastItsWindow()->create();
        Payment::factory()->for($order)->create(['redirect_url' => 'https://pay.example.test/abc']);

        $this->get('/account/orders')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('orders.data.0.status', 'pending_payment')
            ->where('orders.data.0.payUrl', null)
        );
    }

    public function test_the_list_is_paged_by_ten(): void
    {
        $customer = $this->signedIn();
        Order::factory()->count(12)->placedBy($customer)->create();

        $this->get('/account/orders')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('orders.data', 10)
            ->where('orders.meta.total', 12)
            ->where('orders.meta.last_page', 2)
        );
        $this->get('/account/orders?page=2')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('orders.data', 2)
        );
    }

    public function test_a_customer_without_orders_gets_an_empty_list(): void
    {
        $this->signedIn();
        Order::factory()->create();

        $this->get('/account/orders')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('orders.data', 0)
            ->where('linkableCount', 0)
        );
    }

    public function test_the_list_costs_the_same_few_queries_however_many_orders_it_holds(): void
    {
        $customer = $this->signedIn();
        Order::factory()->placedBy($customer)->paid()->create();

        $small = $this->queriesOf(fn () => $this->get('/account/orders')->assertOk());

        Order::factory()->count(9)->placedBy($customer)->shipped()->create();

        $big = $this->queriesOf(fn () => $this->get('/account/orders')->assertOk());

        $this->assertSame($small, $big);
    }

    private function queriesOf(callable $request): int
    {
        $request();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_an_order_shows_what_the_customer_needs_to_know(): void
    {
        $customer = $this->signedIn();
        $product = Product::factory()->create();
        $order = Order::factory()->placedBy($customer)->paid()->withLine($product, 2)->create([
            'delivery_address' => 'Jonas Jonaitis, +37060000000, Gedimino pr. 1, 01103 Vilnius, Lietuva',
            'customer_note' => 'Please call first.',
            'internal_notes' => 'The shop only.',
        ]);

        $this->get("/account/orders/{$order->number}")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Account/Orders/Show', false)
            ->where('order.number', $order->number)
            ->where('order.status', 'paid')
            ->where('order.deliveryAddress', 'Jonas Jonaitis, +37060000000, Gedimino pr. 1, 01103 Vilnius, Lietuva')
            ->where('order.customerNote', 'Please call first.')
            ->where('order.lines.0.quantity', 2)
            ->where('order.totalInclVatCents', $order->total_incl_vat_cents)
            ->where('order.shipping.label', $order->shipping_label)
            ->missing('order.internalNotes')
            ->missing('order.customerEmail')
            ->where('seo.noindex', true)
        );
    }

    public function test_the_order_page_is_not_stored_by_a_browser(): void
    {
        $order = $this->orderOf($this->signedIn());

        $this->assertStringContainsString('no-store', (string) $this->get("/account/orders/{$order->number}")->headers->get('Cache-Control'));
    }

    public function test_the_moments_of_an_orders_life_come_from_its_own_timestamps_in_order(): void
    {
        $customer = $this->signedIn();
        $order = Order::factory()->placedBy($customer)->shipped()->create([
            'created_at' => now()->subDays(3),
            'paid_at' => now()->subDays(2),
            'shipped_at' => now()->subDay(),
        ]);

        $this->get("/account/orders/{$order->number}")->assertInertia(fn (AssertableInertia $page) => $page
            ->has('order.timeline', 3)
            ->where('order.timeline.0.key', 'placed')
            ->where('order.timeline.1.key', 'paid')
            ->where('order.timeline.2.key', 'shipped')
            ->where('order.timeline.1.at', $order->fresh()->paid_at->toIso8601String())
        );
    }

    public function test_an_order_that_expired_and_was_paid_late_shows_both_in_the_order_they_happened(): void
    {
        $customer = $this->signedIn();
        $order = Order::factory()->placedBy($customer)->paid()->create([
            'created_at' => now()->subHours(3),
            'expired_at' => now()->subHours(2),
            'paid_at' => now()->subHour(),
        ]);

        $this->get("/account/orders/{$order->number}")->assertInertia(fn (AssertableInertia $page) => $page
            ->where('order.timeline.0.key', 'placed')
            ->where('order.timeline.1.key', 'expired')
            ->where('order.timeline.2.key', 'paid')
            ->where('order.status', 'paid')
        );
    }

    public function test_an_order_waiting_for_its_payment_shows_the_way_to_pay_and_its_deadline(): void
    {
        $customer = $this->signedIn();
        $order = Order::factory()->placedBy($customer)->awaitingPayment()->create();
        Payment::factory()->for($order)->create(['redirect_url' => 'https://pay.example.test/abc']);

        $this->get("/account/orders/{$order->number}")->assertInertia(fn (AssertableInertia $page) => $page
            ->where('order.status', 'pending_payment')
            ->where('order.payUrl', 'https://pay.example.test/abc')
            ->where('order.paymentExpiresAt', $order->fresh()->payment_expires_at->toIso8601String())
        );
    }

    public function test_viewing_an_order_changes_nothing_about_it(): void
    {
        $customer = $this->signedIn();
        $order = Order::factory()->placedBy($customer)->awaitingPayment()->create();
        $before = $order->fresh()->getAttributes();

        $this->get("/account/orders/{$order->number}")->assertOk();
        $this->get('/account/orders')->assertOk();

        $this->assertSame($before, $order->fresh()->getAttributes());
    }

    public function test_another_customers_order_is_not_found(): void
    {
        $this->signedIn();
        $theirs = $this->orderOf(Customer::factory()->create());

        $this->get("/account/orders/{$theirs->number}")->assertNotFound();
    }

    public function test_a_guest_order_is_not_found_even_with_the_customers_own_address(): void
    {
        $customer = $this->signedIn();
        $guestOrder = Order::factory()->create(['customer_email' => $customer->email]);

        $this->get("/account/orders/{$guestOrder->number}")->assertNotFound();
    }

    public function test_an_order_that_does_not_exist_or_has_a_malformed_number_is_not_found(): void
    {
        $this->signedIn();

        $this->get('/account/orders/AS-ZZZZZZZZ')->assertNotFound();
        $this->get('/account/orders/not-a-number')->assertNotFound();
        $this->get('/account/orders/'.Order::factory()->create()->uuid)->assertNotFound();
    }

    public function test_the_policy_shows_a_customer_only_their_own_orders(): void
    {
        $customer = Customer::factory()->create();
        $own = $this->orderOf($customer);
        $guest = Order::factory()->create();
        $foreign = $this->orderOf(Customer::factory()->create());

        $this->assertTrue(Gate::forUser($customer)->allows('viewOwn', $own));
        $this->assertFalse(Gate::forUser($customer)->allows('viewOwn', $guest));
        $this->assertFalse(Gate::forUser($customer)->allows('viewOwn', $foreign));
    }

    public function test_the_pages_are_closed_to_guests_and_unverified_customers(): void
    {
        $order = $this->orderOf(Customer::factory()->create());

        $this->get('/account/orders')->assertRedirect(route('customer.login'));
        $this->get("/account/orders/{$order->number}")->assertRedirect(route('customer.login'));
        $this->post('/account/orders/link')->assertRedirect(route('customer.login'));

        $this->actingAs(Customer::factory()->unverified()->create(), 'customer');

        $this->get('/account/orders')->assertRedirect(route('customer.verification.notice'));
        $this->post('/account/orders/link')->assertRedirect(route('customer.verification.notice'));
    }

    public function test_the_public_order_page_still_works_for_a_guest_and_hides_the_account_details(): void
    {
        $order = $this->orderOf(Customer::factory()->create());

        $this->get("/orders/{$order->uuid}")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Orders/Show', false)
            ->missing('order.deliveryAddress')
            ->missing('order.timeline')
        );
    }

    public function test_the_dashboard_shows_the_three_latest_orders(): void
    {
        $customer = $this->signedIn();
        foreach (range(1, 5) as $days) {
            Order::factory()->placedBy($customer)->create(['created_at' => now()->subDays($days)]);
        }
        $latest = $customer->orders()->latest()->limit(3)->pluck('number')->all();

        $this->get('/account')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('recentOrders', 3)
            ->where('recentOrders.0.number', $latest[0])
            ->where('recentOrders.1.number', $latest[1])
            ->where('recentOrders.2.number', $latest[2])
        );
    }

    public function test_the_dashboard_of_a_customer_without_orders_has_none(): void
    {
        $this->signedIn();

        $this->get('/account')->assertInertia(fn (AssertableInertia $page) => $page->has('recentOrders', 0));
    }

    public function test_guest_orders_with_the_customers_address_are_counted_as_ready_to_add(): void
    {
        $customer = $this->signedIn(Customer::factory()->create(['email' => 'jonas@example.com']));
        Order::factory()->count(2)->create(['customer_email' => 'jonas@example.com']);
        Order::factory()->create(['customer_email' => 'JONAS@Example.com']);
        Order::factory()->create(['customer_email' => 'someone-else@example.com']);
        $this->orderOf(Customer::factory()->create(['email' => 'other-owner@example.com']));
        Order::factory()->placedBy(Customer::factory()->create())->create(['customer_email' => 'jonas@example.com']);

        $this->get('/account/orders')->assertInertia(fn (AssertableInertia $page) => $page->where('linkableCount', 3));
    }

    public function test_asking_adds_those_orders_to_the_account_and_says_how_many(): void
    {
        $customer = $this->signedIn(Customer::factory()->create(['email' => 'jonas@example.com']));
        $mine = Order::factory()->count(2)->create(['customer_email' => 'jonas@example.com']);
        $someoneElses = Order::factory()->create(['customer_email' => 'someone-else@example.com']);
        $ownedElsewhere = Order::factory()->placedBy($other = Customer::factory()->create())->create(['customer_email' => 'jonas@example.com']);

        $this->followingRedirects()->post('/account/orders/link')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Account/Orders/Index', false)
            ->hasFlash('accountStatus', 'orders-linked')
            ->hasFlash('linkedOrders', 2)
            ->has('orders.data', 2)
            ->where('linkableCount', 0)
        );

        foreach ($mine as $order) {
            $this->assertSame($customer->getKey(), $order->fresh()->customer_id);
        }
        $this->assertNull($someoneElses->fresh()->customer_id);
        $this->assertSame($other->getKey(), $ownedElsewhere->fresh()->customer_id);
    }

    public function test_adding_is_matched_on_the_address_whatever_its_case(): void
    {
        $customer = $this->signedIn(Customer::factory()->create(['email' => 'jonas@example.com']));
        $order = Order::factory()->create(['customer_email' => 'JONAS@EXAMPLE.COM']);

        $this->post('/account/orders/link');

        $this->assertSame($customer->getKey(), $order->fresh()->customer_id);
    }

    public function test_adding_changes_only_the_link_and_not_the_order(): void
    {
        $this->signedIn(Customer::factory()->create(['email' => 'jonas@example.com']));
        $order = Order::factory()->paid()->create(['customer_email' => 'jonas@example.com', 'customer_name' => 'Jonas Jonaitis']);
        $this->travel(1)->hour();
        $before = $order->fresh()->getAttributes();

        $this->post('/account/orders/link');

        $after = $order->fresh()->getAttributes();
        unset($before['customer_id'], $after['customer_id']);

        $this->assertSame($before, $after);
    }

    public function test_orders_whose_details_were_wiped_are_never_added(): void
    {
        $this->signedIn();
        $wiped = Order::factory()->expired()->create(['customer_email' => '', 'anonymized_at' => now()]);

        $this->get('/account/orders')->assertInertia(fn (AssertableInertia $page) => $page->where('linkableCount', 0));
        $this->post('/account/orders/link');

        $this->assertNull($wiped->fresh()->customer_id);
    }

    public function test_adding_again_when_nothing_is_left_says_so(): void
    {
        $this->signedIn();

        $this->followingRedirects()->post('/account/orders/link')->assertInertia(fn (AssertableInertia $page) => $page
            ->hasFlash('accountStatus', 'orders-linked')
            ->hasFlash('linkedOrders', 0)
        );
    }

    public function test_adding_is_limited_to_a_few_tries_a_minute(): void
    {
        $this->signedIn();

        foreach (range(1, 5) as $try) {
            $this->post('/account/orders/link')->assertRedirect();
        }

        $this->post('/account/orders/link')->assertTooManyRequests();
    }
}

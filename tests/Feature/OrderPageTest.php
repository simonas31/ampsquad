<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Settings\ShopSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class OrderPageTest extends TestCase
{
    use RefreshDatabase;

    private function placedOrder(): Order
    {
        $product = Product::factory()->priced(1000)->create(['name' => ['lt' => 'Maršrutizatorius', 'en' => 'Router'], 'sku' => fake()->unique()->bothify('RT-####')]);

        $order = Order::factory()
            ->shippedBy(ShippingMethod::factory()->named('DPD')->priced(450)->create())
            ->withLine($product, 2)
            ->create(['customer_name' => 'Jonas Jonaitis', 'customer_email' => 'jonas@secret.test', 'customer_phone' => '+37060012345', 'delivery_address' => 'Slaptoji g. 1, Vilnius']);
        Payment::factory()->for($order)->create(['redirect_url' => 'https://pay.example.test/abc']);

        return $order;
    }

    public function test_the_page_shows_the_order_to_whoever_has_its_uuid(): void
    {
        $order = $this->placedOrder();

        $this->get(route('orders.show', ['order' => $order->uuid]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Orders/Show', false)
                ->where('order.number', $order->number)
                ->where('order.status', 'pending_payment')
                ->where('order.lines.0.name', 'Maršrutizatorius')
                ->where('order.lines.0.sku', $order->lines()->value('sku'))
                ->where('order.lines.0.quantity', 2)
                ->where('order.lines.0.unitPriceInclVatCents', 1210)
                ->where('order.lines.0.lineTotalInclVatCents', 2420)
                ->where('order.shipping.label', 'DPD')
                ->where('order.shipping.priceInclVatCents', 545)
                ->where('order.shipping.isPickup', false)
                ->where('order.subtotalExclVatCents', 2000)
                ->where('order.vatRate', 21)
                ->where('order.totalInclVatCents', 2420 + 545)
                ->where('seo.noindex', true)
            );
    }

    public function test_the_page_does_not_hand_out_the_customers_details(): void
    {
        $order = $this->placedOrder();

        $this->get(route('orders.show', ['order' => $order->uuid]))
            ->assertOk()
            ->assertDontSee('jonas@secret.test')
            ->assertDontSee('Jonas Jonaitis')
            ->assertDontSee('+37060012345')
            ->assertDontSee('Slaptoji g. 1')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->missing('order.customerEmail')
                ->missing('order.customerPhone')
                ->missing('order.deliveryAddress')
                ->missing('order.id'));
    }

    public function test_the_page_is_not_cached(): void
    {
        $order = $this->placedOrder();

        $header = $this->get(route('orders.show', ['order' => $order->uuid]))->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $header);
        $this->assertStringContainsString('private', $header);
    }

    public function test_an_unknown_uuid_or_an_id_is_not_found(): void
    {
        $order = $this->placedOrder();

        $this->get('/orders/'.fake()->uuid())->assertNotFound();
        $this->get('/orders/'.$order->id)->assertNotFound();
        $this->get('/orders/'.$order->number)->assertNotFound();
    }

    public function test_the_customer_can_still_pay_while_the_order_waits_and_its_window_is_open(): void
    {
        $order = $this->placedOrder();

        $this->get(route('orders.show', ['order' => $order->uuid]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('order.payUrl', 'https://pay.example.test/abc'));
    }

    public function test_there_is_nothing_to_pay_once_the_window_closed_or_the_order_moved_on(): void
    {
        $closed = $this->placedOrder();
        $closed->update(['payment_expires_at' => now()->subMinute()]);
        $paid = $this->placedOrder();
        $paid->transitionTo(OrderStatus::Paid);

        foreach ([$closed, $paid] as $order) {
            $this->get(route('orders.show', ['order' => $order->uuid]))
                ->assertInertia(fn (AssertableInertia $page) => $page->where('order.payUrl', null));
        }
    }

    public function test_a_payment_that_failed_is_not_offered_again(): void
    {
        $order = $this->placedOrder();
        $order->payments()->update(['status' => 'failed']);

        $this->get(route('orders.show', ['order' => $order->uuid]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('order.payUrl', null));
    }

    public function test_the_page_shows_where_the_order_stands(): void
    {
        $order = $this->placedOrder();

        foreach (['paid', 'shipped', 'cancelled', 'expired', 'refunded'] as $status) {
            Order::query()->whereKey($order->id)->update(['status' => $status]);

            $this->get(route('orders.show', ['order' => $order->uuid]))
                ->assertInertia(fn (AssertableInertia $page) => $page->where('order.status', $status));
        }
    }

    public function test_the_page_is_there_even_when_the_shop_and_checkout_are_switched_off(): void
    {
        $order = $this->placedOrder();
        $settings = app(ShopSettings::class);
        $settings->enabled = false;
        $settings->checkoutEnabled = false;
        $settings->save();

        $this->get(route('orders.show', ['order' => $order->uuid]))->assertOk();
    }

    public function test_the_page_is_in_the_orders_own_names_and_not_the_products_current_ones(): void
    {
        $order = $this->placedOrder();
        Product::query()->update(['name' => json_encode(['lt' => 'Pervadintas', 'en' => 'Renamed'])]);

        $this->get(route('orders.show', ['order' => $order->uuid]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('order.lines.0.name', 'Maršrutizatorius'));
    }
}

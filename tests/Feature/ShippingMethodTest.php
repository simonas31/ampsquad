<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_name_and_description_are_translated(): void
    {
        $method = ShippingMethod::factory()->pickup()->create();

        $this->assertSame('Atsiėmimas parduotuvėje', $method->getTranslation('name', 'lt'));
        $this->assertSame('Pickup at the store', $method->getTranslation('name', 'en'));
        $this->assertStringContainsString('Rudmenos g. 5-7', $method->getTranslation('description', 'lt'));
    }

    public function test_the_price_is_held_without_vat_and_shown_with_it(): void
    {
        $method = ShippingMethod::factory()->priced(399)->create();

        $this->assertSame(399, $method->price_excl_vat_cents);
        $this->assertSame(Product::addVat(399), $method->priceInclVatCents());
        $this->assertSame(483, $method->priceInclVatCents());
    }

    public function test_pickup_needs_no_address_and_the_rest_do(): void
    {
        $this->assertFalse(ShippingMethod::factory()->pickup()->make()->needsDeliveryAddress());
        $this->assertTrue(ShippingMethod::factory()->make()->needsDeliveryAddress());
    }

    public function test_checkout_offers_only_active_methods_cheapest_first(): void
    {
        $dear = ShippingMethod::factory()->priced(900)->create();
        $free = ShippingMethod::factory()->pickup()->create();
        $cheap = ShippingMethod::factory()->priced(300)->create();
        $sameAsCheap = ShippingMethod::factory()->priced(300)->create();
        ShippingMethod::factory()->inactive()->priced(1)->create();

        $this->assertSame(
            [$free->id, $cheap->id, $sameAsCheap->id, $dear->id],
            ShippingMethod::query()->forCheckout()->pluck('id')->all(),
        );
    }

    public function test_a_new_method_is_free_active_and_not_pickup(): void
    {
        $method = ShippingMethod::query()->create(['name' => ['lt' => 'X', 'en' => 'X']])->refresh();

        $this->assertSame(0, $method->price_excl_vat_cents);
        $this->assertTrue($method->is_active);
        $this->assertFalse($method->is_pickup);
    }

    public function test_deleting_a_method_leaves_its_orders_as_they_were(): void
    {
        $method = ShippingMethod::factory()->named('Omniva')->priced(349)->create();
        $order = Order::factory()->shippedBy($method)->create();
        $total = $order->total_incl_vat_cents;

        $method->delete();

        $order->refresh();
        $this->assertNull($order->shipping_method_id);
        $this->assertSame('Omniva', $order->shipping_label);
        $this->assertSame(349, $order->shipping_excl_vat_cents);
        $this->assertSame($total, $order->total_incl_vat_cents);
    }

    public function test_editing_a_method_leaves_its_orders_as_they_were(): void
    {
        $method = ShippingMethod::factory()->named('Omniva')->priced(349)->create();
        $order = Order::factory()->shippedBy($method)->create();

        $method->update(['name' => ['lt' => 'Naujas', 'en' => 'New'], 'price_excl_vat_cents' => 9999]);

        $order->refresh();
        $this->assertSame('Omniva', $order->shipping_label);
        $this->assertSame(349, $order->shipping_excl_vat_cents);
        $this->assertTrue($order->shippingMethod->is($method));
    }
}

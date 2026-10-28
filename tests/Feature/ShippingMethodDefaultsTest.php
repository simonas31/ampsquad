<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The migration that gives a new shop its pickup point and couriers. Tests
 * start from an empty table, so it does nothing here by itself; these call
 * what it does.
 */
class ShippingMethodDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_28_212349_create_default_shipping_methods.php');
    }

    public function test_it_creates_one_free_pickup_point_and_four_couriers(): void
    {
        $this->migration()->createDefaults();

        $methods = ShippingMethod::query()->orderBy('id')->get();

        $this->assertCount(5, $methods);
        $this->assertSame(
            ['Atsiėmimas parduotuvėje', 'LP Express', 'DPD', 'Omniva', 'Lietuvos paštas'],
            $methods->map(fn (ShippingMethod $method): string => $method->getTranslation('name', 'lt'))->all(),
        );
        $this->assertSame(
            ['Pickup at the store', 'LP Express', 'DPD', 'Omniva', 'Lithuania Post'],
            $methods->map(fn (ShippingMethod $method): string => $method->getTranslation('name', 'en'))->all(),
        );
        $this->assertTrue($methods->every(fn (ShippingMethod $method): bool => $method->is_active));
    }

    public function test_the_pickup_point_is_free_and_asks_for_no_address(): void
    {
        $this->migration()->createDefaults();

        $pickup = ShippingMethod::query()->where('is_pickup', true)->sole();

        $this->assertSame(0, $pickup->price_excl_vat_cents);
        $this->assertSame(0, $pickup->priceInclVatCents());
        $this->assertFalse($pickup->needsDeliveryAddress());
        $this->assertStringContainsString('Rudmenos g. 5-7', $pickup->getTranslation('description', 'lt'));
        $this->assertStringContainsString('Rudmenos g. 5-7', $pickup->getTranslation('description', 'en'));
    }

    public function test_every_courier_costs_three_euros_with_vat_and_asks_for_an_address(): void
    {
        $this->migration()->createDefaults();

        $couriers = ShippingMethod::query()->where('is_pickup', false)->get();

        $this->assertCount(4, $couriers);
        foreach ($couriers as $courier) {
            $this->assertSame(300, $courier->priceInclVatCents(), $courier->getTranslation('name', 'en'));
            $this->assertTrue($courier->needsDeliveryAddress());
            $this->assertStringContainsString('2-3', $courier->getTranslation('description', 'lt'));
            $this->assertStringContainsString('2-3', $courier->getTranslation('description', 'en'));
        }
    }

    public function test_the_stored_price_gives_exactly_three_euros_at_the_shops_vat_rate(): void
    {
        $this->assertSame(21, Product::vatRatePercent());

        $this->migration()->createDefaults();

        $this->assertSame(248, ShippingMethod::query()->where('is_pickup', false)->value('price_excl_vat_cents'));
        $this->assertSame(300, Product::addVat(248));
    }

    public function test_it_does_not_add_to_shipping_methods_the_shop_already_has(): void
    {
        ShippingMethod::factory()->named('Our own courier')->priced(500)->create();

        $this->migration()->createDefaults();

        $this->assertSame(1, ShippingMethod::query()->count());
    }

    public function test_running_it_twice_creates_them_once(): void
    {
        $this->migration()->createDefaults();
        $this->migration()->createDefaults();

        $this->assertSame(5, ShippingMethod::query()->count());
    }

    public function test_it_does_nothing_in_tests_but_runs_everywhere_else(): void
    {
        $this->migration()->up();
        $this->assertSame(0, ShippingMethod::query()->count());

        $this->app['env'] = 'production';
        $this->migration()->up();
        $this->assertSame(5, ShippingMethod::query()->count());
    }

    public function test_rolling_it_back_removes_only_its_own_methods_and_no_order_loses_its_copy(): void
    {
        $this->migration()->createDefaults();
        $ours = ShippingMethod::factory()->named('Our own courier')->priced(500)->create();
        $dpd = ShippingMethod::query()->where('name->lt', 'DPD')->sole();
        $order = Order::factory()->shippedBy($dpd)->create();

        $this->migration()->down();

        $this->assertSame([$ours->id], ShippingMethod::query()->pluck('id')->all());
        $order->refresh();
        $this->assertNull($order->shipping_method_id);
        $this->assertSame('DPD', $order->shipping_label);
        $this->assertSame(248, $order->shipping_excl_vat_cents);
    }

    public function test_the_defaults_are_ordered_at_checkout_pickup_first(): void
    {
        $this->migration()->createDefaults();

        $names = ShippingMethod::query()->forCheckout()->get()->map(fn (ShippingMethod $method): string => $method->getTranslation('name', 'en'))->all();

        $this->assertSame('Pickup at the store', $names[0]);
        $this->assertCount(5, $names);
        $this->assertSame(5, DB::table('shipping_methods')->count());
    }
}

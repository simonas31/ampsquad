<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Product;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the database refuses on its own, whatever the application does: the
 * amounts of an order that do not add up, a second line for a product, a
 * payment message seen twice, an order or product that has history being
 * deleted.
 */
class OrderConstraintsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Runs $statement and expects the database to refuse it for the named
     * constraint, not merely for some reason.
     */
    private function assertRefusedBy(string $constraint, Closure $statement): void
    {
        try {
            $statement();
        } catch (QueryException $exception) {
            $this->assertStringContainsString($constraint, $exception->getMessage());

            return;
        }

        $this->fail("Expected the database to refuse it with {$constraint}.");
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function lineFor(Product $product, array $overrides = []): array
    {
        return [...OrderLine::snapshotOf($product, 2, 'lt'), ...$overrides];
    }

    public function test_an_order_whose_amounts_do_not_add_up_is_refused(): void
    {
        $this->assertRefusedBy('orders_total_adds_up', fn () => Order::factory()->create(['total_incl_vat_cents' => 5]));
        $this->assertRefusedBy('orders_total_adds_up', fn () => Order::factory()->create(['subtotal_excl_vat_cents' => 100, 'total_incl_vat_cents' => 100, 'vat_cents' => 1]));
    }

    public function test_shipping_cannot_cost_less_with_vat_than_without(): void
    {
        $this->assertRefusedBy('orders_shipping_vat_not_negative', fn () => Order::factory()->create([
            'shipping_excl_vat_cents' => 100,
            'shipping_incl_vat_cents' => 50,
            'total_incl_vat_cents' => 100,
        ]));
    }

    public function test_two_orders_cannot_share_a_uuid_or_a_number(): void
    {
        $first = Order::factory()->create();

        $this->assertRefusedBy('orders_number_unique', fn () => Order::factory()->create(['number' => $first->number]));
        $this->assertRefusedBy('orders_uuid_unique', fn () => Order::factory()->create(['uuid' => $first->uuid]));
    }

    public function test_a_line_needs_at_least_one_unit(): void
    {
        $order = Order::factory()->create();
        $product = Product::factory()->create();

        $this->assertRefusedBy('order_lines_quantity_positive', fn () => $order->lines()->create($this->lineFor($product, [
            'quantity' => 0,
            'line_total_excl_vat_cents' => 0,
            'line_total_incl_vat_cents' => 0,
        ])));
    }

    public function test_a_lines_totals_must_be_its_unit_prices_times_the_quantity(): void
    {
        $order = Order::factory()->create();
        $product = Product::factory()->priced(1000)->create();

        $this->assertRefusedBy('order_lines_totals_add_up', fn () => $order->lines()->create($this->lineFor($product, ['line_total_incl_vat_cents' => 1])));
        $this->assertRefusedBy('order_lines_totals_add_up', fn () => $order->lines()->create($this->lineFor($product, ['line_total_excl_vat_cents' => 1])));
    }

    public function test_a_unit_price_cannot_be_lower_with_vat_than_without(): void
    {
        $order = Order::factory()->create();
        $product = Product::factory()->priced(1000)->create();

        $this->assertRefusedBy('order_lines_vat_not_negative', fn () => $order->lines()->create($this->lineFor($product, [
            'unit_price_incl_vat_cents' => 500,
            'line_total_incl_vat_cents' => 1000,
        ])));
    }

    public function test_an_order_has_one_line_per_product(): void
    {
        $order = Order::factory()->create();
        $product = Product::factory()->create();
        $order->lines()->create($this->lineFor($product));

        $this->assertRefusedBy('order_lines_order_id_product_id_unique', fn () => $order->lines()->create($this->lineFor($product)));
    }

    public function test_an_order_with_history_cannot_be_deleted(): void
    {
        $order = Order::factory()->create();

        $this->assertRefusedBy('order_lines_order_id_foreign', fn () => $order->delete());
        $this->assertModelExists($order);
    }

    public function test_a_product_that_was_sold_cannot_be_force_deleted(): void
    {
        $product = Product::factory()->create();
        Order::factory()->withLine($product)->create();

        $this->assertRefusedBy('order_lines_product_id_foreign', fn () => $product->forceDelete());
        $this->assertModelExists($product);
    }

    public function test_a_payment_reference_belongs_to_one_payment_of_a_provider(): void
    {
        $first = Payment::factory()->create(['provider' => 'opay', 'provider_reference' => 'ABC123']);

        $this->assertRefusedBy('payments_provider_provider_reference_unique', fn () => Payment::factory()->create(['provider' => 'opay', 'provider_reference' => 'ABC123']));

        // Another provider's ids are its own business.
        Payment::factory()->create(['provider' => 'other', 'provider_reference' => 'ABC123']);

        $this->assertSame(2, Payment::query()->where('provider_reference', 'ABC123')->count());
        $this->assertModelExists($first);
    }

    public function test_money_taken_is_recorded_once_per_token(): void
    {
        Payment::factory()->succeeded()->create(['provider' => 'opay', 'provider_payment_token' => 'tok_1']);

        $this->assertRefusedBy('payments_provider_provider_payment_token_unique', fn () => Payment::factory()->succeeded()->create(['provider' => 'opay', 'provider_payment_token' => 'tok_1']));
    }

    public function test_payments_that_are_not_started_or_paid_yet_can_be_many(): void
    {
        Payment::factory()->count(3)->create(['provider_reference' => null, 'provider_payment_token' => null]);

        $this->assertSame(3, Payment::query()->whereNull('provider_reference')->count());
    }

    public function test_a_payment_message_can_be_stored_only_once(): void
    {
        PaymentEvent::factory()->create(['provider' => 'opay', 'event_key' => 'key-1']);

        $this->assertRefusedBy('payment_events_provider_event_key_unique', fn () => PaymentEvent::factory()->create(['provider' => 'opay', 'event_key' => 'key-1']));

        PaymentEvent::factory()->create(['provider' => 'other', 'event_key' => 'key-1']);
        PaymentEvent::factory()->create(['provider' => 'opay', 'event_key' => 'key-2']);

        $this->assertSame(3, PaymentEvent::query()->count());
    }

    public function test_a_message_about_an_order_we_do_not_have_can_still_be_kept(): void
    {
        $event = PaymentEvent::factory()->create(['order_id' => null, 'payment_id' => null]);

        $this->assertNull($event->order);
        $this->assertNull($event->payment);
    }

    public function test_an_order_with_payments_cannot_be_deleted_either(): void
    {
        $payment = Payment::factory()->create();

        $this->assertRefusedBy('foreign key constraint fails', fn () => $payment->order->delete());
    }
}

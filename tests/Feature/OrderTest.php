<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShippingMethod;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private const string NUMBER_PATTERN = '/^AS-[0-9A-HJKMNP-TV-Z]{8}$/';

    public function test_an_order_gets_a_public_uuid_a_random_looking_number_and_a_payment_window(): void
    {
        $order = Order::factory()->create();

        $this->assertTrue(Str::isUuid($order->uuid));
        $this->assertNotSame((string) $order->id, $order->uuid);
        $this->assertMatchesRegularExpression(self::NUMBER_PATTERN, $order->number);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertEqualsWithDelta(
            now()->addMinutes(Order::PAYMENT_WINDOW_MINUTES)->getTimestamp(),
            $order->payment_expires_at->getTimestamp(),
            5,
        );
    }

    public function test_the_window_and_grace_are_fifteen_and_five_minutes(): void
    {
        $this->assertSame(15, Order::PAYMENT_WINDOW_MINUTES);
        $this->assertSame(5, Order::EXPIRY_GRACE_MINUTES);
    }

    public function test_an_order_made_with_only_what_a_customer_gives_gets_the_rest_from_the_model(): void
    {
        $order = Order::query()->create([
            'locale' => 'lt',
            'vat_rate_percent' => 21,
            'subtotal_excl_vat_cents' => 1000,
            'vat_cents' => 210,
            'total_incl_vat_cents' => 1210,
            'shipping_method' => ShippingMethod::Pickup,
            'shipping_label' => 'Atsiėmimas parduotuvėje',
            'customer_name' => 'Jonas Jonaitis',
            'customer_email' => 'jonas@example.test',
            'customer_phone' => '+37060000000',
            'terms_accepted_at' => now(),
        ])->refresh();

        $this->assertTrue(Str::isUuid($order->uuid));
        $this->assertMatchesRegularExpression(self::NUMBER_PATTERN, $order->number);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame('EUR', $order->currency);
        $this->assertSame(0, $order->shipping_excl_vat_cents);
        $this->assertNotNull($order->payment_expires_at);
    }

    public function test_status_and_its_timestamps_cannot_be_mass_assigned(): void
    {
        $order = Order::query()->create([
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
            'uuid' => 'chosen-by-the-browser',
            'number' => 'AS-CHOSEN00',
            'locale' => 'lt',
            'vat_rate_percent' => 21,
            'subtotal_excl_vat_cents' => 0,
            'vat_cents' => 0,
            'total_incl_vat_cents' => 0,
            'shipping_method' => ShippingMethod::Pickup,
            'shipping_label' => 'x',
            'customer_name' => 'x',
            'customer_email' => 'x@example.test',
            'customer_phone' => '1',
            'terms_accepted_at' => now(),
        ])->refresh();

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertNull($order->paid_at);
        $this->assertTrue(Str::isUuid($order->uuid));
        $this->assertNotSame('AS-CHOSEN00', $order->number);
    }

    public function test_numbers_are_unique_and_well_formed(): void
    {
        $numbers = Order::factory()->count(30)->create()->pluck('number');

        $this->assertCount(30, $numbers->unique());
        $numbers->each(fn (string $number) => $this->assertMatchesRegularExpression(self::NUMBER_PATTERN, $number));
    }

    public function test_a_new_number_is_never_one_already_taken(): void
    {
        $taken = Order::factory()->create();

        $this->assertNotSame($taken->number, Order::newNumber());
    }

    public function test_the_amounts_come_from_the_lines_and_add_up(): void
    {
        $cheap = Product::factory()->priced(333)->create();
        $dear = Product::factory()->priced(12_345)->create();

        $order = Order::factory()
            ->shippedBy(ShippingMethod::Dpd, 450)
            ->withLine($cheap, 3)
            ->withLine($dear, 2)
            ->create()
            ->load('lines');

        $this->assertCount(2, $order->lines);
        $this->assertSame(3 * 333 + 2 * 12_345, $order->subtotal_excl_vat_cents);
        $this->assertSame(450, $order->shipping_excl_vat_cents);
        $this->assertSame(Product::addVat(450), $order->shipping_incl_vat_cents);
        $this->assertSame(
            $order->lines->sum('line_total_incl_vat_cents') + $order->shipping_incl_vat_cents,
            $order->total_incl_vat_cents,
        );
        $this->assertSame(
            $order->subtotal_excl_vat_cents + $order->shipping_excl_vat_cents + $order->vat_cents,
            $order->total_incl_vat_cents,
        );
    }

    public function test_an_order_without_lines_given_gets_one_so_it_is_never_empty(): void
    {
        $order = Order::factory()->create();

        $this->assertSame(1, $order->lines()->count());
        $this->assertGreaterThan(0, $order->total_incl_vat_cents);
    }

    public function test_lines_are_named_in_the_orders_language(): void
    {
        $product = Product::factory()->create(['name' => ['lt' => 'Maršrutizatorius', 'en' => 'Router']]);

        $lithuanian = Order::factory()->withLine($product)->create();
        $english = Order::factory()->inLocale('en')->withLine($product)->create();

        $this->assertSame('Maršrutizatorius', $lithuanian->lines()->sole()->product_name);
        $this->assertSame('Router', $english->lines()->sole()->product_name);
        $this->assertSame('Pickup at the store', $english->shipping_label);
    }

    public function test_only_a_courier_needs_a_delivery_address(): void
    {
        $pickup = Order::factory()->shippedBy(ShippingMethod::Pickup)->create();
        $courier = Order::factory()->shippedBy(ShippingMethod::Omniva, 349)->create();

        $this->assertNull($pickup->delivery_address);
        $this->assertNotEmpty($courier->delivery_address);
        $this->assertSame(ShippingMethod::Omniva, $courier->shipping_method);
        $this->assertSame('Omniva', $courier->shipping_label);
    }

    /**
     * @return array<string, array{string, OrderStatus, string|null}>
     */
    public static function statesAndTheirTimestamps(): array
    {
        return [
            'awaiting payment' => ['awaitingPayment', OrderStatus::PendingPayment, null],
            'paid' => ['paid', OrderStatus::Paid, 'paid_at'],
            'shipped' => ['shipped', OrderStatus::Shipped, 'shipped_at'],
            'cancelled' => ['cancelled', OrderStatus::Cancelled, 'cancelled_at'],
            'expired' => ['expired', OrderStatus::Expired, 'expired_at'],
            'refunded' => ['refunded', OrderStatus::Refunded, 'refunded_at'],
        ];
    }

    #[DataProvider('statesAndTheirTimestamps')]
    public function test_each_status_state_stamps_its_own_timestamp(string $state, OrderStatus $status, ?string $column): void
    {
        $order = Order::factory()->{$state}()->create();

        $this->assertSame($status, $order->status);
        $this->assertSame($status->timestampColumn(), $column);

        if ($column !== null) {
            $this->assertNotNull($order->{$column});
        }
    }

    public function test_an_expired_state_is_past_its_window_and_its_grace(): void
    {
        $expired = Order::factory()->expired()->create();
        $stale = Order::factory()->pastItsWindow()->create();

        $limit = now()->subMinutes(Order::PAYMENT_WINDOW_MINUTES + Order::EXPIRY_GRACE_MINUTES);

        $this->assertTrue($expired->payment_expires_at->lessThan($limit));
        $this->assertTrue($stale->payment_expires_at->lessThan($limit));
        $this->assertSame(OrderStatus::PendingPayment, $stale->status);
    }

    public function test_an_order_knows_its_lines_payments_and_events(): void
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->for($order)->create();
        $event = PaymentEvent::factory()->create(['order_id' => $order->id, 'payment_id' => $payment->id]);

        $this->assertTrue($order->lines()->sole()->order->is($order));
        $this->assertTrue($order->payments()->sole()->is($payment));
        $this->assertTrue($order->paymentEvents()->sole()->is($event));
        $this->assertTrue($payment->order->is($order));
        $this->assertTrue($payment->events()->sole()->is($event));
        $this->assertTrue($event->payment->is($payment));
        $this->assertTrue($event->order->is($order));
    }

    public function test_a_line_still_finds_its_product_after_it_is_deleted(): void
    {
        $product = Product::factory()->create();
        $order = Order::factory()->withLine($product)->create();

        $product->delete();

        $line = $order->lines()->sole();
        $this->assertTrue($line->product->is($product));
        $this->assertTrue($product->orderLines()->sole()->is($line));
    }

    public function test_payment_states_are_whole(): void
    {
        $pending = Payment::factory()->create();
        $succeeded = Payment::factory()->succeeded()->create();
        $failed = Payment::factory()->failed()->create();
        $refunded = Payment::factory()->refunded()->create();

        $this->assertSame(PaymentStatus::Pending, $pending->status);
        $this->assertSame($pending->order->total_incl_vat_cents, $pending->amount_cents);
        $this->assertNull($pending->provider_payment_token);

        $this->assertSame(PaymentStatus::Succeeded, $succeeded->status);
        $this->assertNotNull($succeeded->provider_payment_token);
        $this->assertSame($succeeded->amount_cents, $succeeded->paid_amount_cents);
        $this->assertNotNull($succeeded->paid_at);

        $this->assertSame(PaymentStatus::Failed, $failed->status);

        $this->assertSame(PaymentStatus::Refunded, $refunded->status);
        $this->assertNotNull($refunded->refunded_at);
    }

    public function test_an_order_line_is_a_copy_not_a_view_of_the_product(): void
    {
        $order = Order::factory()->create();

        $this->assertInstanceOf(OrderLine::class, $order->lines()->sole());
        $this->assertNotEmpty($order->lines()->sole()->product_name);
    }
}

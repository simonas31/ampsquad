<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\Checkout\OrderTotals;
use Database\Seeders\OrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderSeederTest extends TestCase
{
    use RefreshDatabase;

    private const int STOCK = 100;

    private function seedProductsAndOrders(): void
    {
        Product::factory()->count(5)->inStock(self::STOCK)->create();

        $this->seed(OrderSeeder::class);
    }

    public function test_every_status_has_an_order(): void
    {
        $this->seedProductsAndOrders();

        foreach (OrderStatus::cases() as $status) {
            $this->assertTrue(
                Order::query()->where('status', $status)->exists(),
                "No seeded order is {$status->value}.",
            );
        }
    }

    public function test_one_order_is_unpaid_and_past_its_window_for_the_sweeper(): void
    {
        $this->seedProductsAndOrders();

        $this->assertSame(1, Order::query()
            ->where('status', OrderStatus::PendingPayment)
            ->where('payment_expires_at', '<', now()->subMinutes(Order::PAYMENT_WINDOW_MINUTES + Order::EXPIRY_GRACE_MINUTES))
            ->count());
    }

    public function test_every_order_has_lines_one_payment_and_amounts_that_add_up(): void
    {
        $this->seedProductsAndOrders();

        Order::query()->with('lines')->each(function (Order $order): void {
            $this->assertNotEmpty($order->lines, "{$order->number} has no lines");
            $this->assertSame(
                OrderTotals::forLines($order->lines, $order->shipping_excl_vat_cents)->toAttributes(),
                $order->only(['subtotal_excl_vat_cents', 'shipping_excl_vat_cents', 'shipping_incl_vat_cents', 'vat_cents', 'total_incl_vat_cents']),
                $order->number,
            );

            $payment = $order->payments()->sole();
            $this->assertSame($order->total_incl_vat_cents, $payment->amount_cents);
        });
    }

    public function test_the_stock_is_what_the_orders_say_it_is(): void
    {
        $this->seedProductsAndOrders();

        $holding = [OrderStatus::PendingPayment, OrderStatus::Paid, OrderStatus::Shipped];

        Product::query()->each(function (Product $product) use ($holding): void {
            $held = (int) $product->orderLines()
                ->whereHas('order', fn ($orders) => $orders->whereIn('status', $holding))
                ->sum('quantity');

            $this->assertSame(self::STOCK - $held, $product->stock_quantity, "Product {$product->id}");
        });

        $this->assertGreaterThan(0, Product::query()->where('stock_quantity', '<', self::STOCK)->count());
    }

    public function test_a_paid_order_has_a_succeeded_payment_and_its_event(): void
    {
        $this->seedProductsAndOrders();

        $paid = Order::query()->where('status', OrderStatus::Paid)->sole();

        $this->assertSame('succeeded', $paid->payments()->sole()->status->value);
        $this->assertSame('paid', $paid->paymentEvents()->sole()->outcome);
        $this->assertSame(Payment::query()->count(), Order::query()->count());
    }

    public function test_it_seeds_nothing_and_does_not_fail_when_there_are_too_few_products(): void
    {
        Product::factory()->count(2)->inStock(self::STOCK)->create();

        $this->seed(OrderSeeder::class);

        $this->assertSame(0, Order::query()->count());
    }
}

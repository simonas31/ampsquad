<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ShippingMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Product;
use Database\Factories\OrderFactory;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * One order in each status, so the admin panel has every case to show. The
 * stock of the products is kept true to what the orders say: an order that
 * is waiting for payment, paid or shipped has taken its units out of stock,
 * and one that was cancelled, expired or refunded has given them back, so
 * those leave the stock as it was.
 *
 * Needs products with some stock, which ProductSeeder and CatalogDemoSeeder
 * provide.
 */
class OrderSeeder extends Seeder
{
    private const int MINIMUM_PRODUCTS = 5;

    public function run(): void
    {
        $products = Product::query()
            ->active()
            ->where('stock_quantity', '>=', 10)
            ->inRandomOrder()
            ->limit(self::MINIMUM_PRODUCTS)
            ->get()
            ->values();

        if ($products->count() < self::MINIMUM_PRODUCTS) {
            $this->command?->warn('Not enough products in stock to seed orders; seed products first.');

            return;
        }

        [$a, $b, $c, $d, $e] = $products->all();

        $awaiting = $this->place(
            Order::factory()->awaitingPayment()->state(['created_at' => now()->subMinutes(4)]),
            [[$a, 2]],
            holdsStock: true,
            payment: Payment::factory(),
        );

        $paid = $this->place(
            Order::factory()->paid()->shippedBy(ShippingMethod::LpExpress, 399)->state([
                'created_at' => now()->subHours(3),
                'customer_note' => 'Please call before delivering.',
            ]),
            [[$a, 1], [$b, 3]],
            holdsStock: true,
            payment: Payment::factory()->succeeded(),
        );

        $this->place(
            Order::factory()->shipped()->shippedBy(ShippingMethod::Dpd, 450)->state(['created_at' => now()->subDays(2)]),
            [[$b, 1], [$c, 2]],
            holdsStock: true,
            payment: Payment::factory()->succeeded(),
        );

        $this->place(
            Order::factory()->cancelled()->state(['created_at' => now()->subDays(4)]),
            [[$d, 1]],
            holdsStock: false,
            payment: Payment::factory()->refunded(),
        );

        $this->place(
            Order::factory()->expired()->shippedBy(ShippingMethod::LietuvosPastas, 299)->state(['created_at' => now()->subDay()]),
            [[$e, 1]],
            holdsStock: false,
            payment: Payment::factory()->failed(),
        );

        $this->place(
            Order::factory()->refunded()->shippedBy(ShippingMethod::Omniva, 349)->inLocale('en')->state(['created_at' => now()->subDays(9)]),
            [[$c, 1], [$d, 1]],
            holdsStock: false,
            payment: Payment::factory()->refunded(),
        );

        // Past its window and unpaid: what the expiry sweeper picks up.
        $this->place(
            Order::factory()->pastItsWindow()->shippedBy(ShippingMethod::Omniva, 349)->inLocale('en')->state(['created_at' => now()->subMinutes(40)]),
            [[$e, 1]],
            holdsStock: true,
            payment: Payment::factory(),
        );

        PaymentEvent::factory()->processed('paid')->create([
            'order_id' => $paid->getKey(),
            'payment_id' => $paid->payments()->sole()->getKey(),
            'provider_status' => '1',
        ]);

        $this->command?->info("Seeded orders, the newest of them awaiting payment: {$awaiting->number}.");
    }

    /**
     * @param  list<array{Product, int}>  $lines
     */
    private function place(OrderFactory $order, array $lines, bool $holdsStock, PaymentFactory $payment): Order
    {
        return DB::transaction(function () use ($order, $lines, $holdsStock, $payment): Order {
            foreach ($lines as [$product, $quantity]) {
                $order = $order->withLine($product, $quantity);

                if ($holdsStock && ! Product::takeStock($product->getKey(), $quantity)) {
                    throw new RuntimeException("Not enough stock of product {$product->getKey()} to seed an order.");
                }
            }

            $order = $order->create();
            $payment->for($order)->create();

            return $order;
        });
    }
}

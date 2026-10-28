<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\ShippingMethod;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use App\Services\Checkout\OrderTotals;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An order awaiting payment, in Lithuanian, for pickup. It gets its lines
 * from withLine(), or one line of a new product when it has none, and its
 * amounts are worked out from them once they exist, so what is stored always
 * adds up. A status state only sets the status and its timestamps: the stock
 * and the payments that go with a status are the caller's to set up.
 *
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'locale' => 'lt',
            'vat_rate_percent' => Product::vatRatePercent(),
            'subtotal_excl_vat_cents' => 0,
            'vat_cents' => 0,
            'total_incl_vat_cents' => 0,
            'shipping_method' => ShippingMethod::Pickup,
            'shipping_label' => ShippingMethod::Pickup->labelIn('lt'),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->unique()->safeEmail(),
            'customer_phone' => '+3706'.fake()->numerify('#######'),
            'terms_accepted_at' => now(),
            'payment_expires_at' => now()->addMinutes(Order::PAYMENT_WINDOW_MINUTES),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Order $order): void {
            if (! $order->lines()->exists()) {
                OrderLine::factory()->for($order)->create();
            }

            $order->update(OrderTotals::forLines($order->lines()->get(), $order->shipping_excl_vat_cents)->toAttributes());
        });
    }

    /**
     * A line for the product as it is now: its name in the order's language,
     * its prices and the quantity, copied the way a real order copies them.
     */
    public function withLine(Product $product, int $quantity = 1): static
    {
        return $this->has(
            OrderLine::factory()->state(['product_id' => $product->getKey(), 'quantity' => $quantity]),
            'lines',
        );
    }

    /**
     * @param  int  $priceExclVatCents  What delivery costs without VAT.
     */
    public function shippedBy(ShippingMethod $method, int $priceExclVatCents = 0): static
    {
        $totals = OrderTotals::forLines([], $priceExclVatCents);

        // Shipping alone makes a whole order as far as the database can tell
        // (it checks the amounts add up when the row is inserted); the lines
        // are added to it afterwards.
        return $this->state(fn (array $attributes): array => [
            'shipping_method' => $method,
            'shipping_label' => $method->labelIn($attributes['locale']),
            'delivery_address' => $method->needsDeliveryAddress() ? fake()->address() : null,
            ...$totals->toAttributes(),
        ]);
    }

    public function inLocale(string $locale): static
    {
        return $this->state(fn (array $attributes): array => [
            'locale' => $locale,
            'shipping_label' => $attributes['shipping_method']->labelIn($locale),
        ]);
    }

    public function awaitingPayment(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::PendingPayment,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Shipped,
            'paid_at' => now()->subDay(),
            'shipped_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Expired,
            'payment_expires_at' => now()->subMinutes(Order::PAYMENT_WINDOW_MINUTES + Order::EXPIRY_GRACE_MINUTES + 1),
            'expired_at' => now(),
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Refunded,
            'paid_at' => now()->subDays(5),
            'shipped_at' => now()->subDays(3),
            'refunded_at' => now(),
        ]);
    }

    /**
     * Still unpaid, with its payment window (and the grace after it) over:
     * what the sweeper looks for.
     */
    public function pastItsWindow(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::PendingPayment,
            'payment_expires_at' => now()->subMinutes(Order::PAYMENT_WINDOW_MINUTES + Order::EXPIRY_GRACE_MINUTES + 1),
        ]);
    }
}

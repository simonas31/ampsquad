<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * One unit of a new product. The name, SKU and prices are copied from the
 * product after it is made, the way an order copies them, unless the test
 * gives them itself.
 *
 * @extends Factory<OrderLine>
 */
class OrderLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'quantity' => 1,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (OrderLine $line): void {
            if ($line->product_name !== null) {
                return;
            }

            $line->fill(OrderLine::snapshotOf($line->product, $line->quantity, $line->order?->locale ?? 'lt'));
        });
    }

    public function quantity(int $quantity): static
    {
        return $this->state(fn (array $attributes): array => [
            'quantity' => $quantity,
        ]);
    }
}

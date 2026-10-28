<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The slug is left out on purpose: HasSlug derives it from the name.
 *
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(3, true));

        return [
            'name' => ['lt' => $name, 'en' => $name],
            'excerpt' => [
                'lt' => fake('lt_LT')->sentence(10),
                'en' => fake('en_US')->sentence(10),
            ],
            'description' => [
                'lt' => fake('lt_LT')->paragraph(4),
                'en' => fake('en_US')->paragraph(4),
            ],
            'price_net_cents' => fake()->numberBetween(500, 50000),
            'stock_quantity' => 100,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function inStock(int $quantity = 5): static
    {
        return $this->state(fn (array $attributes): array => [
            'stock_quantity' => $quantity,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes): array => [
            'stock_quantity' => 0,
        ]);
    }

    /**
     * @param  int  $netCents  The price without VAT.
     */
    public function priced(int $netCents): static
    {
        return $this->state(fn (array $attributes): array => [
            'price_net_cents' => $netCents,
        ]);
    }
}

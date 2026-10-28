<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ShippingMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A courier that costs 4.50 without VAT.
 *
 * @extends Factory<ShippingMethod>
 */
class ShippingMethodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'name' => ['lt' => $name, 'en' => $name],
            'description' => [
                'lt' => 'Pristatome per 2-3 darbo dienas.',
                'en' => 'Delivered within 2-3 business days.',
            ],
            'price_excl_vat_cents' => 450,
            'is_pickup' => false,
            'is_active' => true,
        ];
    }

    /**
     * Collected at the shop: free, and no address asked for.
     */
    public function pickup(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => ['lt' => 'Atsiėmimas parduotuvėje', 'en' => 'Pickup at the store'],
            'description' => [
                'lt' => 'Rudmenos g. 5-7, Kaunas. Atsiėmimo laiką suderiname iš anksto.',
                'en' => 'Rudmenos g. 5-7, Kaunas. We agree the pickup time with you beforehand.',
            ],
            'price_excl_vat_cents' => 0,
            'is_pickup' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * @param  int  $exclVatCents  The price without VAT.
     */
    public function priced(int $exclVatCents): static
    {
        return $this->state(fn (array $attributes): array => [
            'price_excl_vat_cents' => $exclVatCents,
        ]);
    }

    public function named(string $name): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => ['lt' => $name, 'en' => $name],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An address in Lithuania, of a customer made along with it unless one is given.
 *
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'label' => null,
            'recipient_name' => fake()->name(),
            'phone' => '+3706'.fake()->numerify('#######'),
            'street' => fake()->streetAddress(),
            'city' => fake()->city(),
            'postal_code' => fake()->numerify('#####'),
            'country' => 'LT',
        ];
    }

    public function labelled(string $label): static
    {
        return $this->state(fn (): array => ['label' => $label]);
    }

    /**
     * The customer's default shipping address, as the address book sets it.
     */
    public function asDefault(): static
    {
        return $this->afterCreating(function (CustomerAddress $address): void {
            Customer::query()->whereKey($address->customer_id)->update(['default_shipping_address_id' => $address->getKey()]);
        });
    }
}

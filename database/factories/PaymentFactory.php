<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A payment started for an order and not finished, asking for the order's
 * total.
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => 'fake',
            'provider_reference' => fake()->unique()->bothify('T########'),
            'status' => PaymentStatus::Pending,
            'amount_cents' => fn (array $attributes): int => Order::query()->findOrFail($attributes['order_id'])->total_incl_vat_cents,
            'redirect_url' => fake()->url(),
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Succeeded,
            'provider_payment_token' => fake()->unique()->bothify('p_########????'),
            'paid_amount_cents' => $attributes['amount_cents'],
            'channel' => 'banklink',
            'bank' => 'swedbank',
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Failed,
        ]);
    }

    public function refunded(): static
    {
        return $this->succeeded()->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Refunded,
            'refunded_at' => now(),
        ]);
    }
}

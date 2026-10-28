<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PaymentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A message from a payment provider that has been received and not yet
 * handled.
 *
 * @extends Factory<PaymentEvent>
 */
class PaymentEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'fake',
            'event_key' => hash('sha256', fake()->unique()->uuid()),
            'provider_status' => '1',
            'payload' => [],
        ];
    }

    public function processed(string $outcome): static
    {
        return $this->state(fn (array $attributes): array => [
            'outcome' => $outcome,
            'processed_at' => now(),
        ]);
    }
}

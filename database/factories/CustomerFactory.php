<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A verified customer who writes in Lithuanian, whose password is "password".
 *
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * The password every customer has, hashed once: hashing is slow on purpose.
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'phone' => '+3706'.fake()->numerify('#######'),
            'locale' => 'lt',
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }

    public function inLocale(string $locale): static
    {
        return $this->state(fn (): array => ['locale' => $locale]);
    }

    public function withMarketingConsent(): static
    {
        return $this->state(fn (): array => ['marketing_consent_at' => now()]);
    }

    /**
     * A deleted account as the delete leaves it: soft deleted, its address
     * moved to retained_email, which frees the address for a new account.
     */
    public function deleted(?CarbonInterface $at = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'deleted_at' => $at ?? now(),
            'retained_email' => $attributes['email'],
            'email' => null,
        ]);
    }
}

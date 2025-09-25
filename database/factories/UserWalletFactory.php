<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserWallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserWallet>
 */
class UserWalletFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'balance' => $this->faker->randomFloat(2, 0, 1000),
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'GBP', 'INR']),
            'metadata' => [
                'created_via' => 'factory',
                'initial_balance' => 0.00,
                'last_transaction_at' => $this->faker->dateTime()->format('c')
            ]
        ];
    }

    /**
     * Indicate that the wallet has zero balance.
     */
    public function empty(): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => 0.00,
        ]);
    }

    /**
     * Indicate that the wallet has a specific balance.
     */
    public function balance(float $balance): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => $balance,
        ]);
    }

    /**
     * Indicate that the wallet uses USD currency.
     */
    public function usd(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => 'USD',
        ]);
    }

    /**
     * Indicate that the wallet uses EUR currency.
     */
    public function eur(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => 'EUR',
        ]);
    }

    /**
     * Indicate that the wallet uses INR currency.
     */
    public function inr(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => 'INR',
        ]);
    }

    /**
     * Create a wallet for specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create a wallet with high balance.
     */
    public function wealthy(): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => $this->faker->randomFloat(2, 1000, 10000),
        ]);
    }

    /**
     * Create a wallet with low balance.
     */
    public function lowBalance(): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => $this->faker->randomFloat(2, 0.01, 10),
        ]);
    }
}
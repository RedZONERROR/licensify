<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::USER,
            '2fa_enabled' => false,
            'privacy_policy_accepted_at' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::ADMIN,
        ]);
    }

    /**
     * Indicate that the user is a developer.
     */
    public function developer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::DEVELOPER,
        ]);
    }

    /**
     * Indicate that the user is a reseller.
     */
    public function reseller(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::RESELLER,
        ]);
    }

    /**
     * Indicate that the user has 2FA enabled.
     */
    public function with2FA(): static
    {
        return $this->state(fn (array $attributes) => [
            '2fa_enabled' => true,
            '2fa_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        ]);
    }
}
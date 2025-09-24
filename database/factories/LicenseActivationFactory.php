<?php

namespace Database\Factories;

use App\Models\License;
use App\Models\LicenseActivation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LicenseActivation>
 */
class LicenseActivationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'license_id' => License::factory(),
            'device_hash' => fake()->sha256(),
            'device_info' => [
                'os' => fake()->randomElement(['Windows 11', 'macOS Ventura', 'Ubuntu 22.04']),
                'browser' => fake()->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
                'device_name' => fake()->userName() . "'s " . fake()->randomElement(['Laptop', 'Desktop', 'Workstation']),
                'ip_address' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
            ],
            'activated_at' => now(),
            'last_seen_at' => fake()->optional(0.8)->dateTimeBetween('-1 week', 'now'),
        ];
    }

    /**
     * Indicate that the activation was recently active.
     */
    public function recentlyActive(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_seen_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ]);
    }

    /**
     * Indicate that the activation is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_seen_at' => fake()->optional(0.5)->dateTimeBetween('-1 month', '-2 hours'),
        ]);
    }

    /**
     * Set a specific device hash.
     */
    public function withDeviceHash(string $deviceHash): static
    {
        return $this->state(fn (array $attributes) => [
            'device_hash' => $deviceHash,
        ]);
    }
}
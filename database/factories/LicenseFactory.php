<?php

namespace Database\Factories;

use App\Models\License;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\License>
 */
class LicenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'owner_id' => User::factory(),
            'user_id' => fake()->optional(0.7)->randomElement([User::factory()]),
            'license_key' => fake()->uuid(),
            'status' => fake()->randomElement([
                License::STATUS_ACTIVE,
                License::STATUS_EXPIRED,
                License::STATUS_SUSPENDED,
                License::STATUS_RESET,
            ]),
            'device_type' => fake()->optional(0.8)->randomElement(['desktop', 'mobile', 'web', 'server']),
            'max_devices' => fake()->numberBetween(1, 10),
            'expires_at' => fake()->optional(0.8)->dateTimeBetween('now', '+2 years'),
            'metadata' => fake()->optional(0.3)->randomElement([
                ['version' => '1.0', 'features' => ['basic']],
                ['version' => '2.0', 'features' => ['basic', 'premium']],
                ['custom_field' => 'custom_value'],
            ]),
        ];
    }

    /**
     * Indicate that the license is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => License::STATUS_ACTIVE,
            'expires_at' => fake()->dateTimeBetween('+1 day', '+2 years'),
        ]);
    }

    /**
     * Indicate that the license is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => License::STATUS_EXPIRED,
            'expires_at' => fake()->dateTimeBetween('-2 years', '-1 day'),
        ]);
    }

    /**
     * Indicate that the license is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => License::STATUS_SUSPENDED,
        ]);
    }

    /**
     * Indicate that the license needs reset.
     */
    public function needsReset(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => License::STATUS_RESET,
        ]);
    }

    /**
     * Set specific owner.
     */
    public function ownedBy(User $owner): static
    {
        return $this->state(fn (array $attributes) => [
            'owner_id' => $owner->id,
        ]);
    }

    /**
     * Set specific user.
     */
    public function assignedTo(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Set specific product.
     */
    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
        ]);
    }

    /**
     * Set max devices.
     */
    public function withMaxDevices(int $maxDevices): static
    {
        return $this->state(fn (array $attributes) => [
            'max_devices' => $maxDevices,
        ]);
    }

    /**
     * Set specific license key.
     */
    public function withKey(string $licenseKey): static
    {
        return $this->state(fn (array $attributes) => [
            'license_key' => $licenseKey,
        ]);
    }
}
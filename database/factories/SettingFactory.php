<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setting>
 */
class SettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = [
            Setting::CATEGORY_GENERAL,
            Setting::CATEGORY_EMAIL,
            Setting::CATEGORY_STORAGE,
            Setting::CATEGORY_INTEGRATIONS,
            Setting::CATEGORY_DEVOPS,
            Setting::CATEGORY_SECURITY,
        ];

        $types = [
            Setting::TYPE_STRING,
            Setting::TYPE_INTEGER,
            Setting::TYPE_BOOLEAN,
            Setting::TYPE_JSON,
        ];

        return [
            'key' => fake()->unique()->slug(2, '_'),
            'value' => fake()->word(),
            'type' => fake()->randomElement($types),
            'category' => fake()->randomElement($categories),
            'is_encrypted' => false,
            'description' => fake()->optional(0.7)->sentence(),
        ];
    }

    /**
     * Indicate that the setting is encrypted.
     */
    public function encrypted(): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => fake()->randomElement(['smtp_password', 'api_secret_key', 's3_secret_key', 'telegram_bot_token']),
            'type' => Setting::TYPE_ENCRYPTED,
            'is_encrypted' => true,
        ]);
    }

    /**
     * Create a string setting.
     */
    public function string(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => fake()->sentence(),
            'type' => Setting::TYPE_STRING,
        ]);
    }

    /**
     * Create an integer setting.
     */
    public function integer(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => fake()->numberBetween(1, 1000),
            'type' => Setting::TYPE_INTEGER,
        ]);
    }

    /**
     * Create a boolean setting.
     */
    public function boolean(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => fake()->boolean(),
            'type' => Setting::TYPE_BOOLEAN,
        ]);
    }

    /**
     * Create a JSON setting.
     */
    public function json(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => json_encode([
                'key1' => fake()->word(),
                'key2' => fake()->numberBetween(1, 100),
                'key3' => fake()->boolean(),
            ]),
            'type' => Setting::TYPE_JSON,
        ]);
    }

    /**
     * Create a general category setting.
     */
    public function general(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => Setting::CATEGORY_GENERAL,
            'key' => fake()->randomElement(['site_name', 'site_url', 'admin_email', 'timezone']),
        ]);
    }

    /**
     * Create an email category setting.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => Setting::CATEGORY_EMAIL,
            'key' => fake()->randomElement(['smtp_host', 'smtp_port', 'smtp_username', 'from_email']),
        ]);
    }

    /**
     * Create a storage category setting.
     */
    public function storage(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => Setting::CATEGORY_STORAGE,
            'key' => fake()->randomElement(['s3_access_key', 's3_bucket', 's3_region']),
        ]);
    }

    /**
     * Create an integrations category setting.
     */
    public function integrations(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => Setting::CATEGORY_INTEGRATIONS,
            'key' => fake()->randomElement(['telegram_chat_id', 'stripe_webhook_secret']),
        ]);
    }

    /**
     * Create a devops category setting.
     */
    public function devops(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => Setting::CATEGORY_DEVOPS,
            'key' => fake()->randomElement(['backup_schedule', 'backup_retention_days', 'monitoring_enabled']),
        ]);
    }
}
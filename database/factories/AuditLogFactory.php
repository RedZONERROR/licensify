<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $actions = [
            AuditLog::ACTION_CREATE,
            AuditLog::ACTION_UPDATE,
            AuditLog::ACTION_DELETE,
            AuditLog::ACTION_LOGIN,
            AuditLog::ACTION_LOGOUT,
            AuditLog::ACTION_LICENSE_VALIDATE,
        ];

        return [
            'user_id' => User::factory(),
            'action' => fake()->randomElement($actions),
            'auditable_type' => fake()->optional(0.7)->randomElement([User::class, 'App\Models\License', 'App\Models\Setting']),
            'auditable_id' => fake()->optional(0.7)->numberBetween(1, 100),
            'old_values' => fake()->optional(0.5)->randomElement([
                ['name' => 'Old Name', 'status' => 'inactive'],
                ['email' => 'old@example.com'],
                ['role' => 'user'],
            ]),
            'new_values' => fake()->optional(0.5)->randomElement([
                ['name' => 'New Name', 'status' => 'active'],
                ['email' => 'new@example.com'],
                ['role' => 'admin'],
            ]),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'metadata' => fake()->optional(0.3)->randomElement([
                ['source' => 'web'],
                ['api_version' => 'v1'],
                ['request_id' => fake()->uuid()],
            ]),
        ];
    }

    /**
     * Indicate that this is a login action.
     */
    public function login(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => AuditLog::ACTION_LOGIN,
            'auditable_type' => null,
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => null,
        ]);
    }

    /**
     * Indicate that this is a logout action.
     */
    public function logout(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => AuditLog::ACTION_LOGOUT,
            'auditable_type' => null,
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => null,
        ]);
    }

    /**
     * Indicate that this is a sensitive action.
     */
    public function sensitive(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => fake()->randomElement([
                AuditLog::ACTION_PASSWORD_RESET,
                AuditLog::ACTION_2FA_ENABLE,
                AuditLog::ACTION_2FA_DISABLE,
                AuditLog::ACTION_BACKUP_RESTORE,
                AuditLog::ACTION_SETTINGS_UPDATE,
            ]),
        ]);
    }

    /**
     * Indicate that this is a license action.
     */
    public function licenseAction(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => fake()->randomElement([
                AuditLog::ACTION_LICENSE_VALIDATE,
                AuditLog::ACTION_LICENSE_SUSPEND,
                AuditLog::ACTION_LICENSE_RESET,
            ]),
            'auditable_type' => 'App\Models\License',
            'auditable_id' => fake()->numberBetween(1, 100),
        ]);
    }

    /**
     * Indicate that this is a backup action.
     */
    public function backupAction(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => fake()->randomElement([
                AuditLog::ACTION_BACKUP_CREATE,
                AuditLog::ACTION_BACKUP_RESTORE,
            ]),
            'auditable_type' => 'App\Models\Backup',
            'auditable_id' => fake()->numberBetween(1, 100),
        ]);
    }

    /**
     * Create a recent audit log.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Create an old audit log.
     */
    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => fake()->dateTimeBetween('-1 year', '-31 days'),
        ]);
    }
}
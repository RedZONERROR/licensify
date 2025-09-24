<?php

namespace Database\Factories;

use App\Models\Backup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Backup>
 */
class BackupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'backup-' . fake()->dateTime()->format('Y-m-d-H-i-s');
        
        return [
            'name' => $name,
            'filename' => $name . '.zip',
            'path' => 'backups/' . $name . '.zip',
            'size' => fake()->numberBetween(1048576, 1073741824), // 1MB to 1GB
            'checksum' => fake()->sha256(),
            'status' => fake()->randomElement([
                Backup::STATUS_COMPLETED,
                Backup::STATUS_RUNNING,
                Backup::STATUS_FAILED,
                Backup::STATUS_PENDING,
            ]),
            'type' => fake()->randomElement([
                Backup::TYPE_MANUAL,
                Backup::TYPE_SCHEDULED,
                Backup::TYPE_PRE_RESTORE,
            ]),
            'created_by' => User::factory(),
            'metadata' => [
                'database_size' => fake()->numberBetween(1048576, 104857600), // 1MB to 100MB
                'files_count' => fake()->numberBetween(100, 10000),
                'compression_ratio' => fake()->randomFloat(2, 0.1, 0.9),
                'backup_version' => '1.0',
            ],
            'expires_at' => fake()->dateTimeBetween('+1 week', '+1 month'),
            'completed_at' => fake()->optional(0.8)->dateTimeBetween('-1 week', 'now'),
            'error_message' => null,
        ];
    }

    /**
     * Indicate that the backup is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Backup::STATUS_COMPLETED,
            'completed_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'error_message' => null,
        ]);
    }

    /**
     * Indicate that the backup is running.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Backup::STATUS_RUNNING,
            'completed_at' => null,
            'error_message' => null,
        ]);
    }

    /**
     * Indicate that the backup failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Backup::STATUS_FAILED,
            'completed_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'error_message' => fake()->randomElement([
                'Disk space insufficient',
                'Database connection failed',
                'Permission denied',
                'Timeout occurred',
                'Encryption failed',
            ]),
        ]);
    }

    /**
     * Indicate that the backup is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Backup::STATUS_PENDING,
            'completed_at' => null,
            'error_message' => null,
            'size' => null,
            'checksum' => null,
        ]);
    }

    /**
     * Indicate that the backup is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Backup::STATUS_EXPIRED,
            'expires_at' => fake()->dateTimeBetween('-1 month', '-1 day'),
        ]);
    }

    /**
     * Indicate that the backup is manual.
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Backup::TYPE_MANUAL,
        ]);
    }

    /**
     * Indicate that the backup is scheduled.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Backup::TYPE_SCHEDULED,
        ]);
    }

    /**
     * Indicate that the backup is pre-restore.
     */
    public function preRestore(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Backup::TYPE_PRE_RESTORE,
        ]);
    }

    /**
     * Create a recent backup.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Create an old backup.
     */
    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => fake()->dateTimeBetween('-6 months', '-1 month'),
        ]);
    }

    /**
     * Create a large backup.
     */
    public function large(): static
    {
        return $this->state(fn (array $attributes) => [
            'size' => fake()->numberBetween(1073741824, 10737418240), // 1GB to 10GB
        ]);
    }

    /**
     * Create a small backup.
     */
    public function small(): static
    {
        return $this->state(fn (array $attributes) => [
            'size' => fake()->numberBetween(1048576, 10485760), // 1MB to 10MB
        ]);
    }
}
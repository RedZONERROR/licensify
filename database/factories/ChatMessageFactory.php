<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sender_id' => User::factory(),
            'receiver_id' => User::factory(),
            'body' => fake()->sentence(),
            'message_type' => ChatMessage::TYPE_TEXT,
            'metadata' => null,
            'read_at' => fake()->optional(0.6)->dateTimeBetween('-1 week', 'now'),
        ];
    }

    /**
     * Indicate that the message is unread.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => null,
        ]);
    }

    /**
     * Indicate that the message is read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the message is a file message.
     */
    public function fileMessage(): static
    {
        return $this->state(fn (array $attributes) => [
            'message_type' => ChatMessage::TYPE_FILE,
            'body' => 'File attachment',
            'metadata' => [
                'file' => [
                    'name' => fake()->word() . '.' . fake()->fileExtension(),
                    'size' => fake()->numberBetween(1024, 10485760), // 1KB to 10MB
                    'type' => fake()->mimeType(),
                    'path' => 'uploads/chat/' . fake()->uuid() . '.' . fake()->fileExtension(),
                ],
            ],
        ]);
    }

    /**
     * Indicate that the message is a system message.
     */
    public function systemMessage(): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_id' => null,
            'message_type' => ChatMessage::TYPE_SYSTEM,
            'body' => fake()->randomElement([
                'User joined the conversation',
                'User left the conversation',
                'License was activated',
                'License was suspended',
                'System maintenance scheduled',
            ]),
            'metadata' => [
                'system_action' => fake()->randomElement(['user_join', 'user_leave', 'license_action', 'maintenance']),
            ],
        ]);
    }

    /**
     * Set specific sender and receiver.
     */
    public function between(User $sender, User $receiver): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
        ]);
    }

    /**
     * Create a recent message.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => fake()->dateTimeBetween('-24 hours', 'now'),
        ]);
    }
}
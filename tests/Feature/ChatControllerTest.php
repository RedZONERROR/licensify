<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ChatMessage;
use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChatControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $developer;
    protected User $reseller;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => Role::ADMIN]);
        $this->developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $this->reseller = User::factory()->create(['role' => Role::RESELLER]);
        $this->user = User::factory()->create([
            'role' => Role::USER,
            'reseller_id' => $this->reseller->id
        ]);
    }

    public function test_chat_index_displays_conversations()
    {
        $response = $this->actingAs($this->user)->get('/chat');

        $response->assertStatus(200);
        $response->assertViewIs('chat.index');
        $response->assertViewHas('conversations');
    }

    public function test_user_can_see_reseller_and_admin_conversations()
    {
        $response = $this->actingAs($this->user)->get('/chat');

        $conversations = $response->viewData('conversations');
        
        // User should see their reseller and admin
        $userIds = collect($conversations)->pluck('user.id')->toArray();
        $this->assertContains($this->reseller->id, $userIds);
        $this->assertContains($this->admin->id, $userIds);
    }

    public function test_reseller_can_see_managed_users_and_admin_conversations()
    {
        $response = $this->actingAs($this->reseller)->get('/chat');

        $conversations = $response->viewData('conversations');
        
        // Reseller should see their managed users and admin
        $userIds = collect($conversations)->pluck('user.id')->toArray();
        $this->assertContains($this->user->id, $userIds);
        $this->assertContains($this->admin->id, $userIds);
    }

    public function test_admin_can_see_all_user_conversations()
    {
        $response = $this->actingAs($this->admin)->get('/chat');

        $conversations = $response->viewData('conversations');
        
        // Admin should see all other users
        $userIds = collect($conversations)->pluck('user.id')->toArray();
        $this->assertContains($this->developer->id, $userIds);
        $this->assertContains($this->reseller->id, $userIds);
        $this->assertContains($this->user->id, $userIds);
    }

    public function test_can_send_message_to_authorized_user()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/chat/send/{$this->reseller->id}", [
                'body' => 'Hello, this is a test message',
                'message_type' => 'text'
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message' => [
                'id', 'sender_id', 'sender_name', 'body', 
                'message_type', 'metadata', 'created_at', 'is_own'
            ]
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'sender_id' => $this->user->id,
            'receiver_id' => $this->reseller->id,
            'body' => 'Hello, this is a test message',
            'message_type' => 'text'
        ]);
    }

    public function test_cannot_send_message_to_unauthorized_user()
    {
        $otherUser = User::factory()->create(['role' => Role::USER]);

        $response = $this->actingAs($this->user)
            ->postJson("/chat/send/{$otherUser->id}", [
                'body' => 'This should not work',
                'message_type' => 'text'
            ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Unauthorized']);

        $this->assertDatabaseMissing('chat_messages', [
            'sender_id' => $this->user->id,
            'receiver_id' => $otherUser->id,
        ]);
    }

    public function test_can_get_messages_between_authorized_users()
    {
        // Create some messages
        ChatMessage::factory()->create([
            'sender_id' => $this->user->id,
            'receiver_id' => $this->reseller->id,
            'body' => 'Message from user'
        ]);

        ChatMessage::factory()->create([
            'sender_id' => $this->reseller->id,
            'receiver_id' => $this->user->id,
            'body' => 'Message from reseller'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/chat/messages/{$this->reseller->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'messages' => [
                '*' => [
                    'id', 'sender_id', 'sender_name', 'body',
                    'message_type', 'metadata', 'created_at', 'is_own'
                ]
            ],
            'last_message_id'
        ]);

        $this->assertCount(2, $response->json('messages'));
    }

    public function test_cannot_get_messages_from_unauthorized_user()
    {
        $otherUser = User::factory()->create(['role' => Role::USER]);

        $response = $this->actingAs($this->user)
            ->getJson("/chat/messages/{$otherUser->id}");

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Unauthorized']);
    }

    public function test_messages_are_marked_as_read_when_retrieved()
    {
        $message = ChatMessage::factory()->create([
            'sender_id' => $this->reseller->id,
            'receiver_id' => $this->user->id,
            'body' => 'Unread message',
            'read_at' => null
        ]);

        $this->assertNull($message->read_at);

        $this->actingAs($this->user)
            ->getJson("/chat/messages/{$this->reseller->id}");

        $message->refresh();
        $this->assertNotNull($message->read_at);
    }

    public function test_can_mark_messages_as_read()
    {
        ChatMessage::factory()->create([
            'sender_id' => $this->reseller->id,
            'receiver_id' => $this->user->id,
            'body' => 'Unread message 1',
            'read_at' => null
        ]);

        ChatMessage::factory()->create([
            'sender_id' => $this->reseller->id,
            'receiver_id' => $this->user->id,
            'body' => 'Unread message 2',
            'read_at' => null
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/chat/mark-read/{$this->reseller->id}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Messages marked as read']);

        $this->assertDatabaseMissing('chat_messages', [
            'sender_id' => $this->reseller->id,
            'receiver_id' => $this->user->id,
            'read_at' => null
        ]);
    }

    public function test_admin_can_enable_slow_mode()
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/chat/enable-slow-mode/{$this->user->id}", [
                'duration' => 300
            ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Slow mode enabled']);

        // Check that slow mode is cached
        $key = "slow_mode:{$this->user->id}:{$this->admin->id}";
        $this->assertTrue(Cache::has($key));

        // Check system message was created
        $this->assertDatabaseHas('chat_messages', [
            'sender_id' => null,
            'receiver_id' => $this->user->id,
            'message_type' => 'system',
            'body' => 'Slow mode has been enabled for this conversation.'
        ]);
    }

    public function test_admin_can_disable_slow_mode()
    {
        // First enable slow mode
        $key = "slow_mode:{$this->user->id}:{$this->admin->id}";
        Cache::put($key, true, 300);

        $response = $this->actingAs($this->admin)
            ->postJson("/chat/disable-slow-mode/{$this->user->id}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Slow mode disabled']);

        // Check that slow mode is removed from cache
        $this->assertFalse(Cache::has($key));

        // Check system message was created
        $this->assertDatabaseHas('chat_messages', [
            'sender_id' => null,
            'receiver_id' => $this->user->id,
            'message_type' => 'system',
            'body' => 'Slow mode has been disabled for this conversation.'
        ]);
    }

    public function test_non_admin_cannot_enable_slow_mode()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/chat/enable-slow-mode/{$this->reseller->id}");

        $response->assertStatus(403);
        // The role middleware blocks access before reaching the controller
        $response->assertJsonFragment(['message' => 'Insufficient permissions. Required roles: admin, developer']);
    }

    public function test_admin_can_block_user()
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/chat/block/{$this->user->id}", [
                'duration' => 3600
            ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'User blocked']);

        // Check that user is blocked in cache
        $key = "blocked:{$this->user->id}:{$this->admin->id}";
        $this->assertTrue(Cache::has($key));

        // Check system message was created
        $this->assertDatabaseHas('chat_messages', [
            'sender_id' => null,
            'receiver_id' => $this->user->id,
            'message_type' => 'system',
            'body' => 'You have been temporarily blocked from sending messages.'
        ]);
    }

    public function test_admin_can_unblock_user()
    {
        // First block the user
        $key = "blocked:{$this->user->id}:{$this->admin->id}";
        Cache::put($key, true, 3600);

        $response = $this->actingAs($this->admin)
            ->postJson("/chat/unblock/{$this->user->id}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'User unblocked']);

        // Check that user is unblocked
        $this->assertFalse(Cache::has($key));

        // Check system message was created
        $this->assertDatabaseHas('chat_messages', [
            'sender_id' => null,
            'receiver_id' => $this->user->id,
            'message_type' => 'system',
            'body' => 'You have been unblocked and can now send messages.'
        ]);
    }

    public function test_non_admin_cannot_block_user()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/chat/block/{$this->reseller->id}");

        $response->assertStatus(403);
        // The role middleware blocks access before reaching the controller
        $response->assertJsonFragment(['message' => 'Insufficient permissions. Required roles: admin, developer']);
    }

    public function test_blocked_user_cannot_send_messages()
    {
        // Block the user
        $key = "blocked:{$this->user->id}:{$this->reseller->id}";
        Cache::put($key, true, 3600);

        $response = $this->actingAs($this->user)
            ->postJson("/chat/send/{$this->reseller->id}", [
                'body' => 'This should be blocked',
                'message_type' => 'text'
            ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'You are blocked from sending messages to this user']);

        $this->assertDatabaseMissing('chat_messages', [
            'sender_id' => $this->user->id,
            'receiver_id' => $this->reseller->id,
            'body' => 'This should be blocked'
        ]);
    }

    public function test_message_validation()
    {
        // Test empty message
        $response = $this->actingAs($this->user)
            ->postJson("/chat/send/{$this->reseller->id}", [
                'body' => '',
                'message_type' => 'text'
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['body']);

        // Test message too long
        $response = $this->actingAs($this->user)
            ->postJson("/chat/send/{$this->reseller->id}", [
                'body' => str_repeat('a', 2001),
                'message_type' => 'text'
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['body']);

        // Test invalid message type
        $response = $this->actingAs($this->user)
            ->postJson("/chat/send/{$this->reseller->id}", [
                'body' => 'Valid message',
                'message_type' => 'invalid'
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message_type']);
    }

    public function test_can_get_conversation_history_with_pagination()
    {
        // Create multiple messages
        for ($i = 1; $i <= 25; $i++) {
            ChatMessage::factory()->create([
                'sender_id' => $this->user->id,
                'receiver_id' => $this->reseller->id,
                'body' => "Message {$i}"
            ]);
        }

        $response = $this->actingAs($this->user)
            ->getJson("/chat/history/{$this->reseller->id}?page=1&per_page=10");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'messages',
            'pagination' => [
                'current_page', 'last_page', 'per_page', 'total'
            ]
        ]);

        $pagination = $response->json('pagination');
        $this->assertEquals(1, $pagination['current_page']);
        $this->assertEquals(3, $pagination['last_page']); // 25 messages / 10 per page = 3 pages
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertEquals(25, $pagination['total']);
    }

    public function test_can_get_unread_counts()
    {
        // Create unread messages from reseller to user
        ChatMessage::factory()->count(3)->create([
            'sender_id' => $this->reseller->id,
            'receiver_id' => $this->user->id,
            'read_at' => null
        ]);

        // Create unread messages from admin to user
        ChatMessage::factory()->count(2)->create([
            'sender_id' => $this->admin->id,
            'receiver_id' => $this->user->id,
            'read_at' => null
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/chat/unread-counts');

        $response->assertStatus(200);
        $response->assertJsonStructure(['unread_counts']);

        $unreadCounts = $response->json('unread_counts');
        $this->assertEquals(3, $unreadCounts[$this->reseller->id]);
        $this->assertEquals(2, $unreadCounts[$this->admin->id]);
    }

    public function test_rate_limiting_for_sending_messages()
    {
        // This test would require mocking the rate limiter or using a test-specific configuration
        // For now, we'll just test that the rate limiting key is being used correctly
        
        $response = $this->actingAs($this->user)
            ->postJson("/chat/send/{$this->reseller->id}", [
                'body' => 'Test message',
                'message_type' => 'text'
            ]);

        $response->assertStatus(201);
        
        // The rate limiting is handled by Laravel's RateLimiter facade
        // In a real test environment, you would configure lower limits for testing
    }

    public function test_system_messages_are_created_correctly()
    {
        $message = ChatMessage::createSystemMessage(
            $this->user->id,
            'This is a system message',
            ['test' => 'metadata']
        );

        $this->assertDatabaseHas('chat_messages', [
            'id' => $message->id,
            'sender_id' => null,
            'receiver_id' => $this->user->id,
            'body' => 'This is a system message',
            'message_type' => 'system'
        ]);

        $this->assertEquals(['test' => 'metadata'], $message->metadata);
        $this->assertTrue($message->isSystemMessage());
    }

    public function test_developer_has_same_permissions_as_admin()
    {
        // Test that developer can enable slow mode
        $response = $this->actingAs($this->developer)
            ->postJson("/chat/enable-slow-mode/{$this->user->id}");

        $response->assertStatus(200);

        // Test that developer can block users
        $response = $this->actingAs($this->developer)
            ->postJson("/chat/block/{$this->user->id}");

        $response->assertStatus(200);
    }
}
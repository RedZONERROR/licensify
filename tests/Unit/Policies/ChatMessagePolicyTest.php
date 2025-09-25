<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Models\ChatMessage;
use App\Policies\ChatMessagePolicy;
use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatMessagePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected ChatMessagePolicy $policy;
    protected User $admin;
    protected User $developer;
    protected User $reseller;
    protected User $user;
    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ChatMessagePolicy();
        $this->admin = User::factory()->create(['role' => Role::ADMIN]);
        $this->developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $this->reseller = User::factory()->create(['role' => Role::RESELLER]);
        $this->user = User::factory()->create([
            'role' => Role::USER,
            'reseller_id' => $this->reseller->id
        ]);
        $this->otherUser = User::factory()->create(['role' => Role::USER]);
    }

    public function test_admin_can_view_any_message()
    {
        $message = ChatMessage::factory()->create([
            'sender_id' => $this->user->id,
            'receiver_id' => $this->otherUser->id
        ]);

        $this->assertTrue($this->policy->view($this->admin, $message));
    }

    public function test_developer_can_view_any_message()
    {
        $message = ChatMessage::factory()->create([
            'sender_id' => $this->user->id,
            'receiver_id' => $this->otherUser->id
        ]);

        $this->assertTrue($this->policy->view($this->developer, $message));
    }

    public function test_user_can_view_own_sent_message()
    {
        $message = ChatMessage::factory()->create([
            'sender_id' => $this->user->id,
            'receiver_id' => $this->reseller->id
        ]);

        $this->assertTrue($this->policy->view($this->user, $message));
    }

    public function test_user_can_view_own_received_message()
    {
        $message = ChatMessage::factory()->create([
            'sender_id' => $this->reseller->id,
            'receiver_id' => $this->user->id
        ]);

        $this->assertTrue($this->policy->view($this->user, $message));
    }

    public function test_user_cannot_view_others_messages()
    {
        $message = ChatMessage::factory()->create([
            'sender_id' => $this->otherUser->id,
            'receiver_id' => $this->reseller->id
        ]);

        $this->assertFalse($this->policy->view($this->user, $message));
    }

    public function test_reseller_can_view_messages_with_managed_users()
    {
        $message = ChatMessage::factory()->create([
            'sender_id' => $this->reseller->id,
            'receiver_id' => $this->user->id
        ]);

        $this->assertTrue($this->policy->view($this->reseller, $message));

        $message2 = ChatMessage::factory()->create([
            'sender_id' => $this->user->id,
            'receiver_id' => $this->reseller->id
        ]);

        $this->assertTrue($this->policy->view($this->reseller, $message2));
    }

    public function test_reseller_cannot_view_messages_with_non_managed_users()
    {
        $message = ChatMessage::factory()->create([
            'sender_id' => $this->otherUser->id,
            'receiver_id' => $this->admin->id
        ]);

        $this->assertFalse($this->policy->view($this->reseller, $message));
    }

    public function test_user_can_create_message_to_reseller()
    {
        $this->assertTrue($this->policy->create($this->user));
    }

    public function test_user_can_create_message_to_admin()
    {
        $this->assertTrue($this->policy->create($this->user));
    }

    public function test_reseller_can_create_message()
    {
        $this->assertTrue($this->policy->create($this->reseller));
    }

    public function test_admin_can_create_message()
    {
        $this->assertTrue($this->policy->create($this->admin));
    }

    public function test_developer_can_create_message()
    {
        $this->assertTrue($this->policy->create($this->developer));
    }

    public function test_user_can_update_own_sent_message()
    {
        $message = ChatMessage::factory()->create([
            'sender_id' => $this->user->id,
            'receiver_id' => $this->reseller->id,
            'created_at' => now()->subMinutes(4), // Within edit window
            'message_type' => 'text'
        ]);

        $this->assertTrue($this->policy->update($this->user, $message));
    }

    public function test_user_cannot_update_others_message()
    {
        $message = ChatMessage::factory()->create([
            'sender_id' => $this->reseller->id,
            'receiver_id' => $this->user->id
        ]);

        $this->assertFalse($this->policy->update($this->user, $message));
    }

    public function test_user_cannot_update_old_message()
    {
        $message = ChatMessage::factory()->create([
            'sender_id' => $this->user->id,
            'receiver_id' => $this->reseller->id,
            'created_at' => now()->subHours(2) // Outside edit window
        ]);

        $this->assertFalse($this->policy->update($this->user, $message));
    }

    public function test_admin_can_update_any_message()
    {
        $message = ChatMessage::factory()->create([
            'sender_id' => $this->user->id,
            'receiver_id' => $this->reseller->id,
            'created_at' => now()->subHours(2)
        ]);

        $this->assertTrue($this->policy->update($this->admin, $message));
    }

    public function test_developer_can_update_any_message()
    {
        $message = ChatMessage::factory()->create([
            'sender_id' => $this->user->id,
            'receiver_id' => $this->reseller->id,
            'created_at' => now()->subHours(2)
        ]);

        $this->assertTrue($this->policy->update($this->developer, $message));
    }

    public function test_user_can_delete_own_sent_message()
    {
        $message = ChatMessage::factory()->create([
            'sender_id' => $this->user->id,
            'receiver_id' => $this->reseller->id,
            'created_at' => now()->subMinutes(4) // Within delete window
        ]);

        $this->assertTrue($this->policy->delete($this->user, $message));
    }

    public function test_user_cannot_delete_others_message()
    {
        $message = ChatMessage::factory()->create([
            'sender_id' => $this->reseller->id,
            'receiver_id' => $this->user->id
        ]);

        $this->assertFalse($this->policy->delete($this->user, $message));
    }

    public function test_user_cannot_delete_old_message()
    {
        $message = ChatMessage::factory()->create([
            'sender_id' => $this->user->id,
            'receiver_id' => $this->reseller->id,
            'created_at' => now()->subHours(2) // Outside delete window
        ]);

        $this->assertFalse($this->policy->delete($this->user, $message));
    }

    public function test_admin_can_delete_any_message()
    {
        $message = ChatMessage::factory()->create([
            'sender_id' => $this->user->id,
            'receiver_id' => $this->reseller->id,
            'created_at' => now()->subHours(2)
        ]);

        $this->assertTrue($this->policy->delete($this->admin, $message));
    }

    public function test_developer_can_delete_any_message()
    {
        $message = ChatMessage::factory()->create([
            'sender_id' => $this->user->id,
            'receiver_id' => $this->reseller->id,
            'created_at' => now()->subHours(2)
        ]);

        $this->assertTrue($this->policy->delete($this->developer, $message));
    }

    public function test_system_messages_cannot_be_updated_by_regular_users()
    {
        $message = ChatMessage::factory()->create([
            'sender_id' => null,
            'receiver_id' => $this->user->id,
            'message_type' => 'system'
        ]);

        $this->assertFalse($this->policy->update($this->user, $message));
        $this->assertFalse($this->policy->update($this->reseller, $message));
    }

    public function test_system_messages_can_be_updated_by_admin()
    {
        $message = ChatMessage::factory()->create([
            'sender_id' => null,
            'receiver_id' => $this->user->id,
            'message_type' => 'system'
        ]);

        $this->assertTrue($this->policy->update($this->admin, $message));
        $this->assertTrue($this->policy->update($this->developer, $message));
    }

    public function test_system_messages_cannot_be_deleted_by_regular_users()
    {
        $message = ChatMessage::factory()->create([
            'sender_id' => null,
            'receiver_id' => $this->user->id,
            'message_type' => 'system'
        ]);

        $this->assertFalse($this->policy->delete($this->user, $message));
        $this->assertFalse($this->policy->delete($this->reseller, $message));
    }

    public function test_system_messages_can_be_deleted_by_admin()
    {
        $message = ChatMessage::factory()->create([
            'sender_id' => null,
            'receiver_id' => $this->user->id,
            'message_type' => 'system'
        ]);

        $this->assertTrue($this->policy->delete($this->admin, $message));
        $this->assertTrue($this->policy->delete($this->developer, $message));
    }
}
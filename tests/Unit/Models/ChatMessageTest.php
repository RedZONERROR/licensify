<?php

namespace Tests\Unit\Models;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_message_has_type_constants()
    {
        $this->assertEquals('text', ChatMessage::TYPE_TEXT);
        $this->assertEquals('file', ChatMessage::TYPE_FILE);
        $this->assertEquals('system', ChatMessage::TYPE_SYSTEM);
    }

    public function test_chat_message_sets_default_type_on_creation()
    {
        $message = ChatMessage::factory()->create(['message_type' => null]);

        $this->assertEquals(ChatMessage::TYPE_TEXT, $message->message_type);
    }

    public function test_chat_message_belongs_to_sender_and_receiver()
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $message = ChatMessage::factory()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id
        ]);

        $this->assertInstanceOf(User::class, $message->sender);
        $this->assertEquals($sender->id, $message->sender->id);

        $this->assertInstanceOf(User::class, $message->receiver);
        $this->assertEquals($receiver->id, $message->receiver->id);
    }

    public function test_chat_message_can_be_marked_as_read()
    {
        $message = ChatMessage::factory()->create(['read_at' => null]);

        $this->assertTrue($message->isUnread());
        $this->assertFalse($message->isRead());

        $this->assertTrue($message->markAsRead());
        $message->refresh();

        $this->assertTrue($message->isRead());
        $this->assertFalse($message->isUnread());
        $this->assertNotNull($message->read_at);
    }

    public function test_chat_message_type_checks()
    {
        $textMessage = ChatMessage::factory()->create(['message_type' => ChatMessage::TYPE_TEXT]);
        $fileMessage = ChatMessage::factory()->create(['message_type' => ChatMessage::TYPE_FILE]);
        $systemMessage = ChatMessage::factory()->create(['message_type' => ChatMessage::TYPE_SYSTEM]);

        $this->assertFalse($textMessage->isSystemMessage());
        $this->assertFalse($textMessage->isFileMessage());

        $this->assertTrue($fileMessage->isFileMessage());
        $this->assertFalse($fileMessage->isSystemMessage());

        $this->assertTrue($systemMessage->isSystemMessage());
        $this->assertFalse($systemMessage->isFileMessage());
    }

    public function test_chat_message_handles_file_info()
    {
        $fileMessage = ChatMessage::factory()->create([
            'message_type' => ChatMessage::TYPE_FILE,
            'metadata' => [
                'file' => [
                    'name' => 'document.pdf',
                    'size' => 1024,
                    'type' => 'application/pdf'
                ]
            ]
        ]);

        $textMessage = ChatMessage::factory()->create([
            'message_type' => ChatMessage::TYPE_TEXT,
            'metadata' => null
        ]);

        $fileInfo = $fileMessage->getFileInfo();
        $this->assertIsArray($fileInfo);
        $this->assertEquals('document.pdf', $fileInfo['name']);

        $this->assertNull($textMessage->getFileInfo());
    }

    public function test_chat_message_formats_body()
    {
        $textMessage = ChatMessage::factory()->create([
            'message_type' => ChatMessage::TYPE_TEXT,
            'body' => 'Hello world'
        ]);

        $fileMessage = ChatMessage::factory()->create([
            'message_type' => ChatMessage::TYPE_FILE,
            'body' => 'File attachment',
            'metadata' => [
                'file' => ['name' => 'document.pdf']
            ]
        ]);

        $fileMessageWithoutInfo = ChatMessage::factory()->create([
            'message_type' => ChatMessage::TYPE_FILE,
            'body' => 'File attachment',
            'metadata' => null
        ]);

        $this->assertEquals('Hello world', $textMessage->getFormattedBody());
        $this->assertEquals('File: document.pdf', $fileMessage->getFormattedBody());
        $this->assertEquals('File attachment', $fileMessageWithoutInfo->getFormattedBody());
    }

    public function test_chat_message_scopes()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $message1to2 = ChatMessage::factory()->create([
            'sender_id' => $user1->id,
            'receiver_id' => $user2->id
        ]);

        $message2to1 = ChatMessage::factory()->create([
            'sender_id' => $user2->id,
            'receiver_id' => $user1->id
        ]);

        $message1to3 = ChatMessage::factory()->create([
            'sender_id' => $user1->id,
            'receiver_id' => $user3->id
        ]);

        $readMessage = ChatMessage::factory()->create([
            'read_at' => now()
        ]);

        $unreadMessage = ChatMessage::factory()->create([
            'read_at' => null
        ]);

        $systemMessage = ChatMessage::factory()->create([
            'message_type' => ChatMessage::TYPE_SYSTEM
        ]);

        $recentMessage = ChatMessage::factory()->create([
            'created_at' => now()->subHours(12)
        ]);

        $oldMessage = ChatMessage::factory()->create([
            'created_at' => now()->subDays(2)
        ]);

        // Test between users scope
        $betweenUsers = ChatMessage::betweenUsers($user1->id, $user2->id)->get();
        $this->assertTrue($betweenUsers->contains($message1to2));
        $this->assertTrue($betweenUsers->contains($message2to1));
        $this->assertFalse($betweenUsers->contains($message1to3));

        // Test read/unread scopes
        $readMessages = ChatMessage::read()->get();
        $this->assertTrue($readMessages->contains($readMessage));
        $this->assertFalse($readMessages->contains($unreadMessage));

        $unreadMessages = ChatMessage::unread()->get();
        $this->assertTrue($unreadMessages->contains($unreadMessage));
        $this->assertFalse($unreadMessages->contains($readMessage));

        // Test sent by scope
        $sentByUser1 = ChatMessage::sentBy($user1->id)->get();
        $this->assertTrue($sentByUser1->contains($message1to2));
        $this->assertTrue($sentByUser1->contains($message1to3));
        $this->assertFalse($sentByUser1->contains($message2to1));

        // Test received by scope
        $receivedByUser2 = ChatMessage::receivedBy($user2->id)->get();
        $this->assertTrue($receivedByUser2->contains($message1to2));
        $this->assertFalse($receivedByUser2->contains($message2to1));

        // Test involving user scope
        $involvingUser1 = ChatMessage::involvingUser($user1->id)->get();
        $this->assertTrue($involvingUser1->contains($message1to2));
        $this->assertTrue($involvingUser1->contains($message2to1));
        $this->assertTrue($involvingUser1->contains($message1to3));

        // Test system messages scope
        $systemMessages = ChatMessage::systemMessages()->get();
        $this->assertTrue($systemMessages->contains($systemMessage));
        $this->assertFalse($systemMessages->contains($message1to2));

        // Test recent scope
        $recentMessages = ChatMessage::recent(24)->get();
        $this->assertTrue($recentMessages->contains($recentMessage));
        $this->assertFalse($recentMessages->contains($oldMessage));
    }

    public function test_chat_message_can_create_system_message()
    {
        $receiver = User::factory()->create();
        $body = 'System notification';
        $metadata = ['type' => 'notification'];

        $systemMessage = ChatMessage::createSystemMessage($receiver->id, $body, $metadata);

        $this->assertNull($systemMessage->sender_id);
        $this->assertEquals($receiver->id, $systemMessage->receiver_id);
        $this->assertEquals($body, $systemMessage->body);
        $this->assertEquals($metadata, $systemMessage->metadata);
        $this->assertEquals(ChatMessage::TYPE_SYSTEM, $systemMessage->message_type);
    }

    public function test_chat_message_casts_attributes_correctly()
    {
        $message = ChatMessage::factory()->create([
            'metadata' => ['key' => 'value'],
            'read_at' => '2023-01-01 12:00:00'
        ]);

        $this->assertIsArray($message->metadata);
        $this->assertEquals(['key' => 'value'], $message->metadata);
        $this->assertInstanceOf(\Carbon\Carbon::class, $message->read_at);
    }
}
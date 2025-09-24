<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMessage extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'body',
        'metadata',
        'read_at',
        'message_type',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'json',
            'read_at' => 'datetime',
        ];
    }

    /**
     * Message type constants
     */
    public const TYPE_TEXT = 'text';
    public const TYPE_FILE = 'file';
    public const TYPE_SYSTEM = 'system';

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($message) {
            if (empty($message->message_type)) {
                $message->message_type = self::TYPE_TEXT;
            }
        });
    }

    /**
     * Get the sender of this message
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get the receiver of this message
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * Mark message as read
     */
    public function markAsRead(): bool
    {
        if ($this->read_at) {
            return true;
        }

        return $this->update(['read_at' => now()]);
    }

    /**
     * Check if message is read
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Check if message is unread
     */
    public function isUnread(): bool
    {
        return !$this->isRead();
    }

    /**
     * Check if message is a system message
     */
    public function isSystemMessage(): bool
    {
        return $this->message_type === self::TYPE_SYSTEM;
    }

    /**
     * Check if message is a file message
     */
    public function isFileMessage(): bool
    {
        return $this->message_type === self::TYPE_FILE;
    }

    /**
     * Get file information from metadata
     */
    public function getFileInfo(): ?array
    {
        if (!$this->isFileMessage() || !$this->metadata) {
            return null;
        }

        return $this->metadata['file'] ?? null;
    }

    /**
     * Get formatted message body
     */
    public function getFormattedBody(): string
    {
        if ($this->isFileMessage()) {
            $fileInfo = $this->getFileInfo();
            return $fileInfo ? "File: {$fileInfo['name']}" : 'File attachment';
        }

        return $this->body;
    }

    /**
     * Scope for messages between two users
     */
    public function scopeBetweenUsers($query, int $user1Id, int $user2Id)
    {
        return $query->where(function ($q) use ($user1Id, $user2Id) {
            $q->where(function ($subQ) use ($user1Id, $user2Id) {
                $subQ->where('sender_id', $user1Id)
                     ->where('receiver_id', $user2Id);
            })->orWhere(function ($subQ) use ($user1Id, $user2Id) {
                $subQ->where('sender_id', $user2Id)
                     ->where('receiver_id', $user1Id);
            });
        });
    }

    /**
     * Scope for unread messages
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope for read messages
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope for messages sent by user
     */
    public function scopeSentBy($query, int $userId)
    {
        return $query->where('sender_id', $userId);
    }

    /**
     * Scope for messages received by user
     */
    public function scopeReceivedBy($query, int $userId)
    {
        return $query->where('receiver_id', $userId);
    }

    /**
     * Scope for messages involving user (sent or received)
     */
    public function scopeInvolvingUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('sender_id', $userId)
              ->orWhere('receiver_id', $userId);
        });
    }

    /**
     * Scope for recent messages
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope for system messages
     */
    public function scopeSystemMessages($query)
    {
        return $query->where('message_type', self::TYPE_SYSTEM);
    }

    /**
     * Create a system message
     */
    public static function createSystemMessage(int $receiverId, string $body, array $metadata = []): self
    {
        return self::create([
            'sender_id' => null,
            'receiver_id' => $receiverId,
            'body' => $body,
            'metadata' => $metadata,
            'message_type' => self::TYPE_SYSTEM,
        ]);
    }
}
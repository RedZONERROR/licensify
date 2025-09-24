<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Backup extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'filename',
        'path',
        'size',
        'checksum',
        'status',
        'type',
        'created_by',
        'metadata',
        'expires_at',
        'completed_at',
        'error_message',
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
            'size' => 'integer',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Backup status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';

    /**
     * Backup type constants
     */
    public const TYPE_MANUAL = 'manual';
    public const TYPE_SCHEDULED = 'scheduled';
    public const TYPE_PRE_RESTORE = 'pre_restore';

    /**
     * Get the user who created this backup
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if backup is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if backup is running
     */
    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * Check if backup failed
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if backup is expired
     */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED || 
               ($this->expires_at && $this->expires_at->isPast());
    }

    /**
     * Check if backup file exists
     */
    public function fileExists(): bool
    {
        return $this->path && Storage::exists($this->path);
    }

    /**
     * Get formatted file size
     */
    public function getFormattedSize(): string
    {
        if (!$this->size) {
            return 'Unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = $this->size;
        
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get backup duration
     */
    public function getDuration(): ?int
    {
        if (!$this->completed_at) {
            return null;
        }

        return $this->created_at->diffInSeconds($this->completed_at);
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDuration(): string
    {
        $duration = $this->getDuration();
        
        if (!$duration) {
            return 'Unknown';
        }

        if ($duration < 60) {
            return $duration . 's';
        } elseif ($duration < 3600) {
            return round($duration / 60, 1) . 'm';
        } else {
            return round($duration / 3600, 1) . 'h';
        }
    }

    /**
     * Mark backup as running
     */
    public function markAsRunning(): bool
    {
        return $this->update(['status' => self::STATUS_RUNNING]);
    }

    /**
     * Mark backup as completed
     */
    public function markAsCompleted(int $size = null, string $checksum = null): bool
    {
        $data = [
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ];

        if ($size !== null) {
            $data['size'] = $size;
        }

        if ($checksum !== null) {
            $data['checksum'] = $checksum;
        }

        return $this->update($data);
    }

    /**
     * Mark backup as failed
     */
    public function markAsFailed(string $errorMessage = null): bool
    {
        return $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark backup as expired
     */
    public function markAsExpired(): bool
    {
        return $this->update(['status' => self::STATUS_EXPIRED]);
    }

    /**
     * Verify backup integrity
     */
    public function verifyIntegrity(): bool
    {
        if (!$this->fileExists() || !$this->checksum) {
            return false;
        }

        $fileChecksum = hash_file('sha256', Storage::path($this->path));
        return hash_equals($this->checksum, $fileChecksum);
    }

    /**
     * Get download URL
     */
    public function getDownloadUrl(): ?string
    {
        if (!$this->isCompleted() || !$this->fileExists()) {
            return null;
        }

        return Storage::temporaryUrl($this->path, now()->addHour());
    }

    /**
     * Delete backup file
     */
    public function deleteFile(): bool
    {
        if ($this->fileExists()) {
            return Storage::delete($this->path);
        }

        return true;
    }

    /**
     * Get metadata value
     */
    public function getMetadata(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Set metadata value
     */
    public function setMetadata(string $key, $value): bool
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        
        return $this->update(['metadata' => $metadata]);
    }

    /**
     * Scope for completed backups
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for failed backups
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for expired backups
     */
    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('status', self::STATUS_EXPIRED)
              ->orWhere('expires_at', '<', now());
        });
    }

    /**
     * Scope for manual backups
     */
    public function scopeManual($query)
    {
        return $query->where('type', self::TYPE_MANUAL);
    }

    /**
     * Scope for scheduled backups
     */
    public function scopeScheduled($query)
    {
        return $query->where('type', self::TYPE_SCHEDULED);
    }

    /**
     * Scope for recent backups
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for backups created by user
     */
    public function scopeCreatedBy($query, int $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Create a new backup record
     */
    public static function createBackup(
        string $name,
        string $type = self::TYPE_MANUAL,
        ?User $creator = null,
        array $metadata = []
    ): self {
        return self::create([
            'name' => $name,
            'filename' => $name . '.zip',
            'status' => self::STATUS_PENDING,
            'type' => $type,
            'created_by' => $creator?->id,
            'metadata' => $metadata,
            'expires_at' => now()->addDays(Setting::get('backup_retention_days', 30)),
        ]);
    }
}
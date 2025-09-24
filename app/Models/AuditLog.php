<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'json',
            'new_values' => 'json',
            'metadata' => 'json',
        ];
    }

    /**
     * Action type constants
     */
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_PASSWORD_RESET = 'password_reset';
    public const ACTION_2FA_ENABLE = '2fa_enable';
    public const ACTION_2FA_DISABLE = '2fa_disable';
    public const ACTION_LICENSE_VALIDATE = 'license_validate';
    public const ACTION_LICENSE_SUSPEND = 'license_suspend';
    public const ACTION_LICENSE_RESET = 'license_reset';
    public const ACTION_BACKUP_CREATE = 'backup_create';
    public const ACTION_BACKUP_RESTORE = 'backup_restore';
    public const ACTION_SETTINGS_UPDATE = 'settings_update';

    /**
     * Get the user who performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the auditable model
     */
    public function auditable()
    {
        return $this->morphTo();
    }

    /**
     * Get formatted action description
     */
    public function getActionDescription(): string
    {
        $descriptions = [
            self::ACTION_CREATE => 'Created',
            self::ACTION_UPDATE => 'Updated',
            self::ACTION_DELETE => 'Deleted',
            self::ACTION_LOGIN => 'Logged in',
            self::ACTION_LOGOUT => 'Logged out',
            self::ACTION_PASSWORD_RESET => 'Reset password',
            self::ACTION_2FA_ENABLE => 'Enabled 2FA',
            self::ACTION_2FA_DISABLE => 'Disabled 2FA',
            self::ACTION_LICENSE_VALIDATE => 'Validated license',
            self::ACTION_LICENSE_SUSPEND => 'Suspended license',
            self::ACTION_LICENSE_RESET => 'Reset license devices',
            self::ACTION_BACKUP_CREATE => 'Created backup',
            self::ACTION_BACKUP_RESTORE => 'Restored backup',
            self::ACTION_SETTINGS_UPDATE => 'Updated settings',
        ];

        return $descriptions[$this->action] ?? ucfirst(str_replace('_', ' ', $this->action));
    }

    /**
     * Get the model name being audited
     */
    public function getModelName(): string
    {
        if (!$this->auditable_type) {
            return 'System';
        }

        return class_basename($this->auditable_type);
    }

    /**
     * Get changes summary
     */
    public function getChangesSummary(): array
    {
        $changes = [];

        if ($this->old_values && $this->new_values) {
            foreach ($this->new_values as $key => $newValue) {
                $oldValue = $this->old_values[$key] ?? null;
                if ($oldValue !== $newValue) {
                    $changes[$key] = [
                        'old' => $oldValue,
                        'new' => $newValue,
                    ];
                }
            }
        }

        return $changes;
    }

    /**
     * Check if this is a sensitive action
     */
    public function isSensitiveAction(): bool
    {
        $sensitiveActions = [
            self::ACTION_PASSWORD_RESET,
            self::ACTION_2FA_ENABLE,
            self::ACTION_2FA_DISABLE,
            self::ACTION_BACKUP_RESTORE,
            self::ACTION_SETTINGS_UPDATE,
        ];

        return in_array($this->action, $sensitiveActions);
    }

    /**
     * Scope for specific action
     */
    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope for specific user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for specific model type
     */
    public function scopeForModel($query, string $modelType)
    {
        return $query->where('auditable_type', $modelType);
    }

    /**
     * Scope for sensitive actions
     */
    public function scopeSensitive($query)
    {
        return $query->whereIn('action', [
            self::ACTION_PASSWORD_RESET,
            self::ACTION_2FA_ENABLE,
            self::ACTION_2FA_DISABLE,
            self::ACTION_BACKUP_RESTORE,
            self::ACTION_SETTINGS_UPDATE,
        ]);
    }

    /**
     * Scope for recent logs
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Create an audit log entry
     */
    public static function log(
        string $action,
        ?User $user = null,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = []
    ): self {
        return self::create([
            'user_id' => $user?->id,
            'action' => $action,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable?->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
        ]);
    }
}
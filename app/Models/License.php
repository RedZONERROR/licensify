<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class License extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'owner_id',
        'user_id',
        'license_key',
        'status',
        'device_type',
        'max_devices',
        'expires_at',
        'metadata',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'deleted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'metadata' => 'json',
            'max_devices' => 'integer',
        ];
    }

    /**
     * License status constants
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_RESET = 'reset';
    public const STATUS_PENDING = 'pending';

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($license) {
            if (empty($license->license_key)) {
                $license->license_key = Str::uuid()->toString();
            }
        });
    }

    /**
     * Get the owner of this license
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the user assigned to this license
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the product this license belongs to
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get license activations for device tracking
     */
    public function activations(): HasMany
    {
        return $this->hasMany(LicenseActivation::class);
    }

    /**
     * Check if license is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && 
               ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Check if license is expired
     */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED || 
               ($this->expires_at !== null && $this->expires_at->isPast());
    }

    /**
     * Check if license is suspended
     */
    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /**
     * Check if license needs device reset
     */
    public function needsReset(): bool
    {
        return $this->status === self::STATUS_RESET;
    }

    /**
     * Check if license is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Suspend the license
     */
    public function suspend(): bool
    {
        return $this->update(['status' => self::STATUS_SUSPENDED]);
    }

    /**
     * Unsuspend the license
     */
    public function unsuspend(): bool
    {
        return $this->update(['status' => self::STATUS_ACTIVE]);
    }

    /**
     * Reset device bindings
     */
    public function resetDeviceBindings(): bool
    {
        $this->activations()->delete();
        return $this->update(['status' => self::STATUS_RESET]);
    }

    /**
     * Expire the license
     */
    public function expire(): bool
    {
        return $this->update([
            'status' => self::STATUS_EXPIRED,
            'expires_at' => now()
        ]);
    }

    /**
     * Check if device can be bound to this license
     */
    public function canBindDevice(): bool
    {
        return $this->isActive() && 
               $this->activations()->count() < $this->max_devices;
    }

    /**
     * Get active device count
     */
    public function getActiveDeviceCount(): int
    {
        return $this->activations()->count();
    }

    /**
     * Check if device is already bound
     */
    public function isDeviceBound(string $deviceHash): bool
    {
        return $this->activations()
            ->where('device_hash', $deviceHash)
            ->exists();
    }

    /**
     * Bind device to license
     */
    public function bindDevice(string $deviceHash, array $deviceInfo = []): ?LicenseActivation
    {
        if (!$this->canBindDevice() || $this->isDeviceBound($deviceHash)) {
            return null;
        }

        return $this->activations()->create([
            'device_hash' => $deviceHash,
            'device_info' => $deviceInfo,
            'activated_at' => now(),
        ]);
    }

    /**
     * Unbind device from license
     */
    public function unbindDevice(string $deviceHash): bool
    {
        return $this->activations()
            ->where('device_hash', $deviceHash)
            ->delete() > 0;
    }

    /**
     * Scope for active licenses
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * Scope for expired licenses
     */
    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('status', self::STATUS_EXPIRED)
              ->orWhere('expires_at', '<=', now());
        });
    }

    /**
     * Scope for licenses owned by user
     */
    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('owner_id', $userId);
    }

    /**
     * Scope for licenses assigned to user
     */
    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for pending licenses
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
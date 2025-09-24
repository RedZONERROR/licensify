<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseActivation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'license_id',
        'device_hash',
        'device_info',
        'activated_at',
        'last_seen_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'device_info' => 'json',
            'activated_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($activation) {
            if (empty($activation->activated_at)) {
                $activation->activated_at = now();
            }
        });
    }

    /**
     * Get the license this activation belongs to
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    /**
     * Update last seen timestamp
     */
    public function updateLastSeen(): bool
    {
        return $this->update(['last_seen_at' => now()]);
    }

    /**
     * Check if device was recently active
     */
    public function isRecentlyActive(int $minutes = 60): bool
    {
        return $this->last_seen_at && 
               $this->last_seen_at->diffInMinutes(now()) <= $minutes;
    }

    /**
     * Get device information as formatted string
     */
    public function getDeviceInfoString(): string
    {
        if (!$this->device_info) {
            return 'Unknown Device';
        }

        $info = $this->device_info;
        $parts = [];

        if (isset($info['os'])) {
            $parts[] = $info['os'];
        }
        if (isset($info['browser'])) {
            $parts[] = $info['browser'];
        }
        if (isset($info['device_name'])) {
            $parts[] = $info['device_name'];
        }

        return implode(' - ', $parts) ?: 'Unknown Device';
    }

    /**
     * Scope for recently active devices
     */
    public function scopeRecentlyActive($query, int $minutes = 60)
    {
        return $query->where('last_seen_at', '>=', now()->subMinutes($minutes));
    }

    /**
     * Scope for inactive devices
     */
    public function scopeInactive($query, int $minutes = 60)
    {
        return $query->where(function ($q) use ($minutes) {
            $q->whereNull('last_seen_at')
              ->orWhere('last_seen_at', '<', now()->subMinutes($minutes));
        });
    }

    /**
     * Scope for specific device hash
     */
    public function scopeForDevice($query, string $deviceHash)
    {
        return $query->where('device_hash', $deviceHash);
    }
}
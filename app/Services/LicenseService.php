<?php

namespace App\Services;

use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LicenseService
{
    /**
     * Generate a new license
     */
    public function generateLicense(array $data): License
    {
        // Validate required fields
        $this->validateLicenseData($data);

        // Ensure product exists and is active
        $product = Product::active()->findOrFail($data['product_id']);

        // Ensure owner exists
        $owner = User::findOrFail($data['owner_id']);

        // Validate user if provided
        if (isset($data['user_id'])) {
            User::findOrFail($data['user_id']);
        }

        return DB::transaction(function () use ($data, $product) {
            $license = License::create([
                'product_id' => $data['product_id'],
                'owner_id' => $data['owner_id'],
                'user_id' => $data['user_id'] ?? null,
                'license_key' => $this->generateUniqueKey(),
                'status' => $data['status'] ?? License::STATUS_ACTIVE,
                'device_type' => $data['device_type'] ?? null,
                'max_devices' => $data['max_devices'] ?? 1,
                'expires_at' => $data['expires_at'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            Log::info('License generated', [
                'license_id' => $license->id,
                'license_key' => $license->license_key,
                'owner_id' => $license->owner_id,
                'product_id' => $license->product_id,
            ]);

            return $license;
        });
    }

    /**
     * Update license status
     */
    public function updateStatus(License $license, string $status): bool
    {
        $validStatuses = [
            License::STATUS_ACTIVE,
            License::STATUS_EXPIRED,
            License::STATUS_SUSPENDED,
            License::STATUS_RESET,
        ];

        if (!in_array($status, $validStatuses)) {
            throw new InvalidArgumentException("Invalid status: {$status}");
        }

        $oldStatus = $license->status;
        $updated = $license->update(['status' => $status]);

        if ($updated) {
            Log::info('License status updated', [
                'license_id' => $license->id,
                'old_status' => $oldStatus,
                'new_status' => $status,
            ]);
        }

        return $updated;
    }

    /**
     * Suspend a license
     */
    public function suspendLicense(License $license): bool
    {
        return $this->updateStatus($license, License::STATUS_SUSPENDED);
    }

    /**
     * Unsuspend a license
     */
    public function unsuspendLicense(License $license): bool
    {
        return $this->updateStatus($license, License::STATUS_ACTIVE);
    }

    /**
     * Reset device bindings for a license
     */
    public function resetDeviceBindings(License $license): bool
    {
        return DB::transaction(function () use ($license) {
            // Delete all activations
            $deletedCount = $license->activations()->delete();
            
            // Update status to reset
            $updated = $this->updateStatus($license, License::STATUS_RESET);

            Log::info('License device bindings reset', [
                'license_id' => $license->id,
                'devices_removed' => $deletedCount,
            ]);

            return $updated;
        });
    }

    /**
     * Expire a license
     */
    public function expireLicense(License $license): bool
    {
        return DB::transaction(function () use ($license) {
            $updated = $license->update([
                'status' => License::STATUS_EXPIRED,
                'expires_at' => now(),
            ]);

            if ($updated) {
                Log::info('License expired', [
                    'license_id' => $license->id,
                    'expired_at' => now(),
                ]);
            }

            return $updated;
        });
    }

    /**
     * Validate device and bind to license
     */
    public function validateAndBindDevice(string $licenseKey, string $deviceHash, array $deviceInfo = []): array
    {
        $license = License::where('license_key', $licenseKey)->first();

        if (!$license) {
            return [
                'valid' => false,
                'message' => 'License not found',
                'code' => 'LICENSE_NOT_FOUND',
            ];
        }

        // Check if license is expired first (more specific)
        if ($license->expires_at !== null && $license->expires_at->isPast()) {
            return [
                'valid' => false,
                'message' => 'License has expired',
                'code' => 'LICENSE_EXPIRED',
                'expires_at' => $license->expires_at,
            ];
        }

        // Check if license status is not active
        if ($license->status !== License::STATUS_ACTIVE) {
            return [
                'valid' => false,
                'message' => 'License is not active',
                'code' => 'LICENSE_INACTIVE',
                'status' => $license->status,
            ];
        }

        // Check if device is already bound
        if ($license->isDeviceBound($deviceHash)) {
            // Update last seen for existing device
            $activation = $license->activations()->where('device_hash', $deviceHash)->first();
            $activation?->updateLastSeen();

            return [
                'valid' => true,
                'message' => 'Device already bound',
                'code' => 'DEVICE_ALREADY_BOUND',
                'license' => $license,
                'activation' => $activation,
            ];
        }

        // Check if license can bind more devices
        if (!$license->canBindDevice()) {
            return [
                'valid' => false,
                'message' => 'Maximum device limit reached',
                'code' => 'DEVICE_LIMIT_REACHED',
                'max_devices' => $license->max_devices,
                'active_devices' => $license->getActiveDeviceCount(),
            ];
        }

        // Bind the device
        $activation = $license->bindDevice($deviceHash, $deviceInfo);

        if (!$activation) {
            return [
                'valid' => false,
                'message' => 'Failed to bind device',
                'code' => 'BINDING_FAILED',
            ];
        }

        Log::info('Device bound to license', [
            'license_id' => $license->id,
            'device_hash' => $deviceHash,
            'activation_id' => $activation->id,
        ]);

        return [
            'valid' => true,
            'message' => 'Device successfully bound',
            'code' => 'DEVICE_BOUND',
            'license' => $license,
            'activation' => $activation,
        ];
    }

    /**
     * Unbind device from license
     */
    public function unbindDevice(License $license, string $deviceHash): bool
    {
        $unbound = $license->unbindDevice($deviceHash);

        if ($unbound) {
            Log::info('Device unbound from license', [
                'license_id' => $license->id,
                'device_hash' => $deviceHash,
            ]);
        }

        return $unbound;
    }

    /**
     * Get licenses for a user (based on role)
     */
    public function getLicensesForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = License::with(['product', 'owner', 'user', 'activations']);

        // Apply role-based filtering
        if ($user->role->value === 'admin' || $user->role->value === 'developer') {
            // Admins and developers can see all licenses - no additional filtering
        } elseif ($user->role->value === 'reseller') {
            // Resellers can only see licenses they own or their users own
            $query->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                  ->orWhereHas('owner', function ($subQ) use ($user) {
                      $subQ->where('reseller_id', $user->id);
                  });
            });
        } else {
            // Regular users can only see licenses assigned to them
            $query->where('user_id', $user->id);
        }

        // Apply additional filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('license_key', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($subQ) use ($search) {
                      $subQ->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('user', function ($subQ) use ($search) {
                      $subQ->where('name', 'like', "%{$search}%")
                           ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        return $query->orderBy('created_at', 'desc')
                    ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get license statistics
     */
    public function getLicenseStatistics(User $user): array
    {
        $query = License::query();

        // Apply role-based filtering for statistics
        if ($user->role->value === 'reseller') {
            $query->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                  ->orWhereHas('owner', function ($subQ) use ($user) {
                      $subQ->where('reseller_id', $user->id);
                  });
            });
        } elseif ($user->role->value === 'user') {
            $query->where('user_id', $user->id);
        }

        return [
            'total' => $query->count(),
            'active' => $query->clone()->where('status', License::STATUS_ACTIVE)->count(),
            'expired' => $query->clone()->where('status', License::STATUS_EXPIRED)->count(),
            'suspended' => $query->clone()->where('status', License::STATUS_SUSPENDED)->count(),
            'expiring_soon' => $query->clone()
                ->where('status', License::STATUS_ACTIVE)
                ->where('expires_at', '<=', now()->addDays(30))
                ->whereNotNull('expires_at')
                ->count(),
        ];
    }

    /**
     * Generate a unique license key
     */
    private function generateUniqueKey(): string
    {
        do {
            $key = Str::uuid()->toString();
        } while (License::where('license_key', $key)->exists());

        return $key;
    }

    /**
     * Validate license data
     */
    private function validateLicenseData(array $data): void
    {
        $required = ['product_id', 'owner_id'];
        
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (isset($data['max_devices']) && $data['max_devices'] < 1) {
            throw new InvalidArgumentException("max_devices must be at least 1");
        }

        if (isset($data['status']) && !in_array($data['status'], [
            License::STATUS_ACTIVE,
            License::STATUS_EXPIRED,
            License::STATUS_SUSPENDED,
            License::STATUS_RESET,
        ])) {
            throw new InvalidArgumentException("Invalid status: {$data['status']}");
        }
    }
}
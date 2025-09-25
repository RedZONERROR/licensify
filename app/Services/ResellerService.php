<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\License;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ResellerService
{
    /**
     * Get all resellers with their quota information
     */
    public function getAllResellers(): Collection
    {
        return User::where('role', Role::RESELLER)
            ->withCount(['managedUsers', 'ownedLicenses'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get paginated resellers
     */
    public function getPaginatedResellers(int $perPage = 15): LengthAwarePaginator
    {
        return User::where('role', Role::RESELLER)
            ->withCount(['managedUsers', 'ownedLicenses'])
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Create a new reseller
     */
    public function createReseller(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $reseller = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => isset($data['password']) ? Hash::make($data['password']) : null,
                'role' => Role::RESELLER,
                'max_users_quota' => $data['max_users_quota'] ?? null,
                'max_licenses_quota' => $data['max_licenses_quota'] ?? null,
                'current_users_count' => 0,
                'current_licenses_count' => 0,
                'privacy_policy_accepted_at' => now(),
            ]);

            // Update counts
            $this->updateResellerCounts($reseller);

            return $reseller;
        });
    }

    /**
     * Update reseller information and quotas
     */
    public function updateReseller(User $reseller, array $data): User
    {
        if (!$reseller->isReseller()) {
            throw ValidationException::withMessages([
                'user' => 'User is not a reseller.'
            ]);
        }

        return DB::transaction(function () use ($reseller, $data) {
            // Check if reducing quotas would violate current usage
            if (isset($data['max_users_quota']) && $data['max_users_quota'] < $reseller->current_users_count) {
                throw ValidationException::withMessages([
                    'max_users_quota' => 'Cannot set user quota below current usage (' . $reseller->current_users_count . ').'
                ]);
            }

            if (isset($data['max_licenses_quota']) && $data['max_licenses_quota'] < $reseller->current_licenses_count) {
                throw ValidationException::withMessages([
                    'max_licenses_quota' => 'Cannot set license quota below current usage (' . $reseller->current_licenses_count . ').'
                ]);
            }

            $reseller->update([
                'name' => $data['name'] ?? $reseller->name,
                'email' => $data['email'] ?? $reseller->email,
                'max_users_quota' => $data['max_users_quota'] ?? $reseller->max_users_quota,
                'max_licenses_quota' => $data['max_licenses_quota'] ?? $reseller->max_licenses_quota,
            ]);

            if (isset($data['password']) && !empty($data['password'])) {
                $reseller->update(['password' => Hash::make($data['password'])]);
            }

            return $reseller->fresh();
        });
    }

    /**
     * Assign user to reseller
     */
    public function assignUserToReseller(User $reseller, User $user): bool
    {
        if (!$reseller->isReseller()) {
            throw ValidationException::withMessages([
                'reseller' => 'User is not a reseller.'
            ]);
        }

        if (!$user->isUser()) {
            throw ValidationException::withMessages([
                'user' => 'Can only assign regular users to resellers.'
            ]);
        }

        if (!$reseller->canAddUser()) {
            throw ValidationException::withMessages([
                'quota' => 'Reseller has reached maximum user quota.'
            ]);
        }

        return DB::transaction(function () use ($reseller, $user) {
            $user->update(['reseller_id' => $reseller->id]);
            $this->updateResellerCounts($reseller);
            return true;
        });
    }

    /**
     * Remove user from reseller
     */
    public function removeUserFromReseller(User $user): bool
    {
        if (!$user->reseller_id) {
            return false;
        }

        return DB::transaction(function () use ($user) {
            $reseller = $user->reseller;
            $user->update(['reseller_id' => null]);
            
            if ($reseller) {
                $this->updateResellerCounts($reseller);
            }
            
            return true;
        });
    }

    /**
     * Get reseller dashboard data
     */
    public function getResellerDashboardData(User $reseller): array
    {
        if (!$reseller->isReseller()) {
            throw ValidationException::withMessages([
                'user' => 'User is not a reseller.'
            ]);
        }

        $managedUsers = $reseller->managedUsers()
            ->withCount(['assignedLicenses'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $ownedLicenses = $reseller->ownedLicenses()
            ->with(['user', 'product'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return [
            'reseller' => $reseller,
            'stats' => [
                'total_users' => $reseller->current_users_count,
                'max_users' => $reseller->max_users_quota,
                'total_licenses' => $reseller->current_licenses_count,
                'max_licenses' => $reseller->max_licenses_quota,
                'user_quota_percentage' => $reseller->getUserQuotaUsagePercentage(),
                'license_quota_percentage' => $reseller->getLicenseQuotaUsagePercentage(),
                'active_licenses' => $reseller->ownedLicenses()->where('status', 'active')->count(),
                'expired_licenses' => $reseller->ownedLicenses()->where('status', 'expired')->count(),
            ],
            'recent_users' => $managedUsers,
            'recent_licenses' => $ownedLicenses,
            'quota_warnings' => [
                'user_near_limit' => $reseller->isUserQuotaNearLimit(),
                'license_near_limit' => $reseller->isLicenseQuotaNearLimit(),
            ]
        ];
    }

    /**
     * Get users managed by reseller with pagination
     */
    public function getResellerUsers(User $reseller, int $perPage = 15): LengthAwarePaginator
    {
        if (!$reseller->isReseller()) {
            throw ValidationException::withMessages([
                'user' => 'User is not a reseller.'
            ]);
        }

        return $reseller->managedUsers()
            ->withCount(['assignedLicenses'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get licenses owned by reseller with pagination
     */
    public function getResellerLicenses(User $reseller, int $perPage = 15): LengthAwarePaginator
    {
        if (!$reseller->isReseller()) {
            throw ValidationException::withMessages([
                'user' => 'User is not a reseller.'
            ]);
        }

        return $reseller->ownedLicenses()
            ->with(['user', 'product'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Update reseller counts
     */
    public function updateResellerCounts(User $reseller): void
    {
        if (!$reseller->isReseller()) {
            return;
        }

        $reseller->update([
            'current_users_count' => $reseller->managedUsers()->count(),
            'current_licenses_count' => $reseller->ownedLicenses()->count(),
        ]);
    }

    /**
     * Update all reseller counts
     */
    public function updateAllResellerCounts(): void
    {
        $resellers = User::where('role', Role::RESELLER)->get();
        
        foreach ($resellers as $reseller) {
            $this->updateResellerCounts($reseller);
        }
    }

    /**
     * Get available users that can be assigned to reseller
     */
    public function getAvailableUsers(): Collection
    {
        return User::where('role', Role::USER)
            ->whereNull('reseller_id')
            ->orderBy('name')
            ->get();
    }

    /**
     * Create user for reseller
     */
    public function createUserForReseller(User $reseller, array $data): User
    {
        if (!$reseller->isReseller()) {
            throw ValidationException::withMessages([
                'reseller' => 'User is not a reseller.'
            ]);
        }

        if (!$reseller->canAddUser()) {
            throw ValidationException::withMessages([
                'quota' => 'Reseller has reached maximum user quota.'
            ]);
        }

        return DB::transaction(function () use ($reseller, $data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => isset($data['password']) ? Hash::make($data['password']) : null,
                'role' => Role::USER,
                'reseller_id' => $reseller->id,
                'privacy_policy_accepted_at' => now(),
            ]);

            $this->updateResellerCounts($reseller);

            return $user;
        });
    }

    /**
     * Delete reseller (with safety checks)
     */
    public function deleteReseller(User $reseller): bool
    {
        if (!$reseller->isReseller()) {
            throw ValidationException::withMessages([
                'user' => 'User is not a reseller.'
            ]);
        }

        return DB::transaction(function () use ($reseller) {
            // Remove reseller assignment from managed users
            $reseller->managedUsers()->update(['reseller_id' => null]);
            
            // Transfer licenses to admin or mark as orphaned
            $adminUser = User::where('role', Role::ADMIN)->first();
            if ($adminUser) {
                $reseller->ownedLicenses()->update(['owner_id' => $adminUser->id]);
            }

            // Soft delete the reseller
            return $reseller->delete();
        });
    }

    /**
     * Get reseller statistics
     */
    public function getResellerStatistics(): array
    {
        $resellers = User::where('role', Role::RESELLER)->get();
        
        return [
            'total_resellers' => $resellers->count(),
            'active_resellers' => $resellers->where('deleted_at', null)->count(),
            'total_managed_users' => $resellers->sum('current_users_count'),
            'total_managed_licenses' => $resellers->sum('current_licenses_count'),
            'resellers_near_user_limit' => $resellers->filter(fn($r) => $r->isUserQuotaNearLimit())->count(),
            'resellers_near_license_limit' => $resellers->filter(fn($r) => $r->isLicenseQuotaNearLimit())->count(),
            'average_user_quota_usage' => $resellers->filter(fn($r) => $r->max_users_quota > 0)
                ->avg(fn($r) => $r->getUserQuotaUsagePercentage()),
            'average_license_quota_usage' => $resellers->filter(fn($r) => $r->max_licenses_quota > 0)
                ->avg(fn($r) => $r->getLicenseQuotaUsagePercentage()),
        ];
    }
}
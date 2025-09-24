<?php

namespace App\Policies;

use App\Models\License;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LicensePolicy
{
    /**
     * Determine whether the user can view any licenses.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role->value, ['admin', 'developer', 'reseller', 'user']);
    }

    /**
     * Determine whether the user can view the license.
     */
    public function view(User $user, License $license): bool
    {
        // Admins and developers can view all licenses
        if (in_array($user->role->value, ['admin', 'developer'])) {
            return true;
        }

        // Resellers can view licenses they own or licenses owned by their users
        if ($user->role->value === 'reseller') {
            return $license->owner_id === $user->id || 
                   $license->owner->reseller_id === $user->id;
        }

        // Users can only view licenses assigned to them
        return $license->user_id === $user->id;
    }

    /**
     * Determine whether the user can create licenses.
     */
    public function create(User $user): bool
    {
        return in_array($user->role->value, ['admin', 'developer', 'reseller']);
    }

    /**
     * Determine whether the user can update the license.
     */
    public function update(User $user, License $license): bool
    {
        // Admins and developers can update all licenses
        if (in_array($user->role->value, ['admin', 'developer'])) {
            return true;
        }

        // Resellers can update licenses they own
        if ($user->role->value === 'reseller') {
            return $license->owner_id === $user->id;
        }

        // Users cannot update licenses
        return false;
    }

    /**
     * Determine whether the user can delete the license.
     */
    public function delete(User $user, License $license): bool
    {
        // Only admins and developers can delete licenses
        if (in_array($user->role->value, ['admin', 'developer'])) {
            return true;
        }

        // Resellers can delete licenses they own (soft delete only)
        if ($user->role->value === 'reseller') {
            return $license->owner_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the license.
     */
    public function restore(User $user, License $license): bool
    {
        return in_array($user->role->value, ['admin', 'developer']);
    }

    /**
     * Determine whether the user can permanently delete the license.
     */
    public function forceDelete(User $user, License $license): bool
    {
        return in_array($user->role->value, ['admin', 'developer']);
    }

    /**
     * Determine whether the user can manage device bindings.
     */
    public function manageDevices(User $user, License $license): bool
    {
        // Admins and developers can manage all device bindings
        if (in_array($user->role->value, ['admin', 'developer'])) {
            return true;
        }

        // Resellers can manage device bindings for licenses they own
        if ($user->role->value === 'reseller') {
            return $license->owner_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can suspend/unsuspend licenses.
     */
    public function suspend(User $user, License $license): bool
    {
        return $this->update($user, $license);
    }

    /**
     * Determine whether the user can expire licenses.
     */
    public function expire(User $user, License $license): bool
    {
        return $this->update($user, $license);
    }
}
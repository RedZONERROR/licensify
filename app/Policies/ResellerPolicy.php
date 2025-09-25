<?php

namespace App\Policies;

use App\Models\User;

class ResellerPolicy
{
    /**
     * Determine whether the user can view any resellers.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the reseller.
     */
    public function view(User $user, User $reseller): bool
    {
        // Admin can view any reseller
        if ($user->isAdmin()) {
            return true;
        }

        // Reseller can view their own profile
        if ($user->isReseller() && $user->id === $reseller->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create resellers.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the reseller.
     */
    public function update(User $user, User $reseller): bool
    {
        // Admin can update any reseller
        if ($user->isAdmin()) {
            return true;
        }

        // Reseller can update their own profile (limited fields)
        if ($user->isReseller() && $user->id === $reseller->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the reseller.
     */
    public function delete(User $user, User $reseller): bool
    {
        return $user->isAdmin() && $reseller->isReseller();
    }

    /**
     * Determine whether the user can restore the reseller.
     */
    public function restore(User $user, User $reseller): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the reseller.
     */
    public function forceDelete(User $user, User $reseller): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can manage users for the reseller.
     */
    public function manageUsers(User $user, User $reseller): bool
    {
        // Admin can manage users for any reseller
        if ($user->isAdmin()) {
            return true;
        }

        // Reseller can manage their own users
        if ($user->isReseller() && $user->id === $reseller->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can manage licenses for the reseller.
     */
    public function manageLicenses(User $user, User $reseller): bool
    {
        // Admin can manage licenses for any reseller
        if ($user->isAdmin()) {
            return true;
        }

        // Reseller can manage their own licenses
        if ($user->isReseller() && $user->id === $reseller->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view reseller statistics.
     */
    public function viewStatistics(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can assign users to reseller.
     */
    public function assignUsers(User $user, User $reseller): bool
    {
        return $user->isAdmin() && $reseller->isReseller();
    }

    /**
     * Determine whether the user can update quotas for the reseller.
     */
    public function updateQuotas(User $user, User $reseller): bool
    {
        return $user->isAdmin() && $reseller->isReseller();
    }
}

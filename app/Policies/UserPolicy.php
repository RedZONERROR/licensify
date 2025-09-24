<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_users') || $user->hasPermission('manage_assigned_users');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Users can always view their own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Admins can view all users
        if ($user->hasPermission('manage_users')) {
            return true;
        }

        // Resellers can view their assigned users
        if ($user->hasPermission('manage_assigned_users')) {
            return $model->reseller_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('manage_users') || $user->hasPermission('manage_assigned_users');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Users can always update their own profile (with restrictions)
        if ($user->id === $model->id) {
            return true;
        }

        // Admins can update all users
        if ($user->hasPermission('manage_users')) {
            return true;
        }

        // Resellers can update their assigned users (but not change roles)
        if ($user->hasPermission('manage_assigned_users')) {
            return $model->reseller_id === $user->id && $user->canManageUser($model);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Users cannot delete themselves
        if ($user->id === $model->id) {
            return false;
        }

        // Admins can delete users (except other admins unless they're super admin)
        if ($user->hasPermission('manage_users')) {
            return $user->canManageUser($model);
        }

        // Resellers can delete their assigned users
        if ($user->hasPermission('manage_assigned_users')) {
            return $model->reseller_id === $user->id && $user->canManageUser($model);
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $this->delete($user, $model);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        // Only admins can permanently delete users
        return $user->hasRole(Role::ADMIN) && $user->canManageUser($model);
    }

    /**
     * Determine whether the user can change roles.
     */
    public function changeRole(User $user, User $model, Role $newRole): bool
    {
        // Users cannot change their own role
        if ($user->id === $model->id) {
            return false;
        }

        // Only admins can change roles
        if (!$user->hasPermission('manage_roles')) {
            return false;
        }

        // Check if user can manage both current and new roles
        return $user->canManageUser($model) && in_array($newRole, $user->role->canManageRoles());
    }

    /**
     * Determine whether the user can assign users to resellers.
     */
    public function assignToReseller(User $user, User $model, User $reseller): bool
    {
        // Only admins and the reseller themselves can assign users
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }

        if ($user->hasRole(Role::RESELLER) && $user->id === $reseller->id) {
            return $user->canManageUser($model);
        }

        return false;
    }

    /**
     * Determine whether the user can manage 2FA for another user.
     */
    public function manage2FA(User $user, User $model): bool
    {
        // Users can manage their own 2FA
        if ($user->id === $model->id) {
            return true;
        }

        // Only admins can manage 2FA for other users
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Determine whether the user can view audit logs for another user.
     */
    public function viewAuditLogs(User $user, User $model): bool
    {
        // Users can view their own audit logs
        if ($user->id === $model->id) {
            return true;
        }

        // Admins and developers can view audit logs
        return $user->hasPermission('view_audit_logs');
    }

    /**
     * Determine whether the user can impersonate another user.
     */
    public function impersonate(User $user, User $model): bool
    {
        // Cannot impersonate yourself
        if ($user->id === $model->id) {
            return false;
        }

        // Only admins can impersonate, and only lower-level users
        return $user->hasRole(Role::ADMIN) && $user->canManageUser($model);
    }

    /**
     * Determine whether the user can export user data (GDPR).
     */
    public function exportData(User $user, User $model): bool
    {
        // Users can export their own data
        if ($user->id === $model->id) {
            return true;
        }

        // Admins can export any user's data
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Determine whether the user can erase user data (GDPR).
     */
    public function eraseData(User $user, User $model): bool
    {
        // Users can request erasure of their own data
        if ($user->id === $model->id) {
            return true;
        }

        // Only admins can erase other users' data
        return $user->hasRole(Role::ADMIN);
    }
}
<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Backup;
use App\Models\User;

class BackupPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_backups');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Backup $backup): bool
    {
        return $user->hasPermission('manage_backups');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('manage_backups');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Backup $backup): bool
    {
        // Only admins can update backup metadata
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Backup $backup): bool
    {
        return $user->hasPermission('manage_backups');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Backup $backup): bool
    {
        // Only admins and developers can restore backups
        return $user->hasPermission('manage_backups');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Backup $backup): bool
    {
        // Only admins can permanently delete backups
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Determine whether the user can download the backup.
     */
    public function download(User $user, Backup $backup): bool
    {
        return $user->hasPermission('manage_backups');
    }

    /**
     * Determine whether the user can trigger a manual backup.
     */
    public function trigger(User $user): bool
    {
        return $user->hasPermission('manage_backups');
    }

    /**
     * Determine whether the user can restore from a backup.
     */
    public function restoreFromBackup(User $user, Backup $backup): bool
    {
        // Restore operations require 2FA and admin/developer role
        if (!$user->hasPermission('manage_backups')) {
            return false;
        }

        // Must have 2FA enabled for restore operations
        if ($user->requires2FA() && !$user->hasTwoFactorEnabled()) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can configure backup settings.
     */
    public function configureSettings(User $user): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Determine whether the user can view backup logs.
     */
    public function viewLogs(User $user): bool
    {
        return $user->hasPermission('manage_backups') || $user->hasPermission('view_audit_logs');
    }

    /**
     * Determine whether the user can test backup integrity.
     */
    public function testIntegrity(User $user, Backup $backup): bool
    {
        return $user->hasPermission('manage_backups');
    }

    /**
     * Determine whether the user can manage offsite storage.
     */
    public function manageOffsiteStorage(User $user): bool
    {
        return $user->hasRole(Role::ADMIN);
    }
}
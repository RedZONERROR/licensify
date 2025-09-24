<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_audit_logs');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AuditLog $auditLog): bool
    {
        // Users can view their own audit logs
        if ($auditLog->user_id === $user->id) {
            return true;
        }

        // Admins and developers can view all audit logs
        return $user->hasPermission('view_audit_logs');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Audit logs are created automatically by the system
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AuditLog $auditLog): bool
    {
        // Audit logs should never be updated
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AuditLog $auditLog): bool
    {
        // Only admins can delete audit logs (for cleanup purposes)
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AuditLog $auditLog): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AuditLog $auditLog): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Determine whether the user can export audit logs.
     */
    public function export(User $user): bool
    {
        return $user->hasPermission('view_audit_logs');
    }

    /**
     * Determine whether the user can filter audit logs by user.
     */
    public function filterByUser(User $user, User $targetUser): bool
    {
        // Users can filter their own logs
        if ($user->id === $targetUser->id) {
            return true;
        }

        // Admins and developers can filter by any user
        return $user->hasPermission('view_audit_logs');
    }

    /**
     * Determine whether the user can view system-level audit logs.
     */
    public function viewSystemLogs(User $user): bool
    {
        return $user->hasPermissionLevel(Role::DEVELOPER);
    }

    /**
     * Determine whether the user can purge old audit logs.
     */
    public function purge(User $user): bool
    {
        // Only admins can purge audit logs
        return $user->hasRole(Role::ADMIN);
    }
}
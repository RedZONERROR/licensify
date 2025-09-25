<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => \App\Policies\UserPolicy::class,
        \App\Models\License::class => \App\Policies\LicensePolicy::class,
        \App\Models\ChatMessage::class => \App\Policies\ChatMessagePolicy::class,
        \App\Models\Backup::class => \App\Policies\BackupPolicy::class,
        \App\Models\Setting::class => \App\Policies\SettingPolicy::class,
        \App\Models\AuditLog::class => \App\Policies\AuditLogPolicy::class,
        'App\Models\User' => \App\Policies\ResellerPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerGates();
    }

    /**
     * Register authorization gates
     */
    protected function registerGates(): void
    {
        // Role-based gates
        Gate::define('admin-access', function (User $user) {
            return $user->hasRole(Role::ADMIN);
        });

        Gate::define('developer-access', function (User $user) {
            return $user->hasRole(Role::DEVELOPER) || $user->hasRole(Role::ADMIN);
        });

        Gate::define('reseller-access', function (User $user) {
            return $user->hasPermissionLevel(Role::RESELLER);
        });

        // Permission-based gates
        Gate::define('manage-users', function (User $user) {
            return $user->hasPermission('manage_users');
        });

        Gate::define('manage-roles', function (User $user) {
            return $user->hasPermission('manage_roles');
        });

        Gate::define('manage-licenses', function (User $user) {
            return $user->hasPermission('manage_licenses') || $user->hasPermission('manage_assigned_licenses');
        });

        Gate::define('manage-settings', function (User $user) {
            return $user->hasPermission('manage_settings');
        });

        Gate::define('manage-backups', function (User $user) {
            return $user->hasPermission('manage_backups');
        });

        Gate::define('view-audit-logs', function (User $user) {
            return $user->hasPermission('view_audit_logs');
        });

        Gate::define('manage-payments', function (User $user) {
            return $user->hasPermission('manage_payments');
        });

        Gate::define('manage-api-keys', function (User $user) {
            return $user->hasPermission('manage_api_keys');
        });

        Gate::define('system-administration', function (User $user) {
            return $user->hasPermission('system_administration');
        });

        Gate::define('system-monitoring', function (User $user) {
            return $user->hasPermission('system_monitoring');
        });

        Gate::define('database-operations', function (User $user) {
            return $user->hasPermission('database_operations');
        });

        Gate::define('log-viewing', function (User $user) {
            return $user->hasPermission('log_viewing');
        });

        Gate::define('chat-access', function (User $user) {
            return $user->hasPermission('chat_access');
        });

        Gate::define('view-dashboard', function (User $user) {
            return $user->hasPermission('view_dashboard') || $user->hasPermissionLevel(Role::RESELLER);
        });

        // 2FA requirement gates
        Gate::define('requires-2fa', function (User $user) {
            return $user->requires2FA();
        });

        Gate::define('sensitive-operation', function (User $user) {
            return $user->requires2FA() ? $user->hasTwoFactorEnabled() : true;
        });

        // Super admin gate (for critical operations)
        Gate::define('super-admin', function (User $user) {
            return $user->hasRole(Role::ADMIN);
        });

        // Developer operations gate
        Gate::define('developer-operations', function (User $user) {
            return $user->hasRole(Role::DEVELOPER) || $user->hasRole(Role::ADMIN);
        });

        // Reseller scope gates
        Gate::define('manage-reseller-users', function (User $user, ?User $targetUser = null) {
            if (!$user->hasPermission('manage_assigned_users')) {
                return false;
            }

            if ($targetUser) {
                return $user->canManageUser($targetUser) && 
                       ($targetUser->reseller_id === $user->id || $user->hasRole(Role::ADMIN));
            }

            return true;
        });

        Gate::define('manage-reseller-licenses', function (User $user, $license = null) {
            if (!$user->hasPermission('manage_assigned_licenses') && !$user->hasPermission('manage_licenses')) {
                return false;
            }

            if ($license && $user->hasRole(Role::RESELLER)) {
                return $license->owner_id === $user->id;
            }

            return true;
        });

        // IP allowlist gate
        Gate::define('ip-restricted-access', function (User $user) {
            if (!$user->hasPermissionLevel(Role::DEVELOPER)) {
                return true; // No IP restriction for lower roles
            }

            // TODO: Implement IP allowlist checking
            // For now, allow all developer/admin access
            return true;
        });
    }
}
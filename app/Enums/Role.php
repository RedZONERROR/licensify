<?php

namespace App\Enums;

enum Role: string
{
    case ADMIN = 'admin';
    case DEVELOPER = 'developer';
    case RESELLER = 'reseller';
    case USER = 'user';

    /**
     * Get all role values
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get role hierarchy level (higher number = more permissions)
     */
    public function level(): int
    {
        return match($this) {
            self::ADMIN => 4,
            self::DEVELOPER => 3,
            self::RESELLER => 2,
            self::USER => 1,
        };
    }

    /**
     * Check if this role has higher or equal permissions than another role
     */
    public function hasPermissionLevel(Role $role): bool
    {
        return $this->level() >= $role->level();
    }

    /**
     * Get roles that this role can manage
     */
    public function canManageRoles(): array
    {
        return match($this) {
            self::ADMIN => [self::DEVELOPER, self::RESELLER, self::USER],
            self::DEVELOPER => [self::RESELLER, self::USER],
            self::RESELLER => [self::USER],
            self::USER => [],
        };
    }

    /**
     * Check if this role requires 2FA for sensitive operations
     */
    public function requires2FA(): bool
    {
        return in_array($this, [self::ADMIN, self::DEVELOPER]);
    }

    /**
     * Get role display name
     */
    public function label(): string
    {
        return match($this) {
            self::ADMIN => 'Administrator',
            self::DEVELOPER => 'Developer',
            self::RESELLER => 'Reseller',
            self::USER => 'User',
        };
    }

    /**
     * Get role description
     */
    public function description(): string
    {
        return match($this) {
            self::ADMIN => 'Full system access and user management',
            self::DEVELOPER => 'Operational tools and system monitoring',
            self::RESELLER => 'License and user management within scope',
            self::USER => 'Basic access to assigned licenses and profile',
        };
    }

    /**
     * Get role permissions
     */
    public function permissions(): array
    {
        return match($this) {
            self::ADMIN => [
                'manage_users',
                'manage_roles',
                'manage_licenses',
                'manage_settings',
                'manage_backups',
                'view_audit_logs',
                'manage_payments',
                'manage_api_keys',
                'system_administration',
                'manage_assigned_users',
                'manage_assigned_licenses',
                'chat_access',
            ],
            self::DEVELOPER => [
                'manage_backups',
                'view_audit_logs',
                'manage_api_keys',
                'system_monitoring',
                'database_operations',
                'log_viewing',
                'chat_access',
            ],
            self::RESELLER => [
                'manage_assigned_users',
                'manage_assigned_licenses',
                'view_dashboard',
                'chat_access',
            ],
            self::USER => [
                'view_profile',
                'manage_profile',
                'view_licenses',
                'chat_access',
            ],
        };
    }
}
<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Setting;
use App\Models\User;

class SettingPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_settings');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Setting $setting): bool
    {
        return $user->hasPermission('manage_settings');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('manage_settings');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Setting $setting): bool
    {
        // Settings updates require 2FA for sensitive settings
        if (!$user->hasPermission('manage_settings')) {
            return false;
        }

        // Sensitive settings require 2FA
        if ($this->isSensitiveSetting($setting) && $user->requires2FA() && !$user->hasTwoFactorEnabled()) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Setting $setting): bool
    {
        // Only admins can delete settings
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Setting $setting): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Setting $setting): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Determine whether the user can test settings (SMTP, S3, etc.).
     */
    public function test(User $user, Setting $setting): bool
    {
        return $user->hasPermission('manage_settings');
    }

    /**
     * Determine whether the user can export settings.
     */
    public function export(User $user): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Determine whether the user can import settings.
     */
    public function import(User $user): bool
    {
        // Import requires 2FA and admin role
        if (!$user->hasRole(Role::ADMIN)) {
            return false;
        }

        if ($user->requires2FA() && !$user->hasTwoFactorEnabled()) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can reset settings to defaults.
     */
    public function reset(User $user): bool
    {
        // Reset requires 2FA and admin role
        if (!$user->hasRole(Role::ADMIN)) {
            return false;
        }

        if ($user->requires2FA() && !$user->hasTwoFactorEnabled()) {
            return false;
        }

        return true;
    }

    /**
     * Check if a setting is considered sensitive.
     */
    protected function isSensitiveSetting(Setting $setting): bool
    {
        $sensitiveKeys = [
            'smtp_password',
            'database_password',
            's3_secret_key',
            'backup_encryption_key',
            'api_secret',
            'oauth_client_secret',
            'telegram_bot_token',
            'payment_api_key',
            'encryption_key',
        ];

        return in_array($setting->key, $sensitiveKeys) || 
               str_contains(strtolower($setting->key), 'password') ||
               str_contains(strtolower($setting->key), 'secret') ||
               str_contains(strtolower($setting->key), 'key');
    }
}
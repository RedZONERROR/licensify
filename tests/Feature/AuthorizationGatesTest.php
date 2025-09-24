<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationGatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_access_gate()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        
        $this->assertTrue(Gate::forUser($admin)->allows('admin-access'));
        $this->assertFalse(Gate::forUser($developer)->allows('admin-access'));
    }

    public function test_developer_access_gate()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        
        $this->assertTrue(Gate::forUser($admin)->allows('developer-access'));
        $this->assertTrue(Gate::forUser($developer)->allows('developer-access'));
        $this->assertFalse(Gate::forUser($reseller)->allows('developer-access'));
    }

    public function test_reseller_access_gate()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $user = User::factory()->create(['role' => Role::USER]);
        
        $this->assertTrue(Gate::forUser($admin)->allows('reseller-access'));
        $this->assertTrue(Gate::forUser($developer)->allows('reseller-access'));
        $this->assertTrue(Gate::forUser($reseller)->allows('reseller-access'));
        $this->assertFalse(Gate::forUser($user)->allows('reseller-access'));
    }

    public function test_permission_based_gates()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $user = User::factory()->create(['role' => Role::USER]);

        // manage-users gate
        $this->assertTrue(Gate::forUser($admin)->allows('manage-users'));
        $this->assertFalse(Gate::forUser($developer)->allows('manage-users'));
        $this->assertFalse(Gate::forUser($reseller)->allows('manage-users'));
        $this->assertFalse(Gate::forUser($user)->allows('manage-users'));

        // manage-backups gate
        $this->assertTrue(Gate::forUser($admin)->allows('manage-backups'));
        $this->assertTrue(Gate::forUser($developer)->allows('manage-backups'));
        $this->assertFalse(Gate::forUser($reseller)->allows('manage-backups'));
        $this->assertFalse(Gate::forUser($user)->allows('manage-backups'));

        // chat-access gate
        $this->assertTrue(Gate::forUser($admin)->allows('chat-access'));
        $this->assertTrue(Gate::forUser($developer)->allows('chat-access'));
        $this->assertTrue(Gate::forUser($reseller)->allows('chat-access'));
        $this->assertTrue(Gate::forUser($user)->allows('chat-access'));
    }

    public function test_requires_2fa_gate()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $user = User::factory()->create(['role' => Role::USER]);

        $this->assertTrue(Gate::forUser($admin)->allows('requires-2fa'));
        $this->assertTrue(Gate::forUser($developer)->allows('requires-2fa'));
        $this->assertFalse(Gate::forUser($reseller)->allows('requires-2fa'));
        $this->assertFalse(Gate::forUser($user)->allows('requires-2fa'));
    }

    public function test_sensitive_operation_gate()
    {
        // Admin without 2FA enabled
        $adminWithout2FA = User::factory()->create([
            'role' => Role::ADMIN,
            'two_factor_confirmed_at' => null
        ]);

        // Admin with 2FA enabled
        $adminWith2FA = User::factory()->create([
            'role' => Role::ADMIN,
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now()
        ]);

        // Reseller (doesn't require 2FA)
        $reseller = User::factory()->create(['role' => Role::RESELLER]);

        $this->assertFalse(Gate::forUser($adminWithout2FA)->allows('sensitive-operation'));
        $this->assertTrue(Gate::forUser($adminWith2FA)->allows('sensitive-operation'));
        $this->assertTrue(Gate::forUser($reseller)->allows('sensitive-operation'));
    }

    public function test_super_admin_gate()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        
        $this->assertTrue(Gate::forUser($admin)->allows('super-admin'));
        $this->assertFalse(Gate::forUser($developer)->allows('super-admin'));
    }

    public function test_developer_operations_gate()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        
        $this->assertTrue(Gate::forUser($admin)->allows('developer-operations'));
        $this->assertTrue(Gate::forUser($developer)->allows('developer-operations'));
        $this->assertFalse(Gate::forUser($reseller)->allows('developer-operations'));
    }

    public function test_manage_reseller_users_gate()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $user = User::factory()->create([
            'role' => Role::USER,
            'reseller_id' => $reseller->id
        ]);
        $otherUser = User::factory()->create(['role' => Role::USER]);

        // Admin can manage any user
        $this->assertTrue(Gate::forUser($admin)->allows('manage-reseller-users'));
        $this->assertTrue(Gate::forUser($admin)->allows('manage-reseller-users', $user));

        // Reseller can manage their assigned users
        $this->assertTrue(Gate::forUser($reseller)->allows('manage-reseller-users'));
        $this->assertTrue(Gate::forUser($reseller)->allows('manage-reseller-users', $user));
        $this->assertFalse(Gate::forUser($reseller)->allows('manage-reseller-users', $otherUser));

        // Regular user cannot manage users
        $this->assertFalse(Gate::forUser($user)->allows('manage-reseller-users'));
    }

    public function test_ip_restricted_access_gate()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        
        // Currently allows all access (IP checking not implemented)
        $this->assertTrue(Gate::forUser($admin)->allows('ip-restricted-access'));
        $this->assertTrue(Gate::forUser($developer)->allows('ip-restricted-access'));
        $this->assertTrue(Gate::forUser($reseller)->allows('ip-restricted-access'));
    }
}
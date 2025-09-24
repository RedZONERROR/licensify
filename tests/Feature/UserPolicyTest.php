<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_any_users()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $user = User::factory()->create(['role' => Role::USER]);

        $this->assertTrue($admin->can('viewAny', User::class));
        $this->assertFalse($developer->can('viewAny', User::class));
        $this->assertTrue($reseller->can('viewAny', User::class));
        $this->assertFalse($user->can('viewAny', User::class));
    }

    public function test_view_user()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $user = User::factory()->create([
            'role' => Role::USER,
            'reseller_id' => $reseller->id
        ]);
        $otherUser = User::factory()->create(['role' => Role::USER]);

        // Users can view their own profile
        $this->assertTrue($user->can('view', $user));

        // Admin can view all users
        $this->assertTrue($admin->can('view', $user));
        $this->assertTrue($admin->can('view', $otherUser));

        // Reseller can view their assigned users
        $this->assertTrue($reseller->can('view', $user));
        $this->assertFalse($reseller->can('view', $otherUser));

        // Users cannot view other users
        $this->assertFalse($user->can('view', $otherUser));
    }

    public function test_create_user()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $user = User::factory()->create(['role' => Role::USER]);

        $this->assertTrue($admin->can('create', User::class));
        $this->assertFalse($developer->can('create', User::class));
        $this->assertTrue($reseller->can('create', User::class));
        $this->assertFalse($user->can('create', User::class));
    }

    public function test_update_user()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $user = User::factory()->create([
            'role' => Role::USER,
            'reseller_id' => $reseller->id
        ]);
        $otherUser = User::factory()->create(['role' => Role::USER]);

        // Users can update their own profile
        $this->assertTrue($user->can('update', $user));

        // Admin can update all users
        $this->assertTrue($admin->can('update', $user));
        $this->assertTrue($admin->can('update', $otherUser));

        // Reseller can update their assigned users
        $this->assertTrue($reseller->can('update', $user));
        $this->assertFalse($reseller->can('update', $otherUser));

        // Users cannot update other users
        $this->assertFalse($user->can('update', $otherUser));
    }

    public function test_delete_user()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $user = User::factory()->create([
            'role' => Role::USER,
            'reseller_id' => $reseller->id
        ]);
        $otherUser = User::factory()->create(['role' => Role::USER]);

        // Users cannot delete themselves
        $this->assertFalse($user->can('delete', $user));

        // Admin can delete users they can manage
        $this->assertTrue($admin->can('delete', $user));
        $this->assertTrue($admin->can('delete', $reseller));

        // Reseller can delete their assigned users
        $this->assertTrue($reseller->can('delete', $user));
        $this->assertFalse($reseller->can('delete', $otherUser));

        // Users cannot delete other users
        $this->assertFalse($user->can('delete', $otherUser));
    }

    public function test_force_delete_user()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $user = User::factory()->create(['role' => Role::USER]);

        // Only admins can force delete
        $this->assertTrue($admin->can('forceDelete', $user));
        $this->assertFalse($developer->can('forceDelete', $user));
        $this->assertFalse($user->can('forceDelete', $user));
    }

    public function test_change_role()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $user = User::factory()->create(['role' => Role::USER]);

        // Users cannot change their own role
        $this->assertFalse($user->can('changeRole', [$user, Role::RESELLER]));

        // Only users with manage_roles permission can change roles
        $this->assertTrue($admin->can('changeRole', [$user, Role::RESELLER]));
        $this->assertFalse($developer->can('changeRole', [$user, Role::RESELLER]));

        // Admin cannot promote user to admin (not in canManageRoles)
        $this->assertFalse($admin->can('changeRole', [$user, Role::ADMIN]));
    }

    public function test_assign_to_reseller()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $user = User::factory()->create(['role' => Role::USER]);

        // Admin can assign users to resellers
        $this->assertTrue($admin->can('assignToReseller', [$user, $reseller]));

        // Reseller can assign users to themselves
        $this->assertTrue($reseller->can('assignToReseller', [$user, $reseller]));

        // User cannot assign users
        $this->assertFalse($user->can('assignToReseller', [$user, $reseller]));
    }

    public function test_manage_2fa()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $user = User::factory()->create(['role' => Role::USER]);
        $otherUser = User::factory()->create(['role' => Role::USER]);

        // Users can manage their own 2FA
        $this->assertTrue($user->can('manage2FA', $user));

        // Admin can manage 2FA for other users
        $this->assertTrue($admin->can('manage2FA', $user));

        // Users cannot manage 2FA for other users
        $this->assertFalse($user->can('manage2FA', $otherUser));
    }

    public function test_view_audit_logs()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $user = User::factory()->create(['role' => Role::USER]);
        $otherUser = User::factory()->create(['role' => Role::USER]);

        // Users can view their own audit logs
        $this->assertTrue($user->can('viewAuditLogs', $user));

        // Admin and developer can view audit logs
        $this->assertTrue($admin->can('viewAuditLogs', $user));
        $this->assertTrue($developer->can('viewAuditLogs', $user));

        // Users cannot view other users' audit logs
        $this->assertFalse($user->can('viewAuditLogs', $otherUser));
    }

    public function test_impersonate()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $user = User::factory()->create(['role' => Role::USER]);

        // Cannot impersonate yourself
        $this->assertFalse($admin->can('impersonate', $admin));

        // Only admin can impersonate lower-level users
        $this->assertTrue($admin->can('impersonate', $user));
        $this->assertTrue($admin->can('impersonate', $developer));
        $this->assertFalse($developer->can('impersonate', $user));
        $this->assertFalse($user->can('impersonate', $admin));
    }

    public function test_export_data()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $user = User::factory()->create(['role' => Role::USER]);
        $otherUser = User::factory()->create(['role' => Role::USER]);

        // Users can export their own data
        $this->assertTrue($user->can('exportData', $user));

        // Admin can export any user's data
        $this->assertTrue($admin->can('exportData', $user));

        // Users cannot export other users' data
        $this->assertFalse($user->can('exportData', $otherUser));
    }

    public function test_erase_data()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $user = User::factory()->create(['role' => Role::USER]);
        $otherUser = User::factory()->create(['role' => Role::USER]);

        // Users can request erasure of their own data
        $this->assertTrue($user->can('eraseData', $user));

        // Admin can erase any user's data
        $this->assertTrue($admin->can('eraseData', $user));

        // Users cannot erase other users' data
        $this->assertFalse($user->can('eraseData', $otherUser));
    }
}
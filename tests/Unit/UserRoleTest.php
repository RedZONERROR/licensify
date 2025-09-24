<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_role_with_enum()
    {
        $user = User::factory()->create(['role' => Role::ADMIN]);
        
        $this->assertTrue($user->hasRole(Role::ADMIN));
        $this->assertFalse($user->hasRole(Role::DEVELOPER));
    }

    public function test_user_has_role_with_string()
    {
        $user = User::factory()->create(['role' => Role::ADMIN]);
        
        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('developer'));
    }

    public function test_user_role_helper_methods()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $user = User::factory()->create(['role' => Role::USER]);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isDeveloper());
        $this->assertFalse($admin->isReseller());
        $this->assertFalse($admin->isUser());

        $this->assertFalse($developer->isAdmin());
        $this->assertTrue($developer->isDeveloper());
        $this->assertFalse($developer->isReseller());
        $this->assertFalse($developer->isUser());

        $this->assertFalse($reseller->isAdmin());
        $this->assertFalse($reseller->isDeveloper());
        $this->assertTrue($reseller->isReseller());
        $this->assertFalse($reseller->isUser());

        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isDeveloper());
        $this->assertFalse($user->isReseller());
        $this->assertTrue($user->isUser());
    }

    public function test_user_requires_2fa()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $user = User::factory()->create(['role' => Role::USER]);

        $this->assertTrue($admin->requires2FA());
        $this->assertTrue($developer->requires2FA());
        $this->assertFalse($reseller->requires2FA());
        $this->assertFalse($user->requires2FA());
    }

    public function test_user_has_permission_level()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $user = User::factory()->create(['role' => Role::USER]);

        // Admin can access all levels
        $this->assertTrue($admin->hasPermissionLevel(Role::ADMIN));
        $this->assertTrue($admin->hasPermissionLevel(Role::DEVELOPER));
        $this->assertTrue($admin->hasPermissionLevel(Role::RESELLER));
        $this->assertTrue($admin->hasPermissionLevel(Role::USER));

        // Developer cannot access admin level
        $this->assertFalse($developer->hasPermissionLevel(Role::ADMIN));
        $this->assertTrue($developer->hasPermissionLevel(Role::DEVELOPER));
        $this->assertTrue($developer->hasPermissionLevel(Role::RESELLER));
        $this->assertTrue($developer->hasPermissionLevel(Role::USER));

        // Reseller cannot access admin or developer levels
        $this->assertFalse($reseller->hasPermissionLevel(Role::ADMIN));
        $this->assertFalse($reseller->hasPermissionLevel(Role::DEVELOPER));
        $this->assertTrue($reseller->hasPermissionLevel(Role::RESELLER));
        $this->assertTrue($reseller->hasPermissionLevel(Role::USER));

        // User can only access user level
        $this->assertFalse($user->hasPermissionLevel(Role::ADMIN));
        $this->assertFalse($user->hasPermissionLevel(Role::DEVELOPER));
        $this->assertFalse($user->hasPermissionLevel(Role::RESELLER));
        $this->assertTrue($user->hasPermissionLevel(Role::USER));
    }

    public function test_user_can_manage_user()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $user = User::factory()->create(['role' => Role::USER]);

        // Admin can manage all except other admins
        $this->assertFalse($admin->canManageUser($admin)); // Cannot manage same level
        $this->assertTrue($admin->canManageUser($developer));
        $this->assertTrue($admin->canManageUser($reseller));
        $this->assertTrue($admin->canManageUser($user));

        // Developer can manage reseller and user
        $this->assertFalse($developer->canManageUser($admin));
        $this->assertFalse($developer->canManageUser($developer));
        $this->assertTrue($developer->canManageUser($reseller));
        $this->assertTrue($developer->canManageUser($user));

        // Reseller can only manage users
        $this->assertFalse($reseller->canManageUser($admin));
        $this->assertFalse($reseller->canManageUser($developer));
        $this->assertFalse($reseller->canManageUser($reseller));
        $this->assertTrue($reseller->canManageUser($user));

        // User cannot manage anyone
        $this->assertFalse($user->canManageUser($admin));
        $this->assertFalse($user->canManageUser($developer));
        $this->assertFalse($user->canManageUser($reseller));
        $this->assertFalse($user->canManageUser($user));
    }

    public function test_user_get_permissions()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $user = User::factory()->create(['role' => Role::USER]);

        $adminPermissions = $admin->getPermissions();
        $this->assertContains('manage_users', $adminPermissions);
        $this->assertContains('system_administration', $adminPermissions);

        $developerPermissions = $developer->getPermissions();
        $this->assertContains('manage_backups', $developerPermissions);
        $this->assertNotContains('manage_users', $developerPermissions);

        $resellerPermissions = $reseller->getPermissions();
        $this->assertContains('manage_assigned_users', $resellerPermissions);
        $this->assertNotContains('manage_users', $resellerPermissions);

        $userPermissions = $user->getPermissions();
        $this->assertContains('view_profile', $userPermissions);
        $this->assertNotContains('manage_users', $userPermissions);
    }

    public function test_user_has_permission()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $user = User::factory()->create(['role' => Role::USER]);

        // Test admin permissions
        $this->assertTrue($admin->hasPermission('manage_users'));
        $this->assertTrue($admin->hasPermission('system_administration'));
        $this->assertFalse($admin->hasPermission('nonexistent_permission'));

        // Test developer permissions
        $this->assertTrue($developer->hasPermission('manage_backups'));
        $this->assertTrue($developer->hasPermission('system_monitoring'));
        $this->assertFalse($developer->hasPermission('manage_users'));

        // Test reseller permissions
        $this->assertTrue($reseller->hasPermission('manage_assigned_users'));
        $this->assertTrue($reseller->hasPermission('chat_access'));
        $this->assertFalse($reseller->hasPermission('manage_users'));

        // Test user permissions
        $this->assertTrue($user->hasPermission('view_profile'));
        $this->assertTrue($user->hasPermission('chat_access'));
        $this->assertFalse($user->hasPermission('manage_users'));
    }

    public function test_role_casting()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $this->assertInstanceOf(Role::class, $user->role);
        $this->assertEquals(Role::ADMIN, $user->role);
    }
}
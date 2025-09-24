<?php

namespace Tests\Unit;

use App\Enums\Role;
use PHPUnit\Framework\TestCase;

class RoleEnumTest extends TestCase
{
    public function test_role_values_returns_all_role_strings()
    {
        $values = Role::values();
        
        $this->assertCount(4, $values);
        $this->assertContains('admin', $values);
        $this->assertContains('developer', $values);
        $this->assertContains('reseller', $values);
        $this->assertContains('user', $values);
    }

    public function test_role_hierarchy_levels()
    {
        $this->assertEquals(4, Role::ADMIN->level());
        $this->assertEquals(3, Role::DEVELOPER->level());
        $this->assertEquals(2, Role::RESELLER->level());
        $this->assertEquals(1, Role::USER->level());
    }

    public function test_permission_level_comparison()
    {
        $this->assertTrue(Role::ADMIN->hasPermissionLevel(Role::DEVELOPER));
        $this->assertTrue(Role::ADMIN->hasPermissionLevel(Role::RESELLER));
        $this->assertTrue(Role::ADMIN->hasPermissionLevel(Role::USER));
        $this->assertTrue(Role::ADMIN->hasPermissionLevel(Role::ADMIN));

        $this->assertFalse(Role::DEVELOPER->hasPermissionLevel(Role::ADMIN));
        $this->assertTrue(Role::DEVELOPER->hasPermissionLevel(Role::RESELLER));
        $this->assertTrue(Role::DEVELOPER->hasPermissionLevel(Role::USER));

        $this->assertFalse(Role::RESELLER->hasPermissionLevel(Role::ADMIN));
        $this->assertFalse(Role::RESELLER->hasPermissionLevel(Role::DEVELOPER));
        $this->assertTrue(Role::RESELLER->hasPermissionLevel(Role::USER));

        $this->assertFalse(Role::USER->hasPermissionLevel(Role::ADMIN));
        $this->assertFalse(Role::USER->hasPermissionLevel(Role::DEVELOPER));
        $this->assertFalse(Role::USER->hasPermissionLevel(Role::RESELLER));
        $this->assertTrue(Role::USER->hasPermissionLevel(Role::USER));
    }

    public function test_can_manage_roles()
    {
        $adminCanManage = Role::ADMIN->canManageRoles();
        $this->assertContains(Role::DEVELOPER, $adminCanManage);
        $this->assertContains(Role::RESELLER, $adminCanManage);
        $this->assertContains(Role::USER, $adminCanManage);
        $this->assertNotContains(Role::ADMIN, $adminCanManage);

        $developerCanManage = Role::DEVELOPER->canManageRoles();
        $this->assertContains(Role::RESELLER, $developerCanManage);
        $this->assertContains(Role::USER, $developerCanManage);
        $this->assertNotContains(Role::ADMIN, $developerCanManage);
        $this->assertNotContains(Role::DEVELOPER, $developerCanManage);

        $resellerCanManage = Role::RESELLER->canManageRoles();
        $this->assertContains(Role::USER, $resellerCanManage);
        $this->assertNotContains(Role::ADMIN, $resellerCanManage);
        $this->assertNotContains(Role::DEVELOPER, $resellerCanManage);
        $this->assertNotContains(Role::RESELLER, $resellerCanManage);

        $userCanManage = Role::USER->canManageRoles();
        $this->assertEmpty($userCanManage);
    }

    public function test_2fa_requirements()
    {
        $this->assertTrue(Role::ADMIN->requires2FA());
        $this->assertTrue(Role::DEVELOPER->requires2FA());
        $this->assertFalse(Role::RESELLER->requires2FA());
        $this->assertFalse(Role::USER->requires2FA());
    }

    public function test_role_labels()
    {
        $this->assertEquals('Administrator', Role::ADMIN->label());
        $this->assertEquals('Developer', Role::DEVELOPER->label());
        $this->assertEquals('Reseller', Role::RESELLER->label());
        $this->assertEquals('User', Role::USER->label());
    }

    public function test_role_descriptions()
    {
        $this->assertNotEmpty(Role::ADMIN->description());
        $this->assertNotEmpty(Role::DEVELOPER->description());
        $this->assertNotEmpty(Role::RESELLER->description());
        $this->assertNotEmpty(Role::USER->description());
    }

    public function test_role_permissions()
    {
        $adminPermissions = Role::ADMIN->permissions();
        $this->assertContains('manage_users', $adminPermissions);
        $this->assertContains('manage_roles', $adminPermissions);
        $this->assertContains('system_administration', $adminPermissions);

        $developerPermissions = Role::DEVELOPER->permissions();
        $this->assertContains('manage_backups', $developerPermissions);
        $this->assertContains('system_monitoring', $developerPermissions);
        $this->assertNotContains('manage_users', $developerPermissions);

        $resellerPermissions = Role::RESELLER->permissions();
        $this->assertContains('manage_assigned_users', $resellerPermissions);
        $this->assertContains('manage_assigned_licenses', $resellerPermissions);
        $this->assertNotContains('manage_users', $resellerPermissions);

        $userPermissions = Role::USER->permissions();
        $this->assertContains('view_profile', $userPermissions);
        $this->assertContains('manage_profile', $userPermissions);
        $this->assertNotContains('manage_users', $userPermissions);
    }
}
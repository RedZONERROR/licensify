<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_access_role_management()
    {
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $user = User::factory()->create(['role' => Role::USER]);

        $this->actingAs($developer)->get(route('admin.roles.index'))->assertStatus(403);
        $this->actingAs($reseller)->get(route('admin.roles.index'))->assertStatus(403);
        $this->actingAs($user)->get(route('admin.roles.index'))->assertStatus(403);
    }

    public function test_admin_can_view_role_management_index()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        User::factory()->count(5)->create(['role' => Role::USER]);

        $response = $this->actingAs($admin)->get(route('admin.roles.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.roles.index');
        $response->assertViewHas(['users', 'roles', 'roleStats']);
    }

    public function test_admin_can_view_user_role_edit_page()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $user = User::factory()->create(['role' => Role::USER]);

        $response = $this->actingAs($admin)->get(route('admin.roles.show', $user));

        $response->assertStatus(200);
        $response->assertViewIs('admin.roles.show');
        $response->assertViewHas(['user', 'availableRoles']);
    }

    public function test_admin_can_update_user_role()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $user = User::factory()->create(['role' => Role::USER]);

        $response = $this->actingAs($admin)->put(route('admin.roles.update', $user), [
            'role' => Role::RESELLER->value,
            'reason' => 'Promoting to reseller for business expansion'
        ]);

        $response->assertRedirect(route('admin.roles.index'));
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertEquals(Role::RESELLER, $user->role);
    }

    public function test_admin_cannot_promote_user_to_admin()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $user = User::factory()->create(['role' => Role::USER]);

        $response = $this->actingAs($admin)->put(route('admin.roles.update', $user), [
            'role' => Role::ADMIN->value,
            'reason' => 'Attempting to promote to admin'
        ]);

        $response->assertStatus(403);
        
        $user->refresh();
        $this->assertEquals(Role::USER, $user->role);
    }

    public function test_role_update_requires_reason()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $user = User::factory()->create(['role' => Role::USER]);

        $response = $this->actingAs($admin)->put(route('admin.roles.update', $user), [
            'role' => Role::RESELLER->value,
            // Missing reason
        ]);

        $response->assertSessionHasErrors(['reason']);
        
        $user->refresh();
        $this->assertEquals(Role::USER, $user->role);
    }

    public function test_bulk_role_update()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $users = User::factory()->count(3)->create(['role' => Role::USER]);

        $response = $this->actingAs($admin)->post(route('admin.roles.bulk-update'), [
            'user_ids' => $users->pluck('id')->toArray(),
            'role' => Role::RESELLER->value,
            'reason' => 'Bulk promotion to reseller'
        ]);

        $response->assertRedirect(route('admin.roles.index'));
        $response->assertSessionHas('success');

        foreach ($users as $user) {
            $user->refresh();
            $this->assertEquals(Role::RESELLER, $user->role);
        }
    }

    public function test_bulk_update_validates_user_ids()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);

        $response = $this->actingAs($admin)->post(route('admin.roles.bulk-update'), [
            'user_ids' => [999, 1000], // Non-existent user IDs
            'role' => Role::RESELLER->value,
            'reason' => 'Test bulk update'
        ]);

        $response->assertSessionHasErrors(['user_ids.0', 'user_ids.1']);
    }

    public function test_admin_can_view_permissions_matrix()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('admin.roles.permissions'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.roles.permissions');
        $response->assertViewHas(['roles', 'allPermissions', 'permissionMatrix']);
    }

    public function test_admin_can_export_role_assignments()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        User::factory()->count(5)->create(['role' => Role::USER]);

        $response = $this->actingAs($admin)->get(route('admin.roles.export'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=', $response->headers->get('Content-Disposition'));
    }

    public function test_role_change_creates_audit_log()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $user = User::factory()->create(['role' => Role::USER]);

        $this->actingAs($admin)->put(route('admin.roles.update', $user), [
            'role' => Role::RESELLER->value,
            'reason' => 'Promotion to reseller'
        ]);

        // Check that activity log was created
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'causer_type' => User::class,
            'causer_id' => $admin->id,
            'description' => 'Role changed from User to Reseller'
        ]);
    }

    public function test_cannot_change_own_role()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);

        $response = $this->actingAs($admin)->put(route('admin.roles.update', $admin), [
            'role' => Role::DEVELOPER->value,
            'reason' => 'Attempting to change own role'
        ]);

        $response->assertStatus(403);
        
        $admin->refresh();
        $this->assertEquals(Role::ADMIN, $admin->role);
    }

    public function test_role_statistics_are_accurate()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        User::factory()->count(3)->create(['role' => Role::DEVELOPER]);
        User::factory()->count(5)->create(['role' => Role::RESELLER]);
        User::factory()->count(10)->create(['role' => Role::USER]);

        $response = $this->actingAs($admin)->get(route('admin.roles.index'));

        $roleStats = $response->viewData('roleStats');
        
        $this->assertEquals(1, $roleStats['admin']['count']);
        $this->assertEquals(3, $roleStats['developer']['count']);
        $this->assertEquals(5, $roleStats['reseller']['count']);
        $this->assertEquals(10, $roleStats['user']['count']);
    }
}
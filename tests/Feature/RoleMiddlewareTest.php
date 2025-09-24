<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_redirected_to_login()
    {
        $response = $this->get('/test-admin-route');
        
        $response->assertRedirect('/login');
    }

    public function test_admin_can_access_admin_route()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        
        $response = $this->actingAs($admin)->get('/test-admin-route');
        
        $response->assertStatus(200);
        $response->assertSeeText('Admin access granted');
    }

    public function test_non_admin_cannot_access_admin_route()
    {
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $user = User::factory()->create(['role' => Role::USER]);

        $this->actingAs($developer)->get('/test-admin-route')->assertStatus(403);
        $this->actingAs($reseller)->get('/test-admin-route')->assertStatus(403);
        $this->actingAs($user)->get('/test-admin-route')->assertStatus(403);
    }

    public function test_multiple_roles_middleware()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $user = User::factory()->create(['role' => Role::USER]);

        // Both admin and developer should have access
        $this->actingAs($admin)->get('/test-developer-route')->assertStatus(200);
        $this->actingAs($developer)->get('/test-developer-route')->assertStatus(200);

        // Reseller and user should not have access
        $this->actingAs($reseller)->get('/test-developer-route')->assertStatus(403);
        $this->actingAs($user)->get('/test-developer-route')->assertStatus(403);
    }

    public function test_permission_middleware()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $developer = User::factory()->create(['role' => Role::DEVELOPER]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $user = User::factory()->create(['role' => Role::USER]);

        // Only admin has 'manage_users' permission
        $this->actingAs($admin)->get('/test-permission-route')->assertStatus(200);
        
        $this->actingAs($developer)->get('/test-permission-route')->assertStatus(403);
        $this->actingAs($reseller)->get('/test-permission-route')->assertStatus(403);
        $this->actingAs($user)->get('/test-permission-route')->assertStatus(403);
    }

    public function test_role_middleware_error_message()
    {
        $user = User::factory()->create(['role' => Role::USER]);
        
        $response = $this->actingAs($user)->get('/test-admin-route');
        
        $response->assertStatus(403);
        $this->assertStringContainsString('Insufficient permissions', $response->exception->getMessage());
        $this->assertStringContainsString('Required roles: admin', $response->exception->getMessage());
    }

    public function test_permission_middleware_error_message()
    {
        $user = User::factory()->create(['role' => Role::USER]);
        
        $response = $this->actingAs($user)->get('/test-permission-route');
        
        $response->assertStatus(403);
        $this->assertStringContainsString('Insufficient permissions', $response->exception->getMessage());
        $this->assertStringContainsString('Required permissions: manage_users', $response->exception->getMessage());
    }
}
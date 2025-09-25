<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResellerControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $reseller;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create(['role' => Role::ADMIN]);
        $this->reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_users_quota' => 10,
            'max_licenses_quota' => 50,
        ]);
        $this->regularUser = User::factory()->create(['role' => Role::USER]);
    }

    public function test_admin_can_view_resellers_index()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.resellers.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.resellers.index');
        $response->assertViewHas(['resellers', 'statistics']);
    }

    public function test_non_admin_cannot_view_resellers_index()
    {
        $response = $this->actingAs($this->reseller)
            ->get(route('admin.resellers.index'));

        $response->assertStatus(403);
    }

    public function test_admin_can_create_reseller()
    {
        $resellerData = [
            'name' => 'New Reseller',
            'email' => 'newreseller@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'max_users_quota' => 15,
            'max_licenses_quota' => 75,
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.resellers.store'), $resellerData);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'name' => 'New Reseller',
            'email' => 'newreseller@example.com',
            'role' => Role::RESELLER->value,
            'max_users_quota' => 15,
            'max_licenses_quota' => 75,
        ]);
    }

    public function test_admin_can_view_reseller_details()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.resellers.show', $this->reseller));

        $response->assertStatus(200);
        $response->assertViewIs('admin.resellers.show');
    }

    public function test_admin_can_update_reseller()
    {
        $updateData = [
            'name' => 'Updated Reseller Name',
            'email' => $this->reseller->email,
            'max_users_quota' => 20,
            'max_licenses_quota' => 100,
        ];

        $response = $this->actingAs($this->admin)
            ->put(route('admin.resellers.update', $this->reseller), $updateData);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $this->reseller->id,
            'name' => 'Updated Reseller Name',
            'max_users_quota' => 20,
            'max_licenses_quota' => 100,
        ]);
    }

    public function test_admin_can_delete_reseller()
    {
        $response = $this->actingAs($this->admin)
            ->delete(route('admin.resellers.destroy', $this->reseller));

        $response->assertRedirect();
        $this->assertSoftDeleted('users', ['id' => $this->reseller->id]);
    }

    public function test_admin_can_assign_user_to_reseller()
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resellers.assign-user', $this->reseller), [
                'user_id' => $this->regularUser->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'User assigned to reseller successfully.']);
        
        $this->assertEquals($this->reseller->id, $this->regularUser->fresh()->reseller_id);
    }

    public function test_admin_can_remove_user_from_reseller()
    {
        // First assign the user
        $this->regularUser->update(['reseller_id' => $this->reseller->id]);

        $response = $this->actingAs($this->admin)
            ->deleteJson(route('admin.resellers.remove-user', [$this->reseller, $this->regularUser]));

        $response->assertStatus(200);
        $response->assertJson(['message' => 'User removed from reseller successfully.']);
        
        $this->assertNull($this->regularUser->fresh()->reseller_id);
    }

    public function test_admin_can_get_available_users()
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.resellers.available-users'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['users']);
    }

    public function test_admin_can_update_reseller_counts()
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resellers.update-counts', $this->reseller));

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Reseller counts updated successfully.']);
    }

    public function test_admin_can_get_reseller_statistics()
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.resellers.statistics'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'statistics' => [
                'total_resellers',
                'active_resellers',
                'total_managed_users',
                'total_managed_licenses',
            ]
        ]);
    }

    public function test_validation_errors_when_creating_reseller_with_invalid_data()
    {
        $invalidData = [
            'name' => '', // Required
            'email' => 'invalid-email', // Invalid format
            'max_users_quota' => -1, // Cannot be negative
            'max_licenses_quota' => 'not-a-number', // Must be integer
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.resellers.store'), $invalidData);

        $response->assertSessionHasErrors(['name', 'email', 'max_users_quota', 'max_licenses_quota']);
    }

    public function test_cannot_reduce_quota_below_current_usage()
    {
        // Set current usage
        $this->reseller->update(['current_users_count' => 5]);

        $updateData = [
            'name' => $this->reseller->name,
            'email' => $this->reseller->email,
            'max_users_quota' => 3, // Below current usage
        ];

        $response = $this->actingAs($this->admin)
            ->put(route('admin.resellers.update', $this->reseller), $updateData);

        $response->assertSessionHasErrors(['max_users_quota']);
    }

    public function test_json_responses_for_api_requests()
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.resellers.index'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'resellers',
            'statistics'
        ]);
    }

    public function test_reseller_cannot_access_admin_routes()
    {
        $routes = [
            'admin.resellers.index',
            'admin.resellers.create',
            'admin.resellers.statistics',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($this->reseller)
                ->get(route($route));

            $response->assertStatus(403);
        }
    }

    public function test_regular_user_cannot_access_admin_routes()
    {
        $routes = [
            'admin.resellers.index',
            'admin.resellers.create',
            'admin.resellers.statistics',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($this->regularUser)
                ->get(route($route));

            $response->assertStatus(403);
        }
    }
}

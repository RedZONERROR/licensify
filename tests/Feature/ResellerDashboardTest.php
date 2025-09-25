<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResellerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $reseller;
    private User $admin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_users_quota' => 10,
            'max_licenses_quota' => 50,
            'current_users_count' => 3,
            'current_licenses_count' => 15,
        ]);
        
        $this->admin = User::factory()->create(['role' => Role::ADMIN]);
        $this->regularUser = User::factory()->create(['role' => Role::USER]);
    }

    public function test_reseller_can_view_dashboard()
    {
        $response = $this->actingAs($this->reseller)
            ->get(route('reseller.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('reseller.dashboard');
        $response->assertViewHas(['stats', 'recent_users', 'recent_licenses', 'quota_warnings']);
    }

    public function test_non_reseller_cannot_view_dashboard()
    {
        $response = $this->actingAs($this->regularUser)
            ->get(route('reseller.dashboard'));

        $response->assertStatus(403);
    }

    public function test_reseller_can_view_managed_users()
    {
        // Create some managed users
        User::factory()->count(3)->create(['reseller_id' => $this->reseller->id]);

        $response = $this->actingAs($this->reseller)
            ->get(route('reseller.users'));

        $response->assertStatus(200);
        $response->assertViewIs('reseller.users.index');
        $response->assertViewHas('users');
    }

    public function test_reseller_can_create_user_when_quota_allows()
    {
        $this->reseller->update(['current_users_count' => 5]); // Below quota

        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->actingAs($this->reseller)
            ->post(route('reseller.users.store'), $userData);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'role' => Role::USER->value,
            'reseller_id' => $this->reseller->id,
        ]);
    }

    public function test_reseller_cannot_create_user_when_quota_exceeded()
    {
        $this->reseller->update(['current_users_count' => 10]); // At quota limit

        $response = $this->actingAs($this->reseller)
            ->get(route('reseller.users.create'));

        $response->assertRedirect();
        $response->assertSessionHasErrors(['quota']);
    }

    public function test_reseller_can_view_managed_user_details()
    {
        $managedUser = User::factory()->create([
            'role' => Role::USER,
            'reseller_id' => $this->reseller->id,
        ]);

        $response = $this->actingAs($this->reseller)
            ->get(route('reseller.users.show', $managedUser));

        $response->assertStatus(200);
        $response->assertViewIs('reseller.users.show');
        $response->assertViewHas(['user', 'userLicenses']);
    }

    public function test_reseller_cannot_view_unmanaged_user_details()
    {
        $unmanagedUser = User::factory()->create(['role' => Role::USER]);

        $response = $this->actingAs($this->reseller)
            ->get(route('reseller.users.show', $unmanagedUser));

        $response->assertStatus(403);
    }

    public function test_reseller_can_update_managed_user()
    {
        $managedUser = User::factory()->create([
            'role' => Role::USER,
            'reseller_id' => $this->reseller->id,
        ]);

        $updateData = [
            'name' => 'Updated User Name',
            'email' => $managedUser->email,
        ];

        $response = $this->actingAs($this->reseller)
            ->put(route('reseller.users.update', $managedUser), $updateData);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $managedUser->id,
            'name' => 'Updated User Name',
        ]);
    }

    public function test_reseller_cannot_update_unmanaged_user()
    {
        $unmanagedUser = User::factory()->create(['role' => Role::USER]);

        $updateData = [
            'name' => 'Updated User Name',
            'email' => $unmanagedUser->email,
        ];

        $response = $this->actingAs($this->reseller)
            ->put(route('reseller.users.update', $unmanagedUser), $updateData);

        $response->assertStatus(403);
    }

    public function test_reseller_can_remove_managed_user()
    {
        $managedUser = User::factory()->create([
            'role' => Role::USER,
            'reseller_id' => $this->reseller->id,
        ]);

        $response = $this->actingAs($this->reseller)
            ->deleteJson(route('reseller.users.remove', $managedUser));

        $response->assertStatus(200);
        $response->assertJson(['message' => 'User removed successfully.']);
        $this->assertNull($managedUser->fresh()->reseller_id);
    }

    public function test_reseller_can_view_managed_licenses()
    {
        // Create some licenses owned by the reseller
        License::factory()->count(5)->create(['owner_id' => $this->reseller->id]);

        $response = $this->actingAs($this->reseller)
            ->get(route('reseller.licenses'));

        $response->assertStatus(200);
        $response->assertViewIs('reseller.licenses.index');
        $response->assertViewHas('licenses');
    }

    public function test_reseller_can_get_statistics()
    {
        $response = $this->actingAs($this->reseller)
            ->getJson(route('reseller.statistics'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'stats' => [
                'total_users',
                'max_users',
                'total_licenses',
                'max_licenses',
                'user_quota_percentage',
                'license_quota_percentage',
            ],
            'quota_warnings' => [
                'user_near_limit',
                'license_near_limit',
            ]
        ]);
    }

    public function test_reseller_can_get_quota_info()
    {
        $response = $this->actingAs($this->reseller)
            ->getJson(route('reseller.quota-info'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'user_quota' => [
                'current',
                'max',
                'remaining',
                'percentage',
                'near_limit',
                'can_add',
            ],
            'license_quota' => [
                'current',
                'max',
                'remaining',
                'percentage',
                'near_limit',
                'can_add',
            ]
        ]);
    }

    public function test_reseller_can_get_recent_activity()
    {
        // Create some managed users and licenses
        User::factory()->count(3)->create(['reseller_id' => $this->reseller->id]);
        License::factory()->count(3)->create(['owner_id' => $this->reseller->id]);

        $response = $this->actingAs($this->reseller)
            ->getJson(route('reseller.recent-activity'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'recent_users',
            'recent_licenses'
        ]);
    }

    public function test_dashboard_shows_quota_warnings()
    {
        // Set reseller near quota limits
        $this->reseller->update([
            'current_users_count' => 9, // 90% of 10
            'current_licenses_count' => 45, // 90% of 50
        ]);

        $response = $this->actingAs($this->reseller)
            ->get(route('reseller.dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('quota_warnings');
        
        $quotaWarnings = $response->viewData('quota_warnings');
        $this->assertTrue($quotaWarnings['user_near_limit']);
        $this->assertTrue($quotaWarnings['license_near_limit']);
    }

    public function test_validation_errors_when_creating_user_with_invalid_data()
    {
        $invalidData = [
            'name' => '', // Required
            'email' => 'invalid-email', // Invalid format
            'password' => '123', // Too short
        ];

        $response = $this->actingAs($this->reseller)
            ->post(route('reseller.users.store'), $invalidData);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
    }

    public function test_json_responses_for_ajax_requests()
    {
        $response = $this->actingAs($this->reseller)
            ->getJson(route('reseller.dashboard'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'reseller',
            'stats',
            'recent_users',
            'recent_licenses',
            'quota_warnings'
        ]);
    }

    public function test_admin_cannot_access_reseller_dashboard()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('reseller.dashboard'));

        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_access_reseller_dashboard()
    {
        $response = $this->actingAs($this->regularUser)
            ->get(route('reseller.dashboard'));

        $response->assertStatus(403);
    }

    public function test_reseller_dashboard_pagination()
    {
        // Create many managed users to test pagination
        User::factory()->count(20)->create(['reseller_id' => $this->reseller->id]);

        $response = $this->actingAs($this->reseller)
            ->get(route('reseller.users') . '?per_page=10');

        $response->assertStatus(200);
        $response->assertViewHas('users');
        
        $users = $response->viewData('users');
        $this->assertEquals(10, $users->perPage());
    }
}

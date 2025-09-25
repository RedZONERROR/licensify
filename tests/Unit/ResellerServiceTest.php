<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Models\License;
use App\Models\User;
use App\Services\ResellerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ResellerServiceTest extends TestCase
{
    use RefreshDatabase;

    private ResellerService $resellerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resellerService = new ResellerService();
    }

    public function test_can_create_reseller_with_quotas()
    {
        $data = [
            'name' => 'Test Reseller',
            'email' => 'reseller@example.com',
            'password' => 'password123',
            'max_users_quota' => 10,
            'max_licenses_quota' => 50,
        ];

        $reseller = $this->resellerService->createReseller($data);

        $this->assertInstanceOf(User::class, $reseller);
        $this->assertEquals(Role::RESELLER, $reseller->role);
        $this->assertEquals('Test Reseller', $reseller->name);
        $this->assertEquals('reseller@example.com', $reseller->email);
        $this->assertEquals(10, $reseller->max_users_quota);
        $this->assertEquals(50, $reseller->max_licenses_quota);
        $this->assertEquals(0, $reseller->current_users_count);
        $this->assertEquals(0, $reseller->current_licenses_count);
        $this->assertNotNull($reseller->privacy_policy_accepted_at);
    }

    public function test_can_create_reseller_without_quotas()
    {
        $data = [
            'name' => 'Unlimited Reseller',
            'email' => 'unlimited@example.com',
        ];

        $reseller = $this->resellerService->createReseller($data);

        $this->assertNull($reseller->max_users_quota);
        $this->assertNull($reseller->max_licenses_quota);
        $this->assertTrue($reseller->canAddUser());
        $this->assertTrue($reseller->canAddLicense());
    }

    public function test_can_update_reseller_quotas()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_users_quota' => 5,
            'max_licenses_quota' => 25,
            'current_users_count' => 2,
            'current_licenses_count' => 10,
        ]);

        $updateData = [
            'name' => 'Updated Reseller',
            'max_users_quota' => 15,
            'max_licenses_quota' => 75,
        ];

        $updatedReseller = $this->resellerService->updateReseller($reseller, $updateData);

        $this->assertEquals('Updated Reseller', $updatedReseller->name);
        $this->assertEquals(15, $updatedReseller->max_users_quota);
        $this->assertEquals(75, $updatedReseller->max_licenses_quota);
    }

    public function test_cannot_reduce_quota_below_current_usage()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_users_quota' => 10,
            'current_users_count' => 5,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot set user quota below current usage (5).');

        $this->resellerService->updateReseller($reseller, [
            'max_users_quota' => 3,
        ]);
    }

    public function test_can_assign_user_to_reseller()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_users_quota' => 10,
            'current_users_count' => 0,
        ]);

        $user = User::factory()->create(['role' => Role::USER]);

        $result = $this->resellerService->assignUserToReseller($reseller, $user);

        $this->assertTrue($result);
        $this->assertEquals($reseller->id, $user->fresh()->reseller_id);
        $this->assertEquals(1, $reseller->fresh()->current_users_count);
    }

    public function test_cannot_assign_user_when_quota_exceeded()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_users_quota' => 1,
            'current_users_count' => 1,
        ]);

        $user = User::factory()->create(['role' => Role::USER]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Reseller has reached maximum user quota.');

        $this->resellerService->assignUserToReseller($reseller, $user);
    }

    public function test_cannot_assign_non_user_to_reseller()
    {
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        $admin = User::factory()->create(['role' => Role::ADMIN]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Can only assign regular users to resellers.');

        $this->resellerService->assignUserToReseller($reseller, $admin);
    }

    public function test_can_remove_user_from_reseller()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'current_users_count' => 1,
        ]);

        $user = User::factory()->create([
            'role' => Role::USER,
            'reseller_id' => $reseller->id,
        ]);

        $result = $this->resellerService->removeUserFromReseller($user);

        $this->assertTrue($result);
        $this->assertNull($user->fresh()->reseller_id);
        $this->assertEquals(0, $reseller->fresh()->current_users_count);
    }

    public function test_can_create_user_for_reseller()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_users_quota' => 10,
            'current_users_count' => 0,
        ]);

        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
        ];

        $user = $this->resellerService->createUserForReseller($reseller, $userData);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals(Role::USER, $user->role);
        $this->assertEquals($reseller->id, $user->reseller_id);
        $this->assertEquals(1, $reseller->fresh()->current_users_count);
    }

    public function test_cannot_create_user_when_quota_exceeded()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_users_quota' => 1,
            'current_users_count' => 1,
        ]);

        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Reseller has reached maximum user quota.');

        $this->resellerService->createUserForReseller($reseller, $userData);
    }

    public function test_get_reseller_dashboard_data()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_users_quota' => 10,
            'max_licenses_quota' => 50,
            'current_users_count' => 3,
            'current_licenses_count' => 15,
        ]);

        // Create some managed users and licenses
        User::factory()->count(3)->create(['reseller_id' => $reseller->id]);
        License::factory()->count(15)->create(['owner_id' => $reseller->id]);

        $dashboardData = $this->resellerService->getResellerDashboardData($reseller);

        $this->assertArrayHasKey('reseller', $dashboardData);
        $this->assertArrayHasKey('stats', $dashboardData);
        $this->assertArrayHasKey('recent_users', $dashboardData);
        $this->assertArrayHasKey('recent_licenses', $dashboardData);
        $this->assertArrayHasKey('quota_warnings', $dashboardData);

        $this->assertEquals(3, $dashboardData['stats']['total_users']);
        $this->assertEquals(10, $dashboardData['stats']['max_users']);
        $this->assertEquals(15, $dashboardData['stats']['total_licenses']);
        $this->assertEquals(50, $dashboardData['stats']['max_licenses']);
    }

    public function test_update_reseller_counts()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'current_users_count' => 0,
            'current_licenses_count' => 0,
        ]);

        // Create actual users and licenses
        User::factory()->count(5)->create(['reseller_id' => $reseller->id]);
        License::factory()->count(10)->create(['owner_id' => $reseller->id]);

        $this->resellerService->updateResellerCounts($reseller);

        $reseller->refresh();
        $this->assertEquals(5, $reseller->current_users_count);
        $this->assertEquals(10, $reseller->current_licenses_count);
    }

    public function test_get_available_users()
    {
        // Create users with different assignments
        User::factory()->create(['role' => Role::USER, 'reseller_id' => null]);
        User::factory()->create(['role' => Role::USER, 'reseller_id' => null]);
        User::factory()->create(['role' => Role::USER, 'reseller_id' => 1]); // Already assigned
        User::factory()->create(['role' => Role::ADMIN]); // Not a regular user

        $availableUsers = $this->resellerService->getAvailableUsers();

        $this->assertCount(2, $availableUsers);
        $this->assertTrue($availableUsers->every(fn($user) => $user->role === Role::USER && is_null($user->reseller_id)));
    }

    public function test_delete_reseller_transfers_licenses_and_removes_user_assignments()
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $reseller = User::factory()->create(['role' => Role::RESELLER]);
        
        // Create managed users and licenses
        $managedUser = User::factory()->create(['reseller_id' => $reseller->id]);
        $license = License::factory()->create(['owner_id' => $reseller->id]);

        $result = $this->resellerService->deleteReseller($reseller);

        $this->assertTrue($result);
        $this->assertSoftDeleted($reseller);
        $this->assertNull($managedUser->fresh()->reseller_id);
        $this->assertEquals($admin->id, $license->fresh()->owner_id);
    }

    public function test_get_reseller_statistics()
    {
        // Create resellers with different quota usage
        $reseller1 = User::factory()->create([
            'role' => Role::RESELLER,
            'max_users_quota' => 10,
            'current_users_count' => 8, // 80% - near limit
        ]);

        $reseller2 = User::factory()->create([
            'role' => Role::RESELLER,
            'max_licenses_quota' => 20,
            'current_licenses_count' => 16, // 80% - near limit
        ]);

        $statistics = $this->resellerService->getResellerStatistics();

        $this->assertArrayHasKey('total_resellers', $statistics);
        $this->assertArrayHasKey('resellers_near_user_limit', $statistics);
        $this->assertArrayHasKey('resellers_near_license_limit', $statistics);
        $this->assertEquals(2, $statistics['total_resellers']);
        $this->assertEquals(1, $statistics['resellers_near_user_limit']);
        $this->assertEquals(1, $statistics['resellers_near_license_limit']);
    }
}

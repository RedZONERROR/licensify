<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseller_can_add_user_when_under_quota()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_users_quota' => 10,
            'current_users_count' => 5,
        ]);

        $this->assertTrue($reseller->canAddUser());
    }

    public function test_reseller_cannot_add_user_when_at_quota()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_users_quota' => 10,
            'current_users_count' => 10,
        ]);

        $this->assertFalse($reseller->canAddUser());
    }

    public function test_reseller_can_add_user_when_no_quota_limit()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_users_quota' => null,
            'current_users_count' => 100,
        ]);

        $this->assertTrue($reseller->canAddUser());
    }

    public function test_non_reseller_cannot_add_user()
    {
        $user = User::factory()->create(['role' => Role::USER]);

        $this->assertFalse($user->canAddUser());
    }

    public function test_reseller_can_add_license_when_under_quota()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_licenses_quota' => 50,
            'current_licenses_count' => 25,
        ]);

        $this->assertTrue($reseller->canAddLicense());
    }

    public function test_reseller_cannot_add_license_when_at_quota()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_licenses_quota' => 50,
            'current_licenses_count' => 50,
        ]);

        $this->assertFalse($reseller->canAddLicense());
    }

    public function test_get_remaining_user_quota()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_users_quota' => 10,
            'current_users_count' => 3,
        ]);

        $this->assertEquals(7, $reseller->getRemainingUserQuota());
    }

    public function test_get_remaining_user_quota_returns_null_when_no_limit()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_users_quota' => null,
            'current_users_count' => 100,
        ]);

        $this->assertNull($reseller->getRemainingUserQuota());
    }

    public function test_get_remaining_license_quota()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_licenses_quota' => 50,
            'current_licenses_count' => 20,
        ]);

        $this->assertEquals(30, $reseller->getRemainingLicenseQuota());
    }

    public function test_get_user_quota_usage_percentage()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_users_quota' => 10,
            'current_users_count' => 8,
        ]);

        $this->assertEquals(80.0, $reseller->getUserQuotaUsagePercentage());
    }

    public function test_get_user_quota_usage_percentage_returns_null_when_no_limit()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_users_quota' => null,
            'current_users_count' => 100,
        ]);

        $this->assertNull($reseller->getUserQuotaUsagePercentage());
    }

    public function test_get_license_quota_usage_percentage()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_licenses_quota' => 50,
            'current_licenses_count' => 40,
        ]);

        $this->assertEquals(80.0, $reseller->getLicenseQuotaUsagePercentage());
    }

    public function test_is_user_quota_near_limit()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_users_quota' => 10,
            'current_users_count' => 8, // 80%
        ]);

        $this->assertTrue($reseller->isUserQuotaNearLimit());
    }

    public function test_is_user_quota_not_near_limit()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_users_quota' => 10,
            'current_users_count' => 5, // 50%
        ]);

        $this->assertFalse($reseller->isUserQuotaNearLimit());
    }

    public function test_is_license_quota_near_limit()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_licenses_quota' => 50,
            'current_licenses_count' => 45, // 90%
        ]);

        $this->assertTrue($reseller->isLicenseQuotaNearLimit());
    }

    public function test_is_license_quota_not_near_limit()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_licenses_quota' => 50,
            'current_licenses_count' => 25, // 50%
        ]);

        $this->assertFalse($reseller->isLicenseQuotaNearLimit());
    }

    public function test_update_user_count_for_reseller()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'current_users_count' => 0,
        ]);

        // Create some managed users
        User::factory()->count(3)->create(['reseller_id' => $reseller->id]);

        $reseller->updateUserCount();

        $this->assertEquals(3, $reseller->fresh()->current_users_count);
    }

    public function test_update_license_count_for_reseller()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'current_licenses_count' => 0,
        ]);

        // Create some owned licenses
        \App\Models\License::factory()->count(5)->create(['owner_id' => $reseller->id]);

        $reseller->updateLicenseCount();

        $this->assertEquals(5, $reseller->fresh()->current_licenses_count);
    }

    public function test_non_reseller_update_counts_does_nothing()
    {
        $user = User::factory()->create([
            'role' => Role::USER,
            'current_users_count' => 5,
        ]);

        $user->updateUserCount();

        // Should remain unchanged
        $this->assertEquals(5, $user->fresh()->current_users_count);
    }

    public function test_remaining_quota_never_goes_negative()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_users_quota' => 10,
            'current_users_count' => 15, // Over quota
        ]);

        $this->assertEquals(0, $reseller->getRemainingUserQuota());
    }

    public function test_quota_percentage_handles_zero_quota()
    {
        $reseller = User::factory()->create([
            'role' => Role::RESELLER,
            'max_users_quota' => 0,
            'current_users_count' => 5,
        ]);

        $this->assertNull($reseller->getUserQuotaUsagePercentage());
    }
}

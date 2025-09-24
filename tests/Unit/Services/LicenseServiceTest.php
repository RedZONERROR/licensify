<?php

namespace Tests\Unit\Services;

use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\Product;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Tests\TestCase;

class LicenseServiceTest extends TestCase
{
    use RefreshDatabase;

    private LicenseService $licenseService;
    private User $admin;
    private User $reseller;
    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->licenseService = new LicenseService();
        
        // Create test users
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->reseller = User::factory()->create(['role' => 'reseller']);
        $this->user = User::factory()->create(['role' => 'user', 'reseller_id' => $this->reseller->id]);
        
        // Create test product
        $this->product = Product::factory()->create(['is_active' => true]);
    }

    public function test_generate_license_creates_valid_license()
    {
        $data = [
            'product_id' => $this->product->id,
            'owner_id' => $this->reseller->id,
            'user_id' => $this->user->id,
            'max_devices' => 3,
            'expires_at' => now()->addYear(),
        ];

        $license = $this->licenseService->generateLicense($data);

        $this->assertInstanceOf(License::class, $license);
        $this->assertEquals($this->product->id, $license->product_id);
        $this->assertEquals($this->reseller->id, $license->owner_id);
        $this->assertEquals($this->user->id, $license->user_id);
        $this->assertEquals(3, $license->max_devices);
        $this->assertEquals(License::STATUS_ACTIVE, $license->status);
        $this->assertNotNull($license->license_key);
        $this->assertTrue(\Illuminate\Support\Str::isUuid($license->license_key));
    }

    public function test_generate_license_with_minimal_data()
    {
        $data = [
            'product_id' => $this->product->id,
            'owner_id' => $this->reseller->id,
        ];

        $license = $this->licenseService->generateLicense($data);

        $this->assertInstanceOf(License::class, $license);
        $this->assertEquals(1, $license->max_devices);
        $this->assertEquals(License::STATUS_ACTIVE, $license->status);
        $this->assertNull($license->user_id);
        $this->assertNull($license->expires_at);
    }

    public function test_generate_license_validates_required_fields()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: product_id');

        $this->licenseService->generateLicense(['owner_id' => $this->reseller->id]);
    }

    public function test_generate_license_validates_product_exists()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->licenseService->generateLicense([
            'product_id' => 999,
            'owner_id' => $this->reseller->id,
        ]);
    }

    public function test_generate_license_validates_inactive_product()
    {
        $inactiveProduct = Product::factory()->create(['is_active' => false]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->licenseService->generateLicense([
            'product_id' => $inactiveProduct->id,
            'owner_id' => $this->reseller->id,
        ]);
    }

    public function test_update_status_changes_license_status()
    {
        $license = License::factory()->create(['status' => License::STATUS_ACTIVE]);

        $result = $this->licenseService->updateStatus($license, License::STATUS_SUSPENDED);

        $this->assertTrue($result);
        $this->assertEquals(License::STATUS_SUSPENDED, $license->fresh()->status);
    }

    public function test_update_status_validates_status()
    {
        $license = License::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status: invalid_status');

        $this->licenseService->updateStatus($license, 'invalid_status');
    }

    public function test_suspend_license()
    {
        $license = License::factory()->create(['status' => License::STATUS_ACTIVE]);

        $result = $this->licenseService->suspendLicense($license);

        $this->assertTrue($result);
        $this->assertEquals(License::STATUS_SUSPENDED, $license->fresh()->status);
    }

    public function test_unsuspend_license()
    {
        $license = License::factory()->create(['status' => License::STATUS_SUSPENDED]);

        $result = $this->licenseService->unsuspendLicense($license);

        $this->assertTrue($result);
        $this->assertEquals(License::STATUS_ACTIVE, $license->fresh()->status);
    }

    public function test_reset_device_bindings()
    {
        $license = License::factory()->create(['max_devices' => 3]);
        
        // Create some activations
        LicenseActivation::factory()->count(2)->create(['license_id' => $license->id]);

        $result = $this->licenseService->resetDeviceBindings($license);

        $this->assertTrue($result);
        $this->assertEquals(License::STATUS_RESET, $license->fresh()->status);
        $this->assertEquals(0, $license->fresh()->activations()->count());
    }

    public function test_expire_license()
    {
        $license = License::factory()->create([
            'status' => License::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);

        $result = $this->licenseService->expireLicense($license);

        $this->assertTrue($result);
        $license = $license->fresh();
        $this->assertEquals(License::STATUS_EXPIRED, $license->status);
        $this->assertTrue($license->expires_at->isPast());
    }

    public function test_validate_and_bind_device_success()
    {
        $license = License::factory()->create([
            'status' => License::STATUS_ACTIVE,
            'max_devices' => 2,
            'expires_at' => now()->addYear(),
        ]);

        $deviceHash = 'test-device-hash';
        $deviceInfo = ['os' => 'Windows', 'browser' => 'Chrome'];

        $result = $this->licenseService->validateAndBindDevice($license->license_key, $deviceHash, $deviceInfo);

        $this->assertTrue($result['valid']);
        $this->assertEquals('DEVICE_BOUND', $result['code']);
        $this->assertInstanceOf(License::class, $result['license']);
        $this->assertInstanceOf(LicenseActivation::class, $result['activation']);
        $this->assertEquals($deviceHash, $result['activation']->device_hash);
        $this->assertEquals($deviceInfo, $result['activation']->device_info);
    }

    public function test_validate_and_bind_device_license_not_found()
    {
        $result = $this->licenseService->validateAndBindDevice('invalid-key', 'device-hash');

        $this->assertFalse($result['valid']);
        $this->assertEquals('LICENSE_NOT_FOUND', $result['code']);
    }

    public function test_validate_and_bind_device_inactive_license()
    {
        $license = License::factory()->create(['status' => License::STATUS_SUSPENDED]);

        $result = $this->licenseService->validateAndBindDevice($license->license_key, 'device-hash');

        $this->assertFalse($result['valid']);
        $this->assertEquals('LICENSE_INACTIVE', $result['code']);
        $this->assertEquals(License::STATUS_SUSPENDED, $result['status']);
    }

    public function test_validate_and_bind_device_expired_license()
    {
        $license = License::factory()->create([
            'status' => License::STATUS_ACTIVE,
            'expires_at' => now()->subDay(),
        ]);

        $result = $this->licenseService->validateAndBindDevice($license->license_key, 'device-hash');

        $this->assertFalse($result['valid']);
        $this->assertEquals('LICENSE_EXPIRED', $result['code']);
    }

    public function test_validate_and_bind_device_already_bound()
    {
        $license = License::factory()->create([
            'status' => License::STATUS_ACTIVE,
            'max_devices' => 2,
            'expires_at' => now()->addYear(),
        ]);
        $deviceHash = 'existing-device';
        
        // Create existing activation
        $activation = LicenseActivation::factory()->create([
            'license_id' => $license->id,
            'device_hash' => $deviceHash,
        ]);

        $result = $this->licenseService->validateAndBindDevice($license->license_key, $deviceHash);

        $this->assertTrue($result['valid']);
        $this->assertEquals('DEVICE_ALREADY_BOUND', $result['code']);
        $this->assertEquals($activation->id, $result['activation']->id);
    }

    public function test_validate_and_bind_device_limit_reached()
    {
        $license = License::factory()->create([
            'status' => License::STATUS_ACTIVE,
            'max_devices' => 1,
            'expires_at' => now()->addYear(),
        ]);
        
        // Fill up device slots
        LicenseActivation::factory()->create(['license_id' => $license->id]);

        $result = $this->licenseService->validateAndBindDevice($license->license_key, 'new-device');

        $this->assertFalse($result['valid']);
        $this->assertEquals('DEVICE_LIMIT_REACHED', $result['code']);
        $this->assertEquals(1, $result['max_devices']);
        $this->assertEquals(1, $result['active_devices']);
    }

    public function test_unbind_device()
    {
        $license = License::factory()->create();
        $deviceHash = 'test-device';
        
        LicenseActivation::factory()->create([
            'license_id' => $license->id,
            'device_hash' => $deviceHash,
        ]);

        $result = $this->licenseService->unbindDevice($license, $deviceHash);

        $this->assertTrue($result);
        $this->assertFalse($license->isDeviceBound($deviceHash));
    }

    public function test_get_licenses_for_admin()
    {
        // Create licenses with existing relationships
        for ($i = 0; $i < 5; $i++) {
            License::factory()->create([
                'product_id' => $this->product->id,
                'owner_id' => $this->reseller->id,
            ]);
        }

        // Debug: Check if licenses exist
        $totalLicenses = License::count();
        $this->assertEquals(5, $totalLicenses);

        $licenses = $this->licenseService->getLicensesForUser($this->admin);

        $this->assertEquals(5, $licenses->total());
    }

    public function test_get_licenses_for_reseller()
    {
        // Create licenses owned by reseller
        for ($i = 0; $i < 3; $i++) {
            License::factory()->create([
                'product_id' => $this->product->id,
                'owner_id' => $this->reseller->id,
            ]);
        }
        
        // Create licenses owned by reseller's users
        for ($i = 0; $i < 2; $i++) {
            License::factory()->create([
                'product_id' => $this->product->id,
                'owner_id' => $this->user->id,
            ]);
        }
        
        // Create licenses owned by other users (should not be visible)
        $otherUser = User::factory()->create(['role' => 'user']);
        for ($i = 0; $i < 2; $i++) {
            License::factory()->create([
                'product_id' => $this->product->id,
                'owner_id' => $otherUser->id,
            ]);
        }

        $licenses = $this->licenseService->getLicensesForUser($this->reseller);

        $this->assertEquals(5, $licenses->total());
    }

    public function test_get_licenses_for_user()
    {
        // Create licenses assigned to user
        License::factory()->count(2)->create(['user_id' => $this->user->id]);
        
        // Create licenses not assigned to user
        License::factory()->count(3)->create();

        $licenses = $this->licenseService->getLicensesForUser($this->user);

        $this->assertEquals(2, $licenses->total());
    }

    public function test_get_licenses_with_filters()
    {
        License::factory()->create([
            'product_id' => $this->product->id,
            'owner_id' => $this->reseller->id,
            'status' => License::STATUS_ACTIVE,
        ]);
        License::factory()->create([
            'product_id' => $this->product->id,
            'owner_id' => $this->reseller->id,
            'status' => License::STATUS_SUSPENDED,
        ]);

        $filters = ['status' => License::STATUS_ACTIVE];
        $licenses = $this->licenseService->getLicensesForUser($this->admin, $filters);

        $this->assertEquals(1, $licenses->total());
        $this->assertEquals(License::STATUS_ACTIVE, $licenses->first()->status);
    }

    public function test_get_license_statistics()
    {
        License::factory()->create(['status' => License::STATUS_ACTIVE]);
        License::factory()->create(['status' => License::STATUS_SUSPENDED]);
        License::factory()->create(['status' => License::STATUS_EXPIRED]);
        License::factory()->create([
            'status' => License::STATUS_ACTIVE,
            'expires_at' => now()->addDays(15), // Expiring soon
        ]);

        $stats = $this->licenseService->getLicenseStatistics($this->admin);

        $this->assertEquals(4, $stats['total']);
        $this->assertEquals(2, $stats['active']);
        $this->assertEquals(1, $stats['suspended']);
        $this->assertEquals(1, $stats['expired']);
        $this->assertEquals(1, $stats['expiring_soon']);
    }

    public function test_logs_license_operations()
    {
        Log::shouldReceive('info')
            ->once()
            ->with('License generated', \Mockery::type('array'));

        $this->licenseService->generateLicense([
            'product_id' => $this->product->id,
            'owner_id' => $this->reseller->id,
        ]);
    }
}
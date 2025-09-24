<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\Product;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseDeviceBindingTest extends TestCase
{
    use RefreshDatabase;

    private LicenseService $licenseService;
    private License $license;
    private Product $product;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->licenseService = new LicenseService();
        $this->product = Product::factory()->create(['is_active' => true]);
        $this->user = User::factory()->create(['role' => 'user']);
        
        $this->license = License::factory()->create([
            'product_id' => $this->product->id,
            'owner_id' => $this->user->id,
            'status' => License::STATUS_ACTIVE,
            'max_devices' => 3,
            'expires_at' => now()->addYear(),
        ]);
    }

    public function test_can_bind_device_to_active_license()
    {
        $deviceHash = 'unique-device-hash-123';
        $deviceInfo = [
            'os' => 'Windows 11',
            'browser' => 'Chrome 120',
            'device_name' => 'John\'s Laptop',
            'ip_address' => '192.168.1.100',
        ];

        $result = $this->licenseService->validateAndBindDevice(
            $this->license->license_key,
            $deviceHash,
            $deviceInfo
        );

        $this->assertTrue($result['valid']);
        $this->assertEquals('DEVICE_BOUND', $result['code']);
        $this->assertEquals('Device successfully bound', $result['message']);
        
        // Verify activation was created
        $this->assertDatabaseHas('license_activations', [
            'license_id' => $this->license->id,
            'device_hash' => $deviceHash,
        ]);

        $activation = LicenseActivation::where('device_hash', $deviceHash)->first();
        $this->assertEquals($deviceInfo, $activation->device_info);
        $this->assertNotNull($activation->activated_at);
    }

    public function test_cannot_bind_device_to_nonexistent_license()
    {
        $result = $this->licenseService->validateAndBindDevice(
            'nonexistent-license-key',
            'device-hash'
        );

        $this->assertFalse($result['valid']);
        $this->assertEquals('LICENSE_NOT_FOUND', $result['code']);
        $this->assertEquals('License not found', $result['message']);
    }

    public function test_cannot_bind_device_to_suspended_license()
    {
        $this->license->update(['status' => License::STATUS_SUSPENDED]);

        $result = $this->licenseService->validateAndBindDevice(
            $this->license->license_key,
            'device-hash'
        );

        $this->assertFalse($result['valid']);
        $this->assertEquals('LICENSE_INACTIVE', $result['code']);
        $this->assertEquals('License is not active', $result['message']);
        $this->assertEquals(License::STATUS_SUSPENDED, $result['status']);
    }

    public function test_cannot_bind_device_to_expired_license()
    {
        $this->license->update(['expires_at' => now()->subDay()]);

        $result = $this->licenseService->validateAndBindDevice(
            $this->license->license_key,
            'device-hash'
        );

        $this->assertFalse($result['valid']);
        $this->assertEquals('LICENSE_EXPIRED', $result['code']);
        $this->assertEquals('License has expired', $result['message']);
        $this->assertNotNull($result['expires_at']);
    }

    public function test_binding_already_bound_device_updates_last_seen()
    {
        $deviceHash = 'existing-device';
        
        // Create existing activation
        $activation = LicenseActivation::factory()->create([
            'license_id' => $this->license->id,
            'device_hash' => $deviceHash,
            'last_seen_at' => now()->subHour(),
        ]);

        $oldLastSeen = $activation->last_seen_at;

        $result = $this->licenseService->validateAndBindDevice(
            $this->license->license_key,
            $deviceHash
        );

        $this->assertTrue($result['valid']);
        $this->assertEquals('DEVICE_ALREADY_BOUND', $result['code']);
        $this->assertEquals('Device already bound', $result['message']);

        $activation->refresh();
        $this->assertTrue($activation->last_seen_at->isAfter($oldLastSeen));
    }

    public function test_cannot_exceed_device_limit()
    {
        // Fill up all device slots
        for ($i = 0; $i < $this->license->max_devices; $i++) {
            LicenseActivation::factory()->create([
                'license_id' => $this->license->id,
                'device_hash' => "device-{$i}",
            ]);
        }

        $result = $this->licenseService->validateAndBindDevice(
            $this->license->license_key,
            'new-device'
        );

        $this->assertFalse($result['valid']);
        $this->assertEquals('DEVICE_LIMIT_REACHED', $result['code']);
        $this->assertEquals('Maximum device limit reached', $result['message']);
        $this->assertEquals($this->license->max_devices, $result['max_devices']);
        $this->assertEquals($this->license->max_devices, $result['active_devices']);
    }

    public function test_can_unbind_device()
    {
        $deviceHash = 'device-to-unbind';
        
        LicenseActivation::factory()->create([
            'license_id' => $this->license->id,
            'device_hash' => $deviceHash,
        ]);

        $result = $this->licenseService->unbindDevice($this->license, $deviceHash);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('license_activations', [
            'license_id' => $this->license->id,
            'device_hash' => $deviceHash,
        ]);
    }

    public function test_unbinding_nonexistent_device_returns_false()
    {
        $result = $this->licenseService->unbindDevice($this->license, 'nonexistent-device');

        $this->assertFalse($result);
    }

    public function test_device_binding_with_device_type_restriction()
    {
        $this->license->update(['device_type' => 'desktop']);

        $deviceInfo = [
            'os' => 'Windows 11',
            'device_type' => 'desktop',
        ];

        $result = $this->licenseService->validateAndBindDevice(
            $this->license->license_key,
            'desktop-device',
            $deviceInfo
        );

        $this->assertTrue($result['valid']);
        $this->assertEquals('DEVICE_BOUND', $result['code']);
    }

    public function test_reset_all_device_bindings()
    {
        // Create multiple activations
        LicenseActivation::factory()->count(3)->create([
            'license_id' => $this->license->id,
        ]);

        $this->assertEquals(3, $this->license->activations()->count());

        $result = $this->licenseService->resetDeviceBindings($this->license);

        $this->assertTrue($result);
        $this->assertEquals(0, $this->license->fresh()->activations()->count());
        $this->assertEquals(License::STATUS_RESET, $this->license->fresh()->status);
    }

    public function test_license_model_device_binding_methods()
    {
        $deviceHash = 'test-device';

        // Test canBindDevice
        $this->assertTrue($this->license->canBindDevice());

        // Test isDeviceBound (should be false initially)
        $this->assertFalse($this->license->isDeviceBound($deviceHash));

        // Test bindDevice
        $activation = $this->license->bindDevice($deviceHash, ['os' => 'Linux']);
        $this->assertInstanceOf(LicenseActivation::class, $activation);
        $this->assertEquals($deviceHash, $activation->device_hash);

        // Test isDeviceBound (should be true now)
        $this->assertTrue($this->license->isDeviceBound($deviceHash));

        // Test getActiveDeviceCount
        $this->assertEquals(1, $this->license->getActiveDeviceCount());

        // Test unbindDevice
        $result = $this->license->unbindDevice($deviceHash);
        $this->assertTrue($result);
        $this->assertFalse($this->license->isDeviceBound($deviceHash));
    }

    public function test_cannot_bind_device_when_license_at_capacity()
    {
        $this->license->update(['max_devices' => 1]);

        // Bind first device
        $this->license->bindDevice('device-1');

        // Try to bind second device
        $activation = $this->license->bindDevice('device-2');
        $this->assertNull($activation);
    }

    public function test_cannot_bind_same_device_twice_directly()
    {
        $deviceHash = 'duplicate-device';

        // Bind device first time
        $activation1 = $this->license->bindDevice($deviceHash);
        $this->assertInstanceOf(LicenseActivation::class, $activation1);

        // Try to bind same device again
        $activation2 = $this->license->bindDevice($deviceHash);
        $this->assertNull($activation2);
    }

    public function test_license_activation_model_methods()
    {
        $activation = LicenseActivation::factory()->create([
            'license_id' => $this->license->id,
            'device_info' => [
                'os' => 'macOS Sonoma',
                'browser' => 'Safari 17',
                'device_name' => 'MacBook Pro',
            ],
            'last_seen_at' => now()->subMinutes(30),
        ]);

        // Test updateLastSeen
        $oldLastSeen = $activation->last_seen_at;
        $result = $activation->updateLastSeen();
        $this->assertTrue($result);
        $this->assertTrue($activation->fresh()->last_seen_at->isAfter($oldLastSeen));

        // Test isRecentlyActive with original timestamp (30 minutes ago)
        $activation->refresh();
        $activation->update(['last_seen_at' => now()->subMinutes(30)]);
        $this->assertTrue($activation->isRecentlyActive(60)); // Within 60 minutes
        $this->assertFalse($activation->isRecentlyActive(15)); // Not within 15 minutes

        // Test getDeviceInfoString
        $deviceString = $activation->getDeviceInfoString();
        $this->assertStringContainsString('macOS Sonoma', $deviceString);
        $this->assertStringContainsString('Safari 17', $deviceString);
        $this->assertStringContainsString('MacBook Pro', $deviceString);
    }

    public function test_license_activation_scopes()
    {
        $recentActivation = LicenseActivation::factory()->create([
            'license_id' => $this->license->id,
            'last_seen_at' => now()->subMinutes(30),
        ]);

        $oldActivation = LicenseActivation::factory()->create([
            'license_id' => $this->license->id,
            'last_seen_at' => now()->subHours(2),
        ]);

        $neverSeenActivation = LicenseActivation::factory()->create([
            'license_id' => $this->license->id,
            'last_seen_at' => null,
        ]);

        // Test recentlyActive scope
        $recentActivations = LicenseActivation::recentlyActive(60)->get();
        $this->assertCount(1, $recentActivations);
        $this->assertEquals($recentActivation->id, $recentActivations->first()->id);

        // Test inactive scope
        $inactiveActivations = LicenseActivation::inactive(60)->get();
        $this->assertCount(2, $inactiveActivations);

        // Test forDevice scope
        $deviceActivations = LicenseActivation::forDevice($recentActivation->device_hash)->get();
        $this->assertCount(1, $deviceActivations);
        $this->assertEquals($recentActivation->id, $deviceActivations->first()->id);
    }

    public function test_device_info_string_with_minimal_info()
    {
        $activation = LicenseActivation::factory()->create([
            'device_info' => null,
        ]);

        $this->assertEquals('Unknown Device', $activation->getDeviceInfoString());

        $activation->update(['device_info' => []]);
        $this->assertEquals('Unknown Device', $activation->getDeviceInfoString());

        $activation->update(['device_info' => ['os' => 'Windows']]);
        $this->assertEquals('Windows', $activation->getDeviceInfoString());
    }
}
<?php

namespace Tests\Unit\Models;

use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_license_has_status_constants()
    {
        $this->assertEquals('active', License::STATUS_ACTIVE);
        $this->assertEquals('expired', License::STATUS_EXPIRED);
        $this->assertEquals('suspended', License::STATUS_SUSPENDED);
        $this->assertEquals('reset', License::STATUS_RESET);
    }

    public function test_license_generates_uuid_key_on_creation()
    {
        $license = License::factory()->create(['license_key' => null]);

        $this->assertNotNull($license->license_key);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $license->license_key);
    }

    public function test_license_belongs_to_owner()
    {
        $owner = User::factory()->create();
        $license = License::factory()->create(['owner_id' => $owner->id]);

        $this->assertInstanceOf(User::class, $license->owner);
        $this->assertEquals($owner->id, $license->owner->id);
    }

    public function test_license_belongs_to_user()
    {
        $user = User::factory()->create();
        $license = License::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $license->user);
        $this->assertEquals($user->id, $license->user->id);
    }

    public function test_license_belongs_to_product()
    {
        $product = Product::factory()->create();
        $license = License::factory()->create(['product_id' => $product->id]);

        $this->assertInstanceOf(Product::class, $license->product);
        $this->assertEquals($product->id, $license->product->id);
    }

    public function test_license_has_many_activations()
    {
        $license = License::factory()->create();
        $activation1 = LicenseActivation::factory()->create(['license_id' => $license->id]);
        $activation2 = LicenseActivation::factory()->create(['license_id' => $license->id]);

        $this->assertCount(2, $license->activations);
        $this->assertTrue($license->activations->contains($activation1));
        $this->assertTrue($license->activations->contains($activation2));
    }

    public function test_license_status_checks()
    {
        $activeLicense = License::factory()->create([
            'status' => License::STATUS_ACTIVE,
            'expires_at' => now()->addDays(30)
        ]);

        $expiredLicense = License::factory()->create([
            'status' => License::STATUS_EXPIRED,
            'expires_at' => now()->subDays(1)
        ]);

        $suspendedLicense = License::factory()->create([
            'status' => License::STATUS_SUSPENDED
        ]);

        $resetLicense = License::factory()->create([
            'status' => License::STATUS_RESET
        ]);

        $this->assertTrue($activeLicense->isActive());
        $this->assertFalse($activeLicense->isExpired());
        $this->assertFalse($activeLicense->isSuspended());
        $this->assertFalse($activeLicense->needsReset());

        $this->assertFalse($expiredLicense->isActive());
        $this->assertTrue($expiredLicense->isExpired());

        $this->assertTrue($suspendedLicense->isSuspended());
        $this->assertFalse($suspendedLicense->isActive());

        $this->assertTrue($resetLicense->needsReset());
        $this->assertFalse($resetLicense->isActive());
    }

    public function test_license_can_be_suspended_and_unsuspended()
    {
        $license = License::factory()->create(['status' => License::STATUS_ACTIVE]);

        $this->assertTrue($license->suspend());
        $license->refresh();
        $this->assertEquals(License::STATUS_SUSPENDED, $license->status);

        $this->assertTrue($license->unsuspend());
        $license->refresh();
        $this->assertEquals(License::STATUS_ACTIVE, $license->status);
    }

    public function test_license_can_reset_device_bindings()
    {
        $license = License::factory()->create();
        LicenseActivation::factory()->create(['license_id' => $license->id]);
        LicenseActivation::factory()->create(['license_id' => $license->id]);

        $this->assertCount(2, $license->activations);

        $this->assertTrue($license->resetDeviceBindings());
        $license->refresh();

        $this->assertEquals(License::STATUS_RESET, $license->status);
        $this->assertCount(0, $license->fresh()->activations);
    }

    public function test_license_can_be_expired()
    {
        $license = License::factory()->create(['status' => License::STATUS_ACTIVE]);

        $this->assertTrue($license->expire());
        $license->refresh();

        $this->assertEquals(License::STATUS_EXPIRED, $license->status);
        $this->assertTrue($license->expires_at->isPast());
    }

    public function test_license_device_binding_logic()
    {
        $license = License::factory()->create([
            'status' => License::STATUS_ACTIVE,
            'max_devices' => 2
        ]);

        $this->assertTrue($license->canBindDevice());
        $this->assertEquals(0, $license->getActiveDeviceCount());

        // Bind first device
        $deviceHash1 = 'device-hash-1';
        $activation1 = $license->bindDevice($deviceHash1, ['os' => 'Windows']);

        $this->assertInstanceOf(LicenseActivation::class, $activation1);
        $this->assertEquals(1, $license->getActiveDeviceCount());
        $this->assertTrue($license->isDeviceBound($deviceHash1));

        // Bind second device
        $deviceHash2 = 'device-hash-2';
        $activation2 = $license->bindDevice($deviceHash2, ['os' => 'macOS']);

        $this->assertInstanceOf(LicenseActivation::class, $activation2);
        $this->assertEquals(2, $license->getActiveDeviceCount());
        $this->assertFalse($license->canBindDevice()); // Max reached

        // Try to bind third device (should fail)
        $deviceHash3 = 'device-hash-3';
        $activation3 = $license->bindDevice($deviceHash3);

        $this->assertNull($activation3);
        $this->assertEquals(2, $license->getActiveDeviceCount());

        // Try to bind same device again (should fail)
        $duplicateActivation = $license->bindDevice($deviceHash1);
        $this->assertNull($duplicateActivation);

        // Unbind device
        $this->assertTrue($license->unbindDevice($deviceHash1));
        $this->assertEquals(1, $license->getActiveDeviceCount());
        $this->assertFalse($license->isDeviceBound($deviceHash1));
        $this->assertTrue($license->canBindDevice());
    }

    public function test_license_scopes()
    {
        $activeLicense = License::factory()->create([
            'status' => License::STATUS_ACTIVE,
            'expires_at' => now()->addDays(30)
        ]);

        $expiredLicense = License::factory()->create([
            'status' => License::STATUS_EXPIRED,
            'expires_at' => now()->subDays(1)
        ]);

        $owner = User::factory()->create();
        $ownedLicense = License::factory()->create(['owner_id' => $owner->id]);

        $assignee = User::factory()->create();
        $assignedLicense = License::factory()->create(['user_id' => $assignee->id]);

        // Test active scope
        $activeLicenses = License::active()->get();
        $this->assertTrue($activeLicenses->contains($activeLicense));
        $this->assertFalse($activeLicenses->contains($expiredLicense));

        // Test expired scope
        $expiredLicenses = License::expired()->get();
        $this->assertTrue($expiredLicenses->contains($expiredLicense));
        $this->assertFalse($expiredLicenses->contains($activeLicense));

        // Test owned by scope
        $ownerLicenses = License::ownedBy($owner->id)->get();
        $this->assertTrue($ownerLicenses->contains($ownedLicense));
        $this->assertFalse($ownerLicenses->contains($assignedLicense));

        // Test assigned to scope
        $assigneeLicenses = License::assignedTo($assignee->id)->get();
        $this->assertTrue($assigneeLicenses->contains($assignedLicense));
        $this->assertFalse($assigneeLicenses->contains($ownedLicense));
    }

    public function test_license_casts_attributes_correctly()
    {
        $license = License::factory()->create([
            'expires_at' => '2023-12-31 23:59:59',
            'metadata' => ['key' => 'value'],
            'max_devices' => '5'
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $license->expires_at);
        $this->assertIsArray($license->metadata);
        $this->assertEquals(['key' => 'value'], $license->metadata);
        $this->assertIsInt($license->max_devices);
        $this->assertEquals(5, $license->max_devices);
    }
}
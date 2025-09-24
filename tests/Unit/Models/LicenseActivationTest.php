<?php

namespace Tests\Unit\Models;

use App\Models\License;
use App\Models\LicenseActivation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_license_activation_belongs_to_license()
    {
        $license = License::factory()->create();
        $activation = LicenseActivation::factory()->create(['license_id' => $license->id]);

        $this->assertInstanceOf(License::class, $activation->license);
        $this->assertEquals($license->id, $activation->license->id);
    }

    public function test_activation_sets_activated_at_on_creation()
    {
        $activation = LicenseActivation::factory()->create(['activated_at' => null]);

        $this->assertNotNull($activation->activated_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $activation->activated_at);
    }

    public function test_activation_can_update_last_seen()
    {
        $activation = LicenseActivation::factory()->create(['last_seen_at' => null]);

        $this->assertNull($activation->last_seen_at);

        $this->assertTrue($activation->updateLastSeen());
        $activation->refresh();

        $this->assertNotNull($activation->last_seen_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $activation->last_seen_at);
    }

    public function test_activation_can_check_recent_activity()
    {
        $recentActivation = LicenseActivation::factory()->create([
            'last_seen_at' => now()->subMinutes(30)
        ]);

        $oldActivation = LicenseActivation::factory()->create([
            'last_seen_at' => now()->subMinutes(90)
        ]);

        $neverSeenActivation = LicenseActivation::factory()->create([
            'last_seen_at' => null
        ]);

        $this->assertTrue($recentActivation->isRecentlyActive(60));
        $this->assertFalse($oldActivation->isRecentlyActive(60));
        $this->assertFalse($neverSeenActivation->isRecentlyActive(60));
    }

    public function test_activation_formats_device_info()
    {
        $activationWithInfo = LicenseActivation::factory()->create([
            'device_info' => [
                'os' => 'Windows 11',
                'browser' => 'Chrome',
                'device_name' => 'John\'s Laptop'
            ]
        ]);

        $activationWithPartialInfo = LicenseActivation::factory()->create([
            'device_info' => ['os' => 'macOS']
        ]);

        $activationWithoutInfo = LicenseActivation::factory()->create([
            'device_info' => null
        ]);

        $this->assertEquals(
            'Windows 11 - Chrome - John\'s Laptop',
            $activationWithInfo->getDeviceInfoString()
        );

        $this->assertEquals('macOS', $activationWithPartialInfo->getDeviceInfoString());
        $this->assertEquals('Unknown Device', $activationWithoutInfo->getDeviceInfoString());
    }

    public function test_activation_scopes()
    {
        $recentActivation = LicenseActivation::factory()->create([
            'last_seen_at' => now()->subMinutes(30)
        ]);

        $oldActivation = LicenseActivation::factory()->create([
            'last_seen_at' => now()->subMinutes(90)
        ]);

        $neverSeenActivation = LicenseActivation::factory()->create([
            'last_seen_at' => null
        ]);

        $deviceHash = 'test-device-hash';
        $specificDeviceActivation = LicenseActivation::factory()->create([
            'device_hash' => $deviceHash
        ]);

        // Test recently active scope
        $recentActivations = LicenseActivation::recentlyActive(60)->get();
        $this->assertTrue($recentActivations->contains($recentActivation));
        $this->assertFalse($recentActivations->contains($oldActivation));
        $this->assertFalse($recentActivations->contains($neverSeenActivation));

        // Test inactive scope
        $inactiveActivations = LicenseActivation::inactive(60)->get();
        $this->assertFalse($inactiveActivations->contains($recentActivation));
        $this->assertTrue($inactiveActivations->contains($oldActivation));
        $this->assertTrue($inactiveActivations->contains($neverSeenActivation));

        // Test for device scope
        $deviceActivations = LicenseActivation::forDevice($deviceHash)->get();
        $this->assertTrue($deviceActivations->contains($specificDeviceActivation));
        $this->assertFalse($deviceActivations->contains($recentActivation));
    }

    public function test_activation_casts_attributes_correctly()
    {
        $activation = LicenseActivation::factory()->create([
            'device_info' => ['os' => 'Windows', 'version' => '11'],
            'activated_at' => '2023-01-01 12:00:00',
            'last_seen_at' => '2023-01-02 12:00:00'
        ]);

        $this->assertIsArray($activation->device_info);
        $this->assertEquals(['os' => 'Windows', 'version' => '11'], $activation->device_info);
        $this->assertInstanceOf(\Carbon\Carbon::class, $activation->activated_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $activation->last_seen_at);
    }
}
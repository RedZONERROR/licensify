<?php

namespace Tests\Feature\Admin;

use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $developer;
    private User $reseller;
    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->developer = User::factory()->create(['role' => 'developer']);
        $this->reseller = User::factory()->create(['role' => 'reseller']);
        $this->user = User::factory()->create(['role' => 'user', 'reseller_id' => $this->reseller->id]);
        $this->product = Product::factory()->create(['is_active' => true]);
    }

    public function test_admin_can_access_license_index()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.licenses.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.licenses.index');
        $response->assertViewHas(['licenses', 'statistics', 'products', 'filters']);
    }

    public function test_reseller_can_access_license_index()
    {
        $this->actingAs($this->reseller);

        $response = $this->get(route('admin.licenses.index'));

        $response->assertStatus(200);
    }

    public function test_regular_user_cannot_access_license_index()
    {
        $this->actingAs($this->user);

        $response = $this->get(route('admin.licenses.index'));

        $response->assertStatus(403);
    }

    public function test_admin_can_create_license()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.licenses.create'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.licenses.create');
        $response->assertViewHas(['products', 'users']);
    }

    public function test_admin_can_store_license()
    {
        $this->actingAs($this->admin);

        $licenseData = [
            'product_id' => $this->product->id,
            'owner_id' => $this->reseller->id,
            'user_id' => $this->user->id,
            'max_devices' => 3,
            'expires_at' => now()->addYear()->format('Y-m-d'),
            'status' => License::STATUS_ACTIVE,
        ];

        $response = $this->post(route('admin.licenses.store'), $licenseData);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('licenses', [
            'product_id' => $this->product->id,
            'owner_id' => $this->reseller->id,
            'user_id' => $this->user->id,
            'max_devices' => 3,
            'status' => License::STATUS_ACTIVE,
        ]);
    }

    public function test_store_license_validates_required_fields()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.licenses.store'), []);

        $response->assertSessionHasErrors(['product_id', 'owner_id', 'max_devices', 'status']);
    }

    public function test_reseller_can_only_create_licenses_for_their_users()
    {
        $otherUser = User::factory()->create(['role' => 'user']);
        $this->actingAs($this->reseller);

        $licenseData = [
            'product_id' => $this->product->id,
            'owner_id' => $this->reseller->id,
            'user_id' => $otherUser->id, // Not their user
            'max_devices' => 1,
            'status' => License::STATUS_ACTIVE,
        ];

        $response = $this->post(route('admin.licenses.store'), $licenseData);

        $response->assertSessionHasErrors(['user_id']);
    }

    public function test_admin_can_view_license()
    {
        $license = License::factory()->create([
            'product_id' => $this->product->id,
            'owner_id' => $this->reseller->id,
        ]);

        $this->actingAs($this->admin);



        $response = $this->get(route('admin.licenses.show', $license));

        $response->assertStatus(200);
        $response->assertViewIs('admin.licenses.show');
        $response->assertViewHas('license');
    }

    public function test_reseller_can_view_their_license()
    {
        $license = License::factory()->create(['owner_id' => $this->reseller->id]);

        $this->actingAs($this->reseller);

        $response = $this->get(route('admin.licenses.show', $license));

        $response->assertStatus(200);
    }

    public function test_reseller_cannot_view_others_license()
    {
        $license = License::factory()->create(['owner_id' => $this->admin->id]);

        $this->actingAs($this->reseller);

        $response = $this->get(route('admin.licenses.show', $license));

        $response->assertStatus(403);
    }

    public function test_admin_can_update_license()
    {
        $license = License::factory()->create([
            'max_devices' => 1,
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($this->admin);

        $updateData = [
            'user_id' => $this->user->id,
            'max_devices' => 5,
            'expires_at' => now()->addYear()->format('Y-m-d'),
        ];

        $response = $this->put(route('admin.licenses.update', $license), $updateData);

        $response->assertRedirect(route('admin.licenses.show', $license));
        $response->assertSessionHas('success');

        $license->refresh();
        $this->assertEquals($this->user->id, $license->user_id);
        $this->assertEquals(5, $license->max_devices);
    }

    public function test_admin_can_delete_license()
    {
        $license = License::factory()->create();

        $this->actingAs($this->admin);

        $response = $this->delete(route('admin.licenses.destroy', $license));

        $response->assertRedirect(route('admin.licenses.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('licenses', ['id' => $license->id]);
    }

    public function test_suspend_license_via_ajax()
    {
        $license = License::factory()->create(['status' => License::STATUS_ACTIVE]);

        $this->actingAs($this->admin);

        $response = $this->postJson(route('admin.licenses.suspend', $license));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => License::STATUS_SUSPENDED,
        ]);

        $this->assertEquals(License::STATUS_SUSPENDED, $license->fresh()->status);
    }

    public function test_unsuspend_license_via_ajax()
    {
        $license = License::factory()->create(['status' => License::STATUS_SUSPENDED]);

        $this->actingAs($this->admin);

        $response = $this->postJson(route('admin.licenses.unsuspend', $license));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => License::STATUS_ACTIVE,
        ]);

        $this->assertEquals(License::STATUS_ACTIVE, $license->fresh()->status);
    }

    public function test_reset_device_bindings_via_ajax()
    {
        $license = License::factory()->create(['max_devices' => 3]);
        LicenseActivation::factory()->count(2)->create(['license_id' => $license->id]);

        $this->actingAs($this->admin);

        $response = $this->postJson(route('admin.licenses.reset-devices', $license));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'active_devices' => 0,
        ]);

        $this->assertEquals(0, $license->fresh()->activations()->count());
        $this->assertEquals(License::STATUS_RESET, $license->fresh()->status);
    }

    public function test_expire_license_via_ajax()
    {
        $license = License::factory()->create([
            'status' => License::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);

        $this->actingAs($this->admin);

        $response = $this->postJson(route('admin.licenses.expire', $license));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => License::STATUS_EXPIRED,
        ]);

        $license->refresh();
        $this->assertEquals(License::STATUS_EXPIRED, $license->status);
        $this->assertTrue($license->expires_at->isPast());
    }

    public function test_unbind_device_via_ajax()
    {
        $license = License::factory()->create();
        $deviceHash = 'test-device-hash';
        
        LicenseActivation::factory()->create([
            'license_id' => $license->id,
            'device_hash' => $deviceHash,
        ]);

        $this->actingAs($this->admin);

        $response = $this->postJson(route('admin.licenses.unbind-device', $license), [
            'device_hash' => $deviceHash,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'active_devices' => 0,
        ]);

        $this->assertEquals(0, $license->fresh()->activations()->count());
    }

    public function test_get_statistics_via_ajax()
    {
        License::factory()->create(['status' => License::STATUS_ACTIVE]);
        License::factory()->create(['status' => License::STATUS_SUSPENDED]);

        $this->actingAs($this->admin);

        $response = $this->getJson(route('admin.licenses.statistics'));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'statistics' => [
                'total' => 2,
                'active' => 1,
                'suspended' => 1,
                'expired' => 0,
                'expiring_soon' => 0,
            ],
        ]);
    }

    public function test_license_index_filters_work()
    {
        License::factory()->create(['status' => License::STATUS_ACTIVE]);
        License::factory()->create(['status' => License::STATUS_SUSPENDED]);

        $this->actingAs($this->admin);

        $response = $this->get(route('admin.licenses.index', ['status' => License::STATUS_ACTIVE]));

        $response->assertStatus(200);
        $licenses = $response->viewData('licenses');
        $this->assertEquals(1, $licenses->total());
        $this->assertEquals(License::STATUS_ACTIVE, $licenses->first()->status);
    }

    public function test_license_index_search_works()
    {
        $license = License::factory()->create(['license_key' => 'search-test-key']);
        License::factory()->create(['license_key' => 'other-key']);

        $this->actingAs($this->admin);

        $response = $this->get(route('admin.licenses.index', ['search' => 'search-test']));

        $response->assertStatus(200);
        $licenses = $response->viewData('licenses');
        $this->assertEquals(1, $licenses->total());
        $this->assertEquals('search-test-key', $licenses->first()->license_key);
    }

    public function test_reseller_sees_only_their_licenses()
    {
        // Licenses owned by reseller
        License::factory()->count(2)->create(['owner_id' => $this->reseller->id]);
        
        // Licenses owned by reseller's users
        License::factory()->create(['owner_id' => $this->user->id]);
        
        // Licenses owned by others
        License::factory()->count(3)->create();

        $this->actingAs($this->reseller);

        $response = $this->get(route('admin.licenses.index'));

        $licenses = $response->viewData('licenses');
        $this->assertEquals(3, $licenses->total()); // 2 owned + 1 from their user
    }

    public function test_unauthorized_ajax_operations_return_403()
    {
        $license = License::factory()->create(['owner_id' => $this->admin->id]);

        $this->actingAs($this->reseller);

        $response = $this->postJson(route('admin.licenses.suspend', $license));
        $response->assertStatus(403);

        $response = $this->postJson(route('admin.licenses.reset-devices', $license));
        $response->assertStatus(403);
    }

    public function test_ajax_operations_handle_errors_gracefully()
    {
        $license = License::factory()->create();

        $this->actingAs($this->admin);

        // Test unbind device with invalid hash
        $response = $this->postJson(route('admin.licenses.unbind-device', $license), [
            'device_hash' => 'non-existent-hash',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
        ]);
    }
}
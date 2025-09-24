<?php

namespace Tests\Unit\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_argon2id_hashing_is_configured(): void
    {
        $password = 'test-password-123';
        $hashedPassword = Hash::make($password);
        
        $this->assertTrue(Hash::check($password, $hashedPassword));
        $this->assertStringStartsWith('$argon2id$', $hashedPassword);
    }

    public function test_admin_access_gate(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        $this->assertTrue(Gate::forUser($admin)->allows('admin-access'));
        $this->assertFalse(Gate::forUser($user)->allows('admin-access'));
    }

    public function test_developer_access_gate(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $developer = User::factory()->create(['role' => 'developer']);
        $user = User::factory()->create(['role' => 'user']);

        $this->assertTrue(Gate::forUser($admin)->allows('developer-access'));
        $this->assertTrue(Gate::forUser($developer)->allows('developer-access'));
        $this->assertFalse(Gate::forUser($user)->allows('developer-access'));
    }

    public function test_reseller_access_gate(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $developer = User::factory()->create(['role' => 'developer']);
        $reseller = User::factory()->create(['role' => 'reseller']);
        $user = User::factory()->create(['role' => 'user']);

        $this->assertTrue(Gate::forUser($admin)->allows('reseller-access'));
        $this->assertTrue(Gate::forUser($developer)->allows('reseller-access'));
        $this->assertTrue(Gate::forUser($reseller)->allows('reseller-access'));
        $this->assertFalse(Gate::forUser($user)->allows('reseller-access'));
    }

    public function test_manage_users_gate(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reseller = User::factory()->create(['role' => 'reseller']);
        $developer = User::factory()->create(['role' => 'developer']);
        $user = User::factory()->create(['role' => 'user']);

        $this->assertTrue(Gate::forUser($admin)->allows('manage-users'));
        $this->assertTrue(Gate::forUser($reseller)->allows('manage-users'));
        $this->assertFalse(Gate::forUser($developer)->allows('manage-users'));
        $this->assertFalse(Gate::forUser($user)->allows('manage-users'));
    }

    public function test_manage_licenses_gate(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reseller = User::factory()->create(['role' => 'reseller']);
        $developer = User::factory()->create(['role' => 'developer']);
        $user = User::factory()->create(['role' => 'user']);

        $this->assertTrue(Gate::forUser($admin)->allows('manage-licenses'));
        $this->assertTrue(Gate::forUser($reseller)->allows('manage-licenses'));
        $this->assertFalse(Gate::forUser($developer)->allows('manage-licenses'));
        $this->assertFalse(Gate::forUser($user)->allows('manage-licenses'));
    }

    public function test_system_operations_gate(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $developer = User::factory()->create(['role' => 'developer']);
        $reseller = User::factory()->create(['role' => 'reseller']);
        $user = User::factory()->create(['role' => 'user']);

        $this->assertTrue(Gate::forUser($admin)->allows('system-operations'));
        $this->assertTrue(Gate::forUser($developer)->allows('system-operations'));
        $this->assertFalse(Gate::forUser($reseller)->allows('system-operations'));
        $this->assertFalse(Gate::forUser($user)->allows('system-operations'));
    }
}
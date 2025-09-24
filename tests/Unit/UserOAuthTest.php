<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test environment for hashing
        config([
            'hashing.driver' => 'bcrypt',
            'hashing.bcrypt.rounds' => 4, // Faster for tests
        ]);
    }

    public function test_has_oauth_provider_returns_true_when_provider_exists()
    {
        $user = User::factory()->create([
            'oauth_providers' => [
                'google' => [
                    'id' => '123456789',
                    'email' => 'test@gmail.com',
                    'name' => 'Test User',
                    'avatar' => 'https://example.com/avatar.jpg',
                    'linked_at' => now()->toISOString()
                ]
            ]
        ]);

        $this->assertTrue($user->hasOAuthProvider('google'));
        $this->assertFalse($user->hasOAuthProvider('facebook'));
    }

    public function test_has_oauth_provider_returns_false_when_no_providers()
    {
        $user = User::factory()->create(['oauth_providers' => null]);

        $this->assertFalse($user->hasOAuthProvider('google'));
    }

    public function test_get_oauth_provider_returns_provider_data()
    {
        $providerData = [
            'id' => '123456789',
            'email' => 'test@gmail.com',
            'name' => 'Test User',
            'avatar' => 'https://example.com/avatar.jpg',
            'linked_at' => now()->toISOString()
        ];

        $user = User::factory()->create([
            'oauth_providers' => ['google' => $providerData]
        ]);

        $this->assertEquals($providerData, $user->getOAuthProvider('google'));
        $this->assertNull($user->getOAuthProvider('facebook'));
    }

    public function test_is_oauth_only_returns_true_when_no_password_and_has_oauth()
    {
        $user = User::factory()->create([
            'password' => null,
            'oauth_providers' => [
                'google' => [
                    'id' => '123456789',
                    'email' => 'test@gmail.com',
                    'name' => 'Test User',
                    'avatar' => 'https://example.com/avatar.jpg',
                    'linked_at' => now()->toISOString()
                ]
            ]
        ]);

        $this->assertTrue($user->isOAuthOnly());
    }

    public function test_is_oauth_only_returns_false_when_has_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'oauth_providers' => [
                'google' => [
                    'id' => '123456789',
                    'email' => 'test@gmail.com',
                    'name' => 'Test User',
                    'avatar' => 'https://example.com/avatar.jpg',
                    'linked_at' => now()->toISOString()
                ]
            ]
        ]);

        $this->assertFalse($user->isOAuthOnly());
    }

    public function test_is_oauth_only_returns_false_when_no_oauth_providers()
    {
        $user = User::factory()->create([
            'password' => null,
            'oauth_providers' => null
        ]);

        $this->assertFalse($user->isOAuthOnly());
    }

    public function test_has_hybrid_auth_returns_true_when_has_both_password_and_oauth()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'oauth_providers' => [
                'google' => [
                    'id' => '123456789',
                    'email' => 'test@gmail.com',
                    'name' => 'Test User',
                    'avatar' => 'https://example.com/avatar.jpg',
                    'linked_at' => now()->toISOString()
                ]
            ]
        ]);

        $this->assertTrue($user->hasHybridAuth());
    }

    public function test_has_hybrid_auth_returns_false_when_missing_password_or_oauth()
    {
        $userWithoutPassword = User::factory()->create([
            'password' => null,
            'oauth_providers' => [
                'google' => [
                    'id' => '123456789',
                    'email' => 'test@gmail.com',
                    'name' => 'Test User',
                    'avatar' => 'https://example.com/avatar.jpg',
                    'linked_at' => now()->toISOString()
                ]
            ]
        ]);

        $userWithoutOAuth = User::factory()->create([
            'password' => Hash::make('password'),
            'oauth_providers' => null
        ]);

        $this->assertFalse($userWithoutPassword->hasHybridAuth());
        $this->assertFalse($userWithoutOAuth->hasHybridAuth());
    }

    public function test_get_linked_providers_returns_array_of_provider_names()
    {
        $user = User::factory()->create([
            'oauth_providers' => [
                'google' => [
                    'id' => '123456789',
                    'email' => 'test@gmail.com',
                    'name' => 'Test User',
                    'avatar' => 'https://example.com/avatar.jpg',
                    'linked_at' => now()->toISOString()
                ]
            ]
        ]);

        $this->assertEquals(['google'], $user->getLinkedProviders());
    }

    public function test_get_linked_providers_returns_empty_array_when_no_providers()
    {
        $user = User::factory()->create(['oauth_providers' => null]);

        $this->assertEquals([], $user->getLinkedProviders());
    }

    public function test_can_unlink_provider_returns_false_when_only_auth_method()
    {
        $user = User::factory()->create([
            'password' => null,
            'oauth_providers' => [
                'google' => [
                    'id' => '123456789',
                    'email' => 'test@gmail.com',
                    'name' => 'Test User',
                    'avatar' => 'https://example.com/avatar.jpg',
                    'linked_at' => now()->toISOString()
                ]
            ]
        ]);

        $this->assertFalse($user->canUnlinkProvider('google'));
    }

    public function test_can_unlink_provider_returns_true_when_has_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'oauth_providers' => [
                'google' => [
                    'id' => '123456789',
                    'email' => 'test@gmail.com',
                    'name' => 'Test User',
                    'avatar' => 'https://example.com/avatar.jpg',
                    'linked_at' => now()->toISOString()
                ]
            ]
        ]);

        $this->assertTrue($user->canUnlinkProvider('google'));
    }

    public function test_can_unlink_provider_returns_true_when_has_multiple_oauth_providers()
    {
        $user = User::factory()->create([
            'password' => null,
            'oauth_providers' => [
                'google' => [
                    'id' => '123456789',
                    'email' => 'test@gmail.com',
                    'name' => 'Test User',
                    'avatar' => 'https://example.com/avatar.jpg',
                    'linked_at' => now()->toISOString()
                ],
                'facebook' => [
                    'id' => '987654321',
                    'email' => 'test@facebook.com',
                    'name' => 'Test User',
                    'avatar' => 'https://example.com/avatar2.jpg',
                    'linked_at' => now()->toISOString()
                ]
            ]
        ]);

        $this->assertTrue($user->canUnlinkProvider('google'));
        $this->assertTrue($user->canUnlinkProvider('facebook'));
    }

    public function test_can_unlink_provider_returns_false_when_provider_not_linked()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'oauth_providers' => null
        ]);

        $this->assertFalse($user->canUnlinkProvider('google'));
    }

    public function test_oauth_providers_cast_to_json()
    {
        $providerData = [
            'google' => [
                'id' => '123456789',
                'email' => 'test@gmail.com',
                'name' => 'Test User',
                'avatar' => 'https://example.com/avatar.jpg',
                'linked_at' => now()->toISOString()
            ]
        ];

        $user = User::factory()->create(['oauth_providers' => $providerData]);

        // Test that the data is properly cast
        $this->assertIsArray($user->oauth_providers);
        $this->assertEquals($providerData, $user->oauth_providers);

        // Test that it's stored as JSON in database
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'oauth_providers' => json_encode($providerData)
        ]);
    }
}
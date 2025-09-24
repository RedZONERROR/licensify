<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class OAuthIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test environment variables
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
            'hashing.driver' => 'bcrypt',
            'hashing.bcrypt.rounds' => 4, // Faster for tests
        ]);
    }

    public function test_oauth_redirect_to_google()
    {
        $response = $this->get('/auth/google');
        
        $response->assertStatus(302);
        $this->assertStringContainsString('accounts.google.com', $response->headers->get('Location'));
    }

    public function test_oauth_redirect_with_invalid_provider_returns_404()
    {
        $response = $this->get('/auth/facebook');
        
        $response->assertStatus(404);
    }

    public function test_oauth_callback_creates_new_user()
    {
        $this->mockSocialiteUser('123456789', 'test@gmail.com', 'Test User', 'https://example.com/avatar.jpg');

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/profile');
        $this->assertAuthenticated();

        $user = User::where('email', 'test@gmail.com')->first();
        $this->assertNotNull($user, 'User should be created after OAuth callback');
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@gmail.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);
        $this->assertTrue($user->hasOAuthProvider('google'));
        $this->assertEquals('123456789', $user->getOAuthProvider('google')['id']);
    }

    public function test_oauth_callback_logs_in_existing_user()
    {
        $user = User::factory()->create([
            'email' => 'test@gmail.com',
            'password' => Hash::make('password'),
        ]);

        $this->mockSocialiteUser('123456789', 'test@gmail.com', 'Test User Updated', 'https://example.com/avatar.jpg');

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertTrue($user->hasOAuthProvider('google'));
        $this->assertNotNull($user->password); // Should keep existing password
    }

    public function test_oauth_callback_existing_user_without_password_shows_setup_message()
    {
        $user = User::factory()->create([
            'email' => 'test@gmail.com',
            'password' => null,
        ]);

        $this->mockSocialiteUser('123456789', 'test@gmail.com', 'Test User', 'https://example.com/avatar.jpg');

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/profile');
        $response->assertSessionHas('oauth_setup_password', true);
        $this->assertAuthenticatedAs($user);
    }

    public function test_oauth_callback_handles_invalid_state_exception()
    {
        $this->mockSocialiteException(new \Laravel\Socialite\Two\InvalidStateException());

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['oauth' => 'Authentication session expired. Please try again.']);
        $this->assertGuest();
    }

    public function test_oauth_callback_handles_general_exception()
    {
        $this->mockSocialiteException(new \Exception('OAuth error'));

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['oauth' => 'Authentication failed. Please try again.']);
        $this->assertGuest();
    }

    public function test_link_oauth_provider_to_authenticated_user()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->mockSocialiteUser('123456789', 'test@gmail.com', 'Test User', 'https://example.com/avatar.jpg');

        $response = $this->actingAs($user)->get('/auth/google/link');

        $response->assertRedirect('/profile');
        $response->assertSessionHas('success', 'Gmail account successfully linked to your profile!');

        $user->refresh();
        $this->assertTrue($user->hasOAuthProvider('google'));
        $this->assertEquals('123456789', $user->getOAuthProvider('google')['id']);
    }

    public function test_link_oauth_provider_prevents_duplicate_linking()
    {
        $existingUser = User::factory()->create([
            'email' => 'existing@gmail.com',
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

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->mockSocialiteUser('123456789', 'test@gmail.com', 'Test User', 'https://example.com/avatar.jpg');

        $response = $this->actingAs($user)->get('/auth/google/link');

        $response->assertRedirect('/profile');
        $response->assertSessionHasErrors(['oauth' => 'This Gmail account is already linked to another user.']);

        $user->refresh();
        $this->assertFalse($user->hasOAuthProvider('google'));
    }

    public function test_unlink_oauth_provider_from_user()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
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

        $response = $this->actingAs($user)->delete('/auth/google/unlink');

        $response->assertRedirect('/profile');
        $response->assertSessionHas('success', 'Gmail account successfully unlinked from your profile.');

        $user->refresh();
        $this->assertFalse($user->hasOAuthProvider('google'));
    }

    public function test_unlink_oauth_provider_prevents_unlinking_only_auth_method()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => null, // OAuth-only user
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

        $response = $this->actingAs($user)->delete('/auth/google/unlink');

        $response->assertRedirect('/profile');
        $response->assertSessionHasErrors(['oauth' => 'You must set a password before unlinking your Gmail account.']);

        $user->refresh();
        $this->assertTrue($user->hasOAuthProvider('google'));
    }

    public function test_unlink_oauth_provider_that_is_not_linked()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($user)->delete('/auth/google/unlink');

        $response->assertRedirect('/profile');
        $response->assertSessionHasErrors(['oauth' => 'Gmail account is not linked to your profile.']);
    }

    public function test_oauth_routes_require_authentication_for_linking()
    {
        $response = $this->get('/auth/google/link');
        $response->assertRedirect('/login');

        $response = $this->delete('/auth/google/unlink');
        $response->assertRedirect('/login');
    }

    public function test_user_model_oauth_helper_methods()
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

        // Test OAuth helper methods
        $this->assertTrue($user->hasOAuthProvider('google'));
        $this->assertFalse($user->hasOAuthProvider('facebook'));
        $this->assertTrue($user->isOAuthOnly());
        $this->assertFalse($user->hasHybridAuth());
        $this->assertEquals(['google'], $user->getLinkedProviders());
        $this->assertFalse($user->canUnlinkProvider('google')); // Can't unlink only auth method

        // Test with hybrid auth
        $user->update(['password' => Hash::make('password')]);
        $this->assertFalse($user->isOAuthOnly());
        $this->assertTrue($user->hasHybridAuth());
        $this->assertTrue($user->canUnlinkProvider('google')); // Can unlink when password exists
    }

    public function test_profile_page_shows_oauth_status()
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

        $response = $this->actingAs($user)->get('/profile');

        $response->assertStatus(200);
        $response->assertSee('Connected as test@gmail.com');
        $response->assertSee('Gmail Only');
        $response->assertSee('Primary Login'); // Should show as primary login, not unlinkable
    }

    public function test_profile_page_shows_hybrid_auth_status()
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

        $response = $this->actingAs($user)->get('/profile');

        $response->assertStatus(200);
        $response->assertSee('Connected as test@gmail.com');
        $response->assertSee('Hybrid (Gmail + Password)');
        $response->assertSee('Unlink'); // Should show unlink button
    }

    /**
     * Mock Socialite driver and user
     */
    protected function mockSocialiteUser($id, $email, $name, $avatar)
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn($id);
        $socialiteUser->shouldReceive('getEmail')->andReturn($email);
        $socialiteUser->shouldReceive('getName')->andReturn($name);
        $socialiteUser->shouldReceive('getAvatar')->andReturn($avatar);

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn($socialiteUser);
        
        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn($driver);
            
        return $socialiteUser;
    }

    /**
     * Mock Socialite driver to throw exception
     */
    protected function mockSocialiteException($exception)
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andThrow($exception);
        
        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn($driver);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
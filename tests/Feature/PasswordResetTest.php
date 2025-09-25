<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AccountLockedNotification;
use App\Notifications\PasswordResetNotification;
use App\Notifications\SuspiciousPasswordResetNotification;
use App\Services\PasswordResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected PasswordResetService $passwordResetService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->passwordResetService = app(PasswordResetService::class);
        Notification::fake();
    }

    public function test_password_reset_request_form_is_displayed(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertStatus(200);
        $response->assertViewIs('auth.forgot-password');
    }

    public function test_password_reset_link_can_be_requested(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        
        Notification::assertSentTo($user, PasswordResetNotification::class);
    }

    public function test_password_reset_link_request_is_rate_limited(): void
    {
        $user = User::factory()->create();

        // Make 5 requests (the limit)
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => $user->email]);
        }

        // The 6th request should be rate limited
        $response = $this->post(route('password.email'), ['email' => $user->email]);

        $response->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('Too many password reset attempts', session('errors')->first('email'));
    }

    public function test_password_reset_request_for_nonexistent_email_returns_success(): void
    {
        $response = $this->post(route('password.email'), [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        
        // Should not send any notifications
        Notification::assertNothingSent();
    }

    public function test_password_reset_form_is_displayed(): void
    {
        $user = User::factory()->create();
        $token = 'test-token';

        $response = $this->get(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]));

        $response->assertStatus(200);
        $response->assertViewIs('auth.reset-password');
        $response->assertViewHas('token', $token);
        $response->assertViewHas('email', $user->email);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = 'test-token';
        
        // Create password reset token
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now(),
            'ip_address' => '127.0.0.1',
            'attempts' => 0,
        ]);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');
        
        // Verify password was changed
        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
        
        // Verify token was deleted
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    public function test_password_reset_fails_with_invalid_token(): void
    {
        $user = User::factory()->create();
        
        // Create password reset token
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make('valid-token'),
            'created_at' => now(),
            'ip_address' => '127.0.0.1',
            'attempts' => 0,
        ]);

        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('Invalid reset token', session('errors')->first('email'));
    }

    public function test_password_reset_fails_with_expired_token(): void
    {
        $user = User::factory()->create();
        $token = 'test-token';
        
        // Create expired password reset token (use a timestamp string format)
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now()->subMinutes(120)->toDateTimeString(), // 2 hours ago, definitely expired
            'ip_address' => '127.0.0.1',
            'attempts' => 0,
        ]);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('expired', session('errors')->first('email'));
    }

    public function test_password_reset_requires_2fa_for_admin_users(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            '2fa_enabled' => true,
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ]);
        
        $token = 'test-token';
        
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now(),
            'ip_address' => '127.0.0.1',
            'attempts' => 0,
        ]);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('Two-factor authentication code is required', session('errors')->first('email'));
    }

    public function test_account_lockout_after_failed_login_attempts(): void
    {
        $user = User::factory()->create();

        // Simulate 5 failed login attempts
        for ($i = 0; $i < 5; $i++) {
            $this->passwordResetService->handleFailedLogin($user, '127.0.0.1');
            $user->refresh();
        }

        $this->assertTrue($user->isAccountLocked());
        $this->assertNotNull($user->locked_until);
        $this->assertEquals(5, $user->failed_login_attempts);
        
        Notification::assertSentTo($user, AccountLockedNotification::class);
    }

    public function test_successful_login_resets_failed_attempts(): void
    {
        $user = User::factory()->create([
            'failed_login_attempts' => 3,
            'last_failed_login_at' => now(),
            'last_failed_login_ip' => '127.0.0.1',
        ]);

        $this->passwordResetService->handleSuccessfulLogin($user);
        $user->refresh();

        $this->assertEquals(0, $user->failed_login_attempts);
        $this->assertNull($user->last_failed_login_at);
        $this->assertNull($user->last_failed_login_ip);
    }

    public function test_suspicious_activity_notification_for_multiple_reset_requests(): void
    {
        $user = User::factory()->create();

        // Create activity log entries to simulate multiple recent requests
        for ($i = 0; $i < 3; $i++) {
            activity()
                ->causedBy($user)
                ->withProperties(['ip_address' => '192.168.1.' . ($i + 1)])
                ->log('Password reset requested');
        }

        $result = $this->passwordResetService->sendResetLink($user->email, '192.168.1.4');

        $this->assertTrue($result['success']);
        Notification::assertSentTo($user, SuspiciousPasswordResetNotification::class);
    }

    public function test_suspicious_activity_notification_for_new_ip_address(): void
    {
        $user = User::factory()->create();

        // Create a reset request from a known IP
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make('token'),
            'created_at' => now()->subDays(5),
            'ip_address' => '192.168.1.1',
        ]);

        // Request from a new IP should trigger suspicious activity
        $result = $this->passwordResetService->sendResetLink($user->email, '10.0.0.1');

        $this->assertTrue($result['success']);
        Notification::assertSentTo($user, SuspiciousPasswordResetNotification::class);
    }

    public function test_oauth_only_users_cannot_reset_password(): void
    {
        $user = User::factory()->create([
            'password' => null,
            'oauth_providers' => ['google' => ['id' => '123', 'email' => 'test@example.com']],
        ]);

        $this->assertFalse($user->canRequestPasswordReset());
    }

    public function test_locked_users_cannot_reset_password(): void
    {
        $user = User::factory()->create([
            'locked_until' => now()->addHours(1),
        ]);

        $this->assertFalse($user->canRequestPasswordReset());
    }

    public function test_password_reset_attempt_rate_limiting(): void
    {
        $user = User::factory()->create();
        $token = 'test-token';
        
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now(),
            'ip_address' => '127.0.0.1',
            'attempts' => 0,
        ]);

        // Make 10 failed attempts (the limit)
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('password.update'), [
                'token' => 'wrong-token',
                'email' => $user->email,
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ]);
        }

        // The 11th attempt should be rate limited
        $response = $this->post(route('password.update'), [
            'token' => 'wrong-token',
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('Too many reset attempts', session('errors')->first('email'));
    }

    public function test_progressive_account_lockout_duration(): void
    {
        $user = User::factory()->create();

        // First lockout (5 attempts) - should be 5 minutes
        for ($i = 0; $i < 5; $i++) {
            $this->passwordResetService->handleFailedLogin($user, '127.0.0.1');
            $user->refresh();
        }

        $firstLockDuration = $user->getRemainingLockTime();
        $this->assertGreaterThanOrEqual(4, $firstLockDuration); // Around 5 minutes

        // Reset and try again with more attempts
        $user->update(['failed_login_attempts' => 0, 'locked_until' => null]);

        // Second lockout (10 attempts) - should be longer
        for ($i = 0; $i < 10; $i++) {
            $this->passwordResetService->handleFailedLogin($user, '127.0.0.1');
            $user->refresh();
        }

        $secondLockDuration = $user->getRemainingLockTime();
        $this->assertGreaterThan($firstLockDuration, $secondLockDuration);
    }

    public function test_login_request_checks_account_lockout(): void
    {
        $user = User::factory()->create([
            'locked_until' => now()->addMinutes(30),
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('temporarily locked', session('errors')->first('email'));
    }

    public function test_password_reset_token_cleanup_after_too_many_attempts(): void
    {
        $user = User::factory()->create();
        $token = 'test-token';
        
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now(),
            'ip_address' => '127.0.0.1',
            'attempts' => 4, // One less than the limit
        ]);

        // This should be the 5th attempt, triggering cleanup
        $response = $this->post(route('password.update'), [
            'token' => 'wrong-token',
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('Too many failed attempts', session('errors')->first('email'));
        
        // Token should be deleted
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);
        
        // Should notify of suspicious activity
        Notification::assertSentTo($user, SuspiciousPasswordResetNotification::class);
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('password-reset:127.0.0.1');
        RateLimiter::clear('password-reset-attempt:127.0.0.1');
        parent::tearDown();
    }
}

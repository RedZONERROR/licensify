<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PasswordResetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PasswordResetService();
        Notification::fake();
    }

    public function test_is_account_locked_returns_true_for_locked_account(): void
    {
        $user = User::factory()->create([
            'locked_until' => now()->addMinutes(30),
        ]);

        $this->assertTrue($this->service->isAccountLocked($user));
    }

    public function test_is_account_locked_returns_false_for_unlocked_account(): void
    {
        $user = User::factory()->create([
            'locked_until' => null,
        ]);

        $this->assertFalse($this->service->isAccountLocked($user));
    }

    public function test_is_account_locked_returns_false_for_expired_lock(): void
    {
        $user = User::factory()->create([
            'locked_until' => now()->subMinutes(30),
        ]);

        $this->assertFalse($this->service->isAccountLocked($user));
    }

    public function test_get_remaining_lock_time_returns_correct_minutes(): void
    {
        $user = User::factory()->create([
            'locked_until' => now()->addMinutes(30),
        ]);

        $remainingTime = $this->service->getRemainingLockTime($user);
        
        $this->assertGreaterThanOrEqual(29, $remainingTime);
        $this->assertLessThanOrEqual(30, $remainingTime);
    }

    public function test_get_remaining_lock_time_returns_null_for_unlocked_account(): void
    {
        $user = User::factory()->create([
            'locked_until' => null,
        ]);

        $this->assertNull($this->service->getRemainingLockTime($user));
    }

    public function test_handle_failed_login_increments_attempts(): void
    {
        $user = User::factory()->create([
            'failed_login_attempts' => 2,
        ]);

        $this->service->handleFailedLogin($user, '127.0.0.1');
        $user->refresh();

        $this->assertEquals(3, $user->failed_login_attempts);
        $this->assertNotNull($user->last_failed_login_at);
        $this->assertEquals('127.0.0.1', $user->last_failed_login_ip);
    }

    public function test_handle_failed_login_locks_account_after_five_attempts(): void
    {
        $user = User::factory()->create([
            'failed_login_attempts' => 4,
        ]);

        $this->service->handleFailedLogin($user, '127.0.0.1');
        $user->refresh();

        $this->assertEquals(5, $user->failed_login_attempts);
        $this->assertNotNull($user->locked_until);
        $this->assertTrue($user->isAccountLocked());
    }

    public function test_handle_successful_login_resets_failed_attempts(): void
    {
        $user = User::factory()->create([
            'failed_login_attempts' => 3,
            'locked_until' => now()->addMinutes(30),
            'last_failed_login_at' => now(),
            'last_failed_login_ip' => '127.0.0.1',
        ]);

        $this->service->handleSuccessfulLogin($user);
        $user->refresh();

        $this->assertEquals(0, $user->failed_login_attempts);
        $this->assertNull($user->locked_until);
        $this->assertNull($user->last_failed_login_at);
        $this->assertNull($user->last_failed_login_ip);
    }

    public function test_calculate_lock_duration_increases_progressively(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateLockDuration');
        $method->setAccessible(true);

        $duration5 = $method->invoke($this->service, 5);
        $duration6 = $method->invoke($this->service, 6);
        $duration7 = $method->invoke($this->service, 7);

        $this->assertEquals(5, $duration5);  // 5 minutes
        $this->assertEquals(10, $duration6); // 10 minutes
        $this->assertEquals(20, $duration7); // 20 minutes
    }

    public function test_generate_secure_token_returns_valid_hash(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateSecureToken');
        $method->setAccessible(true);

        $token1 = $method->invoke($this->service);
        $token2 = $method->invoke($this->service);

        $this->assertIsString($token1);
        $this->assertIsString($token2);
        $this->assertNotEquals($token1, $token2);
        $this->assertEquals(64, strlen($token1)); // SHA256 hash length
    }

    public function test_send_reset_link_returns_success_for_valid_user(): void
    {
        $user = User::factory()->create();

        $result = $this->service->sendResetLink($user->email, '127.0.0.1');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('sent a password reset link', $result['message']);
    }

    public function test_send_reset_link_returns_success_for_invalid_user(): void
    {
        $result = $this->service->sendResetLink('nonexistent@example.com', '127.0.0.1');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('sent a password reset link', $result['message']);
    }

    public function test_reset_password_fails_for_nonexistent_user(): void
    {
        $result = $this->service->resetPassword(
            'nonexistent@example.com',
            'token',
            'password',
            null,
            '127.0.0.1'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid reset token', $result['message']);
    }

    public function test_reset_password_fails_for_locked_user(): void
    {
        $user = User::factory()->create([
            'locked_until' => now()->addMinutes(30),
        ]);

        // Create a valid reset token first
        $token = 'test-token';
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now(),
            'ip_address' => '127.0.0.1',
            'attempts' => 0,
        ]);

        $result = $this->service->resetPassword(
            $user->email,
            $token,
            'password',
            null,
            '127.0.0.1'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('temporarily locked', $result['message']);
    }
}

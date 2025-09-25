<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PasswordResetNotification;
use App\Notifications\SuspiciousPasswordResetNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class PasswordResetService
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Send password reset link to user
     */
    public function sendResetLink(string $email, string $ipAddress): array
    {
        $user = User::where('email', $email)->first();
        
        // Always return success message to prevent email enumeration
        $successMessage = 'If an account with that email exists, we have sent a password reset link.';
        
        if (!$user) {
            return [
                'success' => true,
                'message' => $successMessage
            ];
        }

        // Check for suspicious activity
        $this->checkSuspiciousActivity($user, $ipAddress);

        // Generate signed token
        $token = $this->generateSecureToken();
        $expires = now()->addMinutes(config('auth.passwords.users.expire', 60));

        // Store reset token (increment attempts if record exists)
        $existingRecord = DB::table('password_reset_tokens')->where('email', $email)->first();
        $attempts = $existingRecord ? $existingRecord->attempts + 1 : 1;

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
                'ip_address' => $ipAddress,
                'attempts' => $attempts,
                'last_attempt_at' => now(),
            ]
        );

        // Send reset email
        $user->notify(new PasswordResetNotification($token, $expires));

        // Log the password reset request
        activity()
            ->causedBy($user)
            ->withProperties([
                'ip_address' => $ipAddress,
                'user_agent' => request()->userAgent(),
            ])
            ->log('Password reset requested');

        return [
            'success' => true,
            'message' => $successMessage
        ];
    }

    /**
     * Reset user password
     */
    public function resetPassword(string $email, string $token, string $password, ?string $totpCode, string $ipAddress): array
    {
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Invalid reset token or email address.'
            ];
        }

        // Get reset token record
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$resetRecord) {
            return [
                'success' => false,
                'message' => 'Invalid or expired reset token.'
            ];
        }

        // Check if token is expired
        $expiryMinutes = config('auth.passwords.users.expire', 60);
        $tokenCreatedAt = \Carbon\Carbon::parse($resetRecord->created_at);
        $expiryTime = $tokenCreatedAt->addMinutes($expiryMinutes);
        
        if (now()->gt($expiryTime)) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return [
                'success' => false,
                'message' => 'Reset token has expired. Please request a new one.'
            ];
        }

        // Verify token
        if (!Hash::check($token, $resetRecord->token)) {
            // Increment attempts
            DB::table('password_reset_tokens')
                ->where('email', $email)
                ->increment('attempts');

            // Check for too many attempts
            if ($resetRecord->attempts >= 5) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();
                $this->notifySuspiciousActivity($user, $ipAddress, 'Too many failed reset attempts');
                
                return [
                    'success' => false,
                    'message' => 'Too many failed attempts. Please request a new reset link.'
                ];
            }

            return [
                'success' => false,
                'message' => 'Invalid reset token.'
            ];
        }

        // Check if user requires 2FA for password reset
        if ($user->requires2FA() && $user->hasTwoFactorEnabled()) {
            if (!$totpCode) {
                return [
                    'success' => false,
                    'message' => 'Two-factor authentication code is required for password reset.',
                    'requires_2fa' => true
                ];
            }

            // Verify TOTP code
            $secret = $user->get2FASecret();
            if (!$secret || !$this->google2fa->verifyKey($secret, $totpCode)) {
                return [
                    'success' => false,
                    'message' => 'Invalid two-factor authentication code.'
                ];
            }
        }

        // Check if user is locked
        if ($user->locked_until && now()->lt($user->locked_until)) {
            return [
                'success' => false,
                'message' => 'Account is temporarily locked. Please try again later.'
            ];
        }

        // Reset password
        $user->update([
            'password' => Hash::make($password),
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_failed_login_at' => null,
            'last_failed_login_ip' => null,
        ]);

        // Delete reset token
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // Log successful password reset
        activity()
            ->causedBy($user)
            ->withProperties([
                'ip_address' => $ipAddress,
                'user_agent' => request()->userAgent(),
            ])
            ->log('Password reset completed');

        return [
            'success' => true,
            'message' => 'Your password has been reset successfully. You can now log in with your new password.'
        ];
    }

    /**
     * Check for suspicious password reset activity
     */
    protected function checkSuspiciousActivity(User $user, string $ipAddress): void
    {
        // Get existing reset token record
        $existingRecord = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->first();

        if ($existingRecord) {
            // Check if there have been multiple recent requests (based on attempts or recent creation)
            if ($existingRecord->created_at > now()->subHours(1)->toDateTimeString() && 
                $existingRecord->attempts >= 2) {
                $this->notifySuspiciousActivity($user, $ipAddress, 'Multiple password reset requests in short time');
            }

            // Check for reset requests from new IP addresses
            if ($existingRecord->ip_address && $existingRecord->ip_address !== $ipAddress) {
                $this->notifySuspiciousActivity($user, $ipAddress, 'Password reset from new IP address');
            }
        }

        // Check activity logs for recent password reset requests
        $recentResetLogs = DB::table('activity_log')
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('description', 'Password reset requested')
            ->where('created_at', '>', now()->subHours(1))
            ->count();

        if ($recentResetLogs >= 3) {
            $this->notifySuspiciousActivity($user, $ipAddress, 'Multiple password reset requests detected');
        }
    }

    /**
     * Notify user of suspicious password reset activity
     */
    protected function notifySuspiciousActivity(User $user, string $ipAddress, string $reason): void
    {
        try {
            $user->notify(new SuspiciousPasswordResetNotification($ipAddress, $reason));
            
            // Log the suspicious activity
            activity()
                ->causedBy($user)
                ->withProperties([
                    'ip_address' => $ipAddress,
                    'reason' => $reason,
                    'user_agent' => request()->userAgent(),
                ])
                ->log('Suspicious password reset activity detected');
        } catch (\Exception $e) {
            logger()->error('Failed to send suspicious activity notification', [
                'user_id' => $user->id,
                'ip_address' => $ipAddress,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate a cryptographically secure token
     */
    protected function generateSecureToken(): string
    {
        return hash_hmac('sha256', Str::random(40), config('app.key'));
    }

    /**
     * Handle account lockout after failed login attempts
     */
    public function handleFailedLogin(User $user, string $ipAddress): void
    {
        $user->increment('failed_login_attempts');
        $user->update([
            'last_failed_login_at' => now(),
            'last_failed_login_ip' => $ipAddress,
        ]);

        // Lock account after 5 failed attempts
        if ($user->failed_login_attempts >= 5) {
            $lockDuration = $this->calculateLockDuration($user->failed_login_attempts);
            $user->update([
                'locked_until' => now()->addMinutes($lockDuration),
            ]);

            // Notify user of account lockout
            try {
                $user->notify(new \App\Notifications\AccountLockedNotification($lockDuration));
            } catch (\Exception $e) {
                logger()->error('Failed to send account locked notification', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Log the lockout
            activity()
                ->causedBy($user)
                ->withProperties([
                    'ip_address' => $ipAddress,
                    'failed_attempts' => $user->failed_login_attempts,
                    'lock_duration_minutes' => $lockDuration,
                ])
                ->log('Account locked due to failed login attempts');
        }
    }

    /**
     * Handle successful login (reset failed attempts)
     */
    public function handleSuccessfulLogin(User $user): void
    {
        if ($user->failed_login_attempts > 0 || $user->locked_until) {
            $user->update([
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'last_failed_login_at' => null,
                'last_failed_login_ip' => null,
            ]);
        }
    }

    /**
     * Calculate lock duration based on failed attempts
     */
    protected function calculateLockDuration(int $attempts): int
    {
        // Progressive lockout: 5 min, 15 min, 30 min, 1 hour, 2 hours, etc.
        $baseDuration = 5; // 5 minutes
        $multiplier = min($attempts - 4, 8); // Cap at 8x multiplier
        
        return $baseDuration * pow(2, $multiplier - 1);
    }

    /**
     * Check if user account is currently locked
     */
    public function isAccountLocked(User $user): bool
    {
        return $user->locked_until && now()->lt($user->locked_until);
    }

    /**
     * Get remaining lock time in minutes
     */
    public function getRemainingLockTime(User $user): ?int
    {
        if (!$this->isAccountLocked($user)) {
            return null;
        }

        return now()->diffInMinutes($user->locked_until);
    }
}
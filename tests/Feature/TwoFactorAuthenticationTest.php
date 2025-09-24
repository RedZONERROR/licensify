<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected Google2FA $google2fa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->google2fa = new Google2FA();
    }

    public function test_user_can_view_two_factor_setup_page()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/two-factor');

        $response->assertStatus(200);
        $response->assertViewIs('auth.two-factor.setup');
        $response->assertViewHas(['secret', 'qrCode', 'user']);
    }

    public function test_user_can_enable_two_factor_authentication()
    {
        $user = User::factory()->create();
        $secret = $this->google2fa->generateSecretKey();
        $code = $this->google2fa->getCurrentOtp($secret);

        $response = $this->actingAs($user)->post('/two-factor', [
            'secret' => $secret,
            'code' => $code
        ]);

        $response->assertRedirect('/two-factor');
        $response->assertSessionHas('status', '2FA has been enabled successfully!');
        $response->assertSessionHas('recoveryCodes');

        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNotNull($user->two_factor_confirmed_at);
    }

    public function test_user_cannot_enable_two_factor_with_invalid_code()
    {
        $user = User::factory()->create();
        $secret = $this->google2fa->generateSecretKey();

        $response = $this->actingAs($user)->post('/two-factor', [
            'secret' => $secret,
            'code' => '000000' // Invalid code
        ]);

        $response->assertSessionHasErrors(['code']);
        
        $user->refresh();
        $this->assertFalse($user->hasTwoFactorEnabled());
    }

    public function test_user_with_enabled_2fa_sees_management_page()
    {
        $user = User::factory()->create();
        $secret = $this->google2fa->generateSecretKey();
        
        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            '2fa_enabled' => true
        ]);
        $user->generateRecoveryCodes();

        $response = $this->actingAs($user)->get('/two-factor');

        $response->assertStatus(200);
        $response->assertViewIs('auth.two-factor.manage');
        $response->assertViewHas(['user', 'recoveryCodes']);
    }

    public function test_user_can_disable_two_factor_authentication()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password')
        ]);
        
        $secret = $this->google2fa->generateSecretKey();
        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            '2fa_enabled' => true
        ]);

        $response = $this->actingAs($user)->delete('/two-factor', [
            'password' => 'password'
        ]);

        $response->assertRedirect('/two-factor');
        $response->assertSessionHas('status', '2FA has been disabled successfully!');

        $user->refresh();
        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_user_cannot_disable_2fa_with_wrong_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password')
        ]);
        
        $secret = $this->google2fa->generateSecretKey();
        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            '2fa_enabled' => true
        ]);

        $response = $this->actingAs($user)->delete('/two-factor', [
            'password' => 'wrong-password'
        ]);

        $response->assertSessionHasErrors(['password']);
        
        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
    }

    public function test_user_can_view_recovery_codes()
    {
        $user = User::factory()->create();
        $secret = $this->google2fa->generateSecretKey();
        
        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            '2fa_enabled' => true
        ]);
        $recoveryCodes = $user->generateRecoveryCodes();

        $response = $this->actingAs($user)->get('/two-factor/recovery-codes');

        $response->assertStatus(200);
        $response->assertViewIs('auth.two-factor.recovery-codes');
        $response->assertViewHas('recoveryCodes', $recoveryCodes);
    }

    public function test_user_can_regenerate_recovery_codes()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password')
        ]);
        
        $secret = $this->google2fa->generateSecretKey();
        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            '2fa_enabled' => true
        ]);
        $oldCodes = $user->generateRecoveryCodes();

        $response = $this->actingAs($user)->post('/two-factor/recovery-codes', [
            'password' => 'password'
        ]);

        $response->assertRedirect('/two-factor/recovery-codes');
        $response->assertSessionHas('status', 'Recovery codes have been regenerated!');
        $response->assertSessionHas('recoveryCodes');

        $user->refresh();
        $newCodes = $user->getRecoveryCodes();
        $this->assertNotEquals($oldCodes, $newCodes);
        $this->assertCount(8, $newCodes);
    }

    public function test_admin_user_requires_2fa()
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->assertTrue($user->requires2FA());
    }

    public function test_developer_user_requires_2fa()
    {
        $user = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->assertTrue($user->requires2FA());
    }

    public function test_regular_user_does_not_require_2fa()
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $this->assertFalse($user->requires2FA());
    }

    public function test_reseller_user_does_not_require_2fa()
    {
        $user = User::factory()->create(['role' => User::ROLE_RESELLER]);
        $this->assertFalse($user->requires2FA());
    }

    public function test_recovery_code_can_be_used_once()
    {
        $user = User::factory()->create();
        $secret = $this->google2fa->generateSecretKey();
        
        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            '2fa_enabled' => true
        ]);
        $recoveryCodes = $user->generateRecoveryCodes();
        $codeToUse = $recoveryCodes[0];

        // First use should succeed
        $this->assertTrue($user->useRecoveryCode($codeToUse));
        
        // Second use should fail
        $this->assertFalse($user->useRecoveryCode($codeToUse));
        
        // Code should be removed from available codes
        $remainingCodes = $user->getRecoveryCodes();
        $this->assertNotContains($codeToUse, $remainingCodes);
        $this->assertCount(7, $remainingCodes);
    }

    public function test_two_factor_challenge_page_is_accessible()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/two-factor/challenge');

        $response->assertStatus(200);
        $response->assertViewIs('auth.two-factor.challenge');
    }

    public function test_user_can_verify_with_totp_code()
    {
        $user = User::factory()->create();
        $secret = $this->google2fa->generateSecretKey();
        
        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            '2fa_enabled' => true
        ]);

        $code = $this->google2fa->getCurrentOtp($secret);

        $response = $this->actingAs($user)->post('/two-factor/challenge', [
            'code' => $code
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertTrue(session('2fa_verified'));
        $this->assertNotNull(session('2fa_verified_at'));
    }

    public function test_user_can_verify_with_recovery_code()
    {
        $user = User::factory()->create();
        $secret = $this->google2fa->generateSecretKey();
        
        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            '2fa_enabled' => true
        ]);
        $recoveryCodes = $user->generateRecoveryCodes();

        $response = $this->actingAs($user)->post('/two-factor/challenge', [
            'code' => $recoveryCodes[0],
            'recovery' => true
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertTrue(session('2fa_verified'));
        $this->assertNotNull(session('2fa_verified_at'));
        
        // Recovery code should be consumed
        $user->refresh();
        $remainingCodes = $user->getRecoveryCodes();
        $this->assertCount(7, $remainingCodes);
    }

    public function test_user_cannot_verify_with_invalid_totp_code()
    {
        $user = User::factory()->create();
        $secret = $this->google2fa->generateSecretKey();
        
        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            '2fa_enabled' => true
        ]);

        $response = $this->actingAs($user)->post('/two-factor/challenge', [
            'code' => '000000'
        ]);

        $response->assertSessionHasErrors(['code']);
        $this->assertFalse(session('2fa_verified', false));
    }

    public function test_user_cannot_verify_with_invalid_recovery_code()
    {
        $user = User::factory()->create();
        $secret = $this->google2fa->generateSecretKey();
        
        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            '2fa_enabled' => true
        ]);
        $user->generateRecoveryCodes();

        $response = $this->actingAs($user)->post('/two-factor/challenge', [
            'code' => 'INVALID1',
            'recovery' => true
        ]);

        $response->assertSessionHasErrors(['code']);
        $this->assertFalse(session('2fa_verified', false));
    }
}
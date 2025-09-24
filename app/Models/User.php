<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use PragmaRX\Google2FALaravel\Support\Authenticator;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'avatar',
        'role',
        '2fa_enabled',
        '2fa_secret',
        'privacy_policy_accepted_at',
        'developer_notes',
        'reseller_id',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'oauth_providers',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        '2fa_secret',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];



    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'privacy_policy_accepted_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            '2fa_enabled' => 'boolean',
            'password' => 'hashed',
            'oauth_providers' => 'json',
            'role' => Role::class,
        ];
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(Role|string $role): bool
    {
        if (is_string($role)) {
            $role = Role::from($role);
        }
        return $this->role === $role;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN);
    }

    /**
     * Check if user is developer
     */
    public function isDeveloper(): bool
    {
        return $this->hasRole(Role::DEVELOPER);
    }

    /**
     * Check if user is reseller
     */
    public function isReseller(): bool
    {
        return $this->hasRole(Role::RESELLER);
    }

    /**
     * Check if user is regular user
     */
    public function isUser(): bool
    {
        return $this->hasRole(Role::USER);
    }

    /**
     * Check if user requires 2FA for sensitive operations
     */
    public function requires2FA(): bool
    {
        return $this->role->requires2FA();
    }

    /**
     * Check if user has permission level equal or higher than given role
     */
    public function hasPermissionLevel(Role|string $role): bool
    {
        if (is_string($role)) {
            $role = Role::from($role);
        }
        return $this->role->hasPermissionLevel($role);
    }

    /**
     * Check if user can manage another user based on role hierarchy
     */
    public function canManageUser(User $user): bool
    {
        return in_array($user->role, $this->role->canManageRoles());
    }

    /**
     * Get user permissions based on role
     */
    public function getPermissions(): array
    {
        return $this->role->permissions();
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->getPermissions());
    }

    /**
     * Get the reseller that manages this user
     */
    public function reseller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }

    /**
     * Get users managed by this reseller
     */
    public function managedUsers(): HasMany
    {
        return $this->hasMany(User::class, 'reseller_id');
    }

    /**
     * Get licenses owned by this user
     */
    public function ownedLicenses(): HasMany
    {
        return $this->hasMany(License::class, 'owner_id');
    }

    /**
     * Get licenses assigned to this user
     */
    public function assignedLicenses(): HasMany
    {
        return $this->hasMany(License::class, 'user_id');
    }

    /**
     * Get chat messages sent by this user
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'sender_id');
    }

    /**
     * Get chat messages received by this user
     */
    public function receivedMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'receiver_id');
    }

    /**
     * Get audit logs created by this user
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }

    /**
     * Get backups created by this user
     */
    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class, 'created_by');
    }

    /**
     * Set 2FA secret (encrypted)
     */
    public function set2FASecret(string $secret): void
    {
        $this->update(['2fa_secret' => encrypt($secret)]);
    }

    /**
     * Get 2FA secret (decrypted)
     */
    public function get2FASecret(): ?string
    {
        return $this->{'2fa_secret'} ? decrypt($this->{'2fa_secret'}) : null;
    }

    /**
     * Enable 2FA for this user
     */
    public function enable2FA(string $secret): void
    {
        $this->update([
            '2fa_enabled' => true,
            '2fa_secret' => encrypt($secret)
        ]);
    }

    /**
     * Disable 2FA for this user
     */
    public function disable2FA(): void
    {
        $this->update([
            '2fa_enabled' => false,
            '2fa_secret' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    /**
     * Check if user has 2FA enabled
     */
    public function hasTwoFactorEnabled(): bool
    {
        return !is_null($this->two_factor_secret) && !is_null($this->two_factor_confirmed_at);
    }

    /**
     * Generate 2FA recovery codes
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
        }
        
        $this->update([
            'two_factor_recovery_codes' => encrypt(json_encode($codes))
        ]);
        
        return $codes;
    }

    /**
     * Get recovery codes
     */
    public function getRecoveryCodes(): array
    {
        if (!$this->two_factor_recovery_codes) {
            return [];
        }
        
        return json_decode(decrypt($this->two_factor_recovery_codes), true) ?? [];
    }

    /**
     * Use a recovery code
     */
    public function useRecoveryCode(string $code): bool
    {
        $codes = $this->getRecoveryCodes();
        $key = array_search(strtoupper($code), $codes);
        
        if ($key !== false) {
            unset($codes[$key]);
            $this->update([
                'two_factor_recovery_codes' => encrypt(json_encode(array_values($codes)))
            ]);
            return true;
        }
        
        return false;
    }

    /**
     * Confirm 2FA setup
     */
    public function confirmTwoFactor(): void
    {
        $this->update([
            'two_factor_confirmed_at' => now(),
            '2fa_enabled' => true,
        ]);
    }

    /**
     * Check if user has OAuth provider linked
     */
    public function hasOAuthProvider(string $provider): bool
    {
        return isset($this->oauth_providers[$provider]);
    }

    /**
     * Get OAuth provider data
     */
    public function getOAuthProvider(string $provider): ?array
    {
        return $this->oauth_providers[$provider] ?? null;
    }

    /**
     * Check if user is OAuth-only (no password set)
     */
    public function isOAuthOnly(): bool
    {
        return is_null($this->password) && !empty($this->oauth_providers);
    }

    /**
     * Check if user has hybrid authentication (OAuth + password)
     */
    public function hasHybridAuth(): bool
    {
        return !is_null($this->password) && !empty($this->oauth_providers);
    }

    /**
     * Get all linked OAuth providers
     */
    public function getLinkedProviders(): array
    {
        return array_keys($this->oauth_providers ?? []);
    }

    /**
     * Check if user can unlink a specific OAuth provider
     */
    public function canUnlinkProvider(string $provider): bool
    {
        // Can't unlink if it's the only authentication method
        if ($this->isOAuthOnly() && count($this->oauth_providers) === 1) {
            return false;
        }

        return $this->hasOAuthProvider($provider);
    }
}
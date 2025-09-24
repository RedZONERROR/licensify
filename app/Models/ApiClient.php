<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ApiClient extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'api_key_hash',
        'secret_hash',
        'user_id',
        'scopes',
        'is_active',
        'rate_limit',
        'last_used_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'api_key_hash',
        'secret_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scopes' => 'json',
            'is_active' => 'boolean',
            'rate_limit' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Available API scopes
     */
    public const SCOPE_LICENSE_VALIDATE = 'license:validate';
    public const SCOPE_LICENSE_READ = 'license:read';
    public const SCOPE_LICENSE_WRITE = 'license:write';
    public const SCOPE_USER_READ = 'user:read';
    public const SCOPE_BACKUP_TRIGGER = 'backup:trigger';

    /**
     * Get the user that owns this API client
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get API requests made by this client
     */
    public function requests(): HasMany
    {
        return $this->hasMany(ApiRequest::class);
    }

    /**
     * Generate a new API key and secret
     */
    public static function generateCredentials(): array
    {
        $apiKey = 'lms_' . Str::random(32);
        $secret = Str::random(64);

        return [
            'api_key' => $apiKey,
            'secret' => $secret,
            'api_key_hash' => hash('sha256', $apiKey),
            'secret_hash' => $secret, // Store actual secret for HMAC (should be encrypted in production)
        ];
    }

    /**
     * Verify API key
     */
    public static function findByApiKey(string $apiKey): ?self
    {
        $hash = hash('sha256', $apiKey);
        return self::where('api_key_hash', $hash)
                   ->where('is_active', true)
                   ->first();
    }

    /**
     * Verify HMAC signature
     */
    public function verifySignature(string $method, string $uri, string $body, string $timestamp, string $nonce, string $signature): bool
    {
        // Reconstruct the string to sign
        $stringToSign = strtoupper($method) . "\n" . 
                       $uri . "\n" . 
                       $body . "\n" . 
                       $timestamp . "\n" . 
                       $nonce;

        // Get the secret for HMAC
        $secret = $this->getSecret();
        if (!$secret) {
            return false;
        }

        // Calculate expected signature
        $expectedSignature = hash_hmac('sha256', $stringToSign, $secret);

        // Use hash_equals for timing-safe comparison
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Get the secret for this API client (for internal use only)
     */
    private function getSecret(): ?string
    {
        // Note: In production, you should store the actual secret encrypted
        // and decrypt it here. For this implementation, we'll use the hash
        // as the secret since we need the original secret for HMAC verification
        return $this->secret_hash;
    }

    /**
     * Set the secret for HMAC verification
     */
    public function setSecret(string $secret): void
    {
        $this->secret_hash = $secret; // Store the actual secret (should be encrypted in production)
    }

    /**
     * Check if client has a specific scope
     */
    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? []);
    }

    /**
     * Update last used timestamp
     */
    public function updateLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Check if client is within rate limit
     */
    public function isWithinRateLimit(): bool
    {
        $hourAgo = now()->subHour();
        $requestCount = $this->requests()
                            ->where('request_timestamp', '>=', $hourAgo)
                            ->count();

        return $requestCount < $this->rate_limit;
    }

    /**
     * Get current rate limit usage
     */
    public function getRateLimitUsage(): array
    {
        $hourAgo = now()->subHour();
        $requestCount = $this->requests()
                            ->where('request_timestamp', '>=', $hourAgo)
                            ->count();

        return [
            'used' => $requestCount,
            'limit' => $this->rate_limit,
            'remaining' => max(0, $this->rate_limit - $requestCount),
            'reset_at' => now()->addHour()->startOfHour(),
        ];
    }

    /**
     * Scope for active clients
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for clients owned by user
     */
    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}

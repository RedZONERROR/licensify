<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiRequest extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'api_client_id',
        'endpoint',
        'method',
        'ip_address',
        'user_agent',
        'request_data',
        'response_data',
        'response_status',
        'response_time',
        'nonce',
        'request_timestamp',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_data' => 'json',
            'response_data' => 'json',
            'response_status' => 'integer',
            'response_time' => 'decimal:3',
            'request_timestamp' => 'datetime',
        ];
    }

    /**
     * Get the API client that made this request
     */
    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }

    /**
     * Log an API request
     */
    public static function logRequest(
        ?ApiClient $apiClient,
        string $endpoint,
        string $method,
        string $ipAddress,
        ?string $userAgent,
        ?array $requestData,
        ?array $responseData,
        int $responseStatus,
        float $responseTime,
        ?string $nonce = null
    ): self {
        return self::create([
            'api_client_id' => $apiClient?->id,
            'endpoint' => $endpoint,
            'method' => $method,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'request_data' => $requestData,
            'response_data' => $responseData,
            'response_status' => $responseStatus,
            'response_time' => $responseTime,
            'nonce' => $nonce,
            'request_timestamp' => now(),
        ]);
    }

    /**
     * Scope for requests by API client
     */
    public function scopeByClient($query, int $clientId)
    {
        return $query->where('api_client_id', $clientId);
    }

    /**
     * Scope for requests within time range
     */
    public function scopeWithinTimeRange($query, $start, $end)
    {
        return $query->whereBetween('request_timestamp', [$start, $end]);
    }

    /**
     * Scope for successful requests
     */
    public function scopeSuccessful($query)
    {
        return $query->whereBetween('response_status', [200, 299]);
    }

    /**
     * Scope for failed requests
     */
    public function scopeFailed($query)
    {
        return $query->where('response_status', '>=', 400);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $scope = null): Response
    {
        $startTime = microtime(true);

        // Try JWT authentication first
        if ($this->attemptJwtAuth($request)) {
            $request->attributes->set('auth_method', 'jwt');
            return $next($request);
        }

        // Try API Key + HMAC authentication
        if ($this->attemptApiKeyAuth($request, $scope)) {
            $request->attributes->set('auth_method', 'api_key');
            return $next($request);
        }

        // Log failed authentication attempt
        $this->logFailedRequest($request, $startTime);

        return response()->json([
            'error' => 'Unauthorized',
            'message' => 'Invalid authentication credentials',
            'code' => 'AUTH_FAILED'
        ], 401);
    }

    /**
     * Attempt JWT authentication using Sanctum
     */
    private function attemptJwtAuth(Request $request): bool
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return false;
        }

        try {
            $accessToken = PersonalAccessToken::findToken($token);
            
            if (!$accessToken || !$accessToken->tokenable) {
                return false;
            }

            // Set the authenticated user
            Auth::setUser($accessToken->tokenable);
            $request->attributes->set('api_client', null);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Attempt API Key + HMAC authentication
     */
    private function attemptApiKeyAuth(Request $request, ?string $requiredScope = null): bool
    {
        $apiKey = $request->header('X-API-KEY');
        $signature = $request->header('X-SIGNATURE');
        $timestamp = $request->header('X-TIMESTAMP');
        $nonce = $request->header('X-NONCE');

        if (!$apiKey || !$signature || !$timestamp || !$nonce) {
            return false;
        }

        // Validate timestamp (must be within 5 minutes)
        $requestTime = (int) $timestamp;
        $currentTime = time();
        if (abs($currentTime - $requestTime) > 300) { // 5 minutes
            return false;
        }

        // Find API client
        $apiClient = ApiClient::findByApiKey($apiKey);
        if (!$apiClient) {
            return false;
        }

        // Check if client is active
        if (!$apiClient->is_active) {
            return false;
        }

        // Verify signature
        $method = $request->method();
        $uri = $request->getRequestUri();
        $body = $request->getContent();

        if (!$apiClient->verifySignature($method, $uri, $body, $timestamp, $nonce, $signature)) {
            return false;
        }

        // Check scope if required
        if ($requiredScope && !$apiClient->hasScope($requiredScope)) {
            return false;
        }

        // Check nonce uniqueness (prevent replay attacks)
        if (!$this->isNonceUnique($apiClient, $nonce, $timestamp)) {
            return false;
        }

        // Set authenticated user and API client
        Auth::setUser($apiClient->user);
        $request->attributes->set('api_client', $apiClient);
        $request->attributes->set('nonce', $nonce);

        // Update last used timestamp
        $apiClient->updateLastUsed();

        return true;
    }

    /**
     * Check if nonce is unique within the timestamp window
     */
    private function isNonceUnique(ApiClient $apiClient, string $nonce, string $timestamp): bool
    {
        // Check if this nonce has been used within the last 5 minutes
        $fiveMinutesAgo = now()->subMinutes(5);
        
        return !$apiClient->requests()
            ->where('nonce', $nonce)
            ->where('request_timestamp', '>=', $fiveMinutesAgo)
            ->exists();
    }

    /**
     * Log failed authentication request
     */
    private function logFailedRequest(Request $request, float $startTime): void
    {
        $responseTime = (microtime(true) - $startTime) * 1000;

        \App\Models\ApiRequest::logRequest(
            null,
            $request->path(),
            $request->method(),
            $request->ip(),
            $request->userAgent(),
            $request->all(),
            ['error' => 'Unauthorized', 'message' => 'Invalid authentication credentials'],
            401,
            $responseTime
        );
    }
}

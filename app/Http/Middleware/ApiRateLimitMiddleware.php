<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimitMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $apiClient = $request->attributes->get('api_client');

        // If no API client (JWT auth), use default rate limiting
        if (!$apiClient) {
            return $this->handleJwtRateLimit($request, $next, $startTime);
        }

        // Check API client rate limit
        if (!$apiClient->isWithinRateLimit()) {
            return $this->rateLimitExceeded($request, $apiClient, $startTime);
        }

        // Process request
        $response = $next($request);

        // Add rate limit headers
        $this->addRateLimitHeaders($response, $apiClient);

        return $response;
    }

    /**
     * Handle rate limiting for JWT authenticated requests
     */
    private function handleJwtRateLimit(Request $request, Closure $next, float $startTime): Response
    {
        // For JWT, we'll use a simple IP-based rate limiting
        $key = 'jwt_rate_limit:' . $request->ip();
        $limit = 1000; // 1000 requests per hour for JWT
        $window = 3600; // 1 hour in seconds

        $current = cache()->get($key, 0);
        
        if ($current >= $limit) {
            return $this->jwtRateLimitExceeded($request, $startTime, $limit);
        }

        // Increment counter
        cache()->put($key, $current + 1, $window);

        $response = $next($request);

        // Add rate limit headers for JWT
        $response->headers->set('X-RateLimit-Limit', $limit);
        $response->headers->set('X-RateLimit-Remaining', max(0, $limit - $current - 1));
        $response->headers->set('X-RateLimit-Reset', now()->addSeconds($window)->timestamp);

        return $response;
    }

    /**
     * Handle rate limit exceeded for API client
     */
    private function rateLimitExceeded(Request $request, ApiClient $apiClient, float $startTime): Response
    {
        $responseTime = (microtime(true) - $startTime) * 1000;
        $usage = $apiClient->getRateLimitUsage();

        // Log the rate limit exceeded request
        \App\Models\ApiRequest::logRequest(
            $apiClient,
            $request->path(),
            $request->method(),
            $request->ip(),
            $request->userAgent(),
            $request->all(),
            [
                'error' => 'Rate limit exceeded',
                'rate_limit' => $usage
            ],
            429,
            $responseTime,
            $request->attributes->get('nonce')
        );

        $response = response()->json([
            'error' => 'Rate limit exceeded',
            'message' => 'Too many requests. Please try again later.',
            'code' => 'RATE_LIMIT_EXCEEDED',
            'rate_limit' => $usage
        ], 429);

        $this->addRateLimitHeaders($response, $apiClient);

        return $response;
    }

    /**
     * Handle rate limit exceeded for JWT
     */
    private function jwtRateLimitExceeded(Request $request, float $startTime, int $limit): Response
    {
        $responseTime = (microtime(true) - $startTime) * 1000;

        // Log the rate limit exceeded request
        \App\Models\ApiRequest::logRequest(
            null,
            $request->path(),
            $request->method(),
            $request->ip(),
            $request->userAgent(),
            $request->all(),
            [
                'error' => 'Rate limit exceeded',
                'limit' => $limit
            ],
            429,
            $responseTime
        );

        return response()->json([
            'error' => 'Rate limit exceeded',
            'message' => 'Too many requests. Please try again later.',
            'code' => 'RATE_LIMIT_EXCEEDED',
            'limit' => $limit
        ], 429);
    }

    /**
     * Add rate limit headers to response
     */
    private function addRateLimitHeaders(Response $response, ApiClient $apiClient): void
    {
        $usage = $apiClient->getRateLimitUsage();

        $response->headers->set('X-RateLimit-Limit', $usage['limit']);
        $response->headers->set('X-RateLimit-Remaining', $usage['remaining']);
        $response->headers->set('X-RateLimit-Reset', $usage['reset_at']->timestamp);
        $response->headers->set('X-RateLimit-Used', $usage['used']);
    }
}

<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ApiRateLimitMiddleware;
use App\Models\ApiClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ApiRateLimitMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private ApiRateLimitMiddleware $middleware;
    private User $user;
    private ApiClient $apiClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new ApiRateLimitMiddleware();
        
        $this->user = User::factory()->create();
        
        $credentials = ApiClient::generateCredentials();
        $this->apiClient = ApiClient::create([
            'name' => 'Test API Client',
            'api_key_hash' => $credentials['api_key_hash'],
            'secret_hash' => $credentials['secret'],
            'user_id' => $this->user->id,
            'scopes' => [ApiClient::SCOPE_LICENSE_VALIDATE],
            'is_active' => true,
            'rate_limit' => 5, // Low limit for testing
        ]);
    }

    public function test_request_passes_when_within_rate_limit()
    {
        $request = Request::create('/api/test', 'GET');
        $request->attributes->set('api_client', $this->apiClient);

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
        $this->assertTrue($response->headers->has('X-RateLimit-Remaining'));
        $this->assertTrue($response->headers->has('X-RateLimit-Reset'));
        $this->assertTrue($response->headers->has('X-RateLimit-Used'));
    }

    public function test_request_blocked_when_rate_limit_exceeded()
    {
        // Simulate that the client has already made the maximum number of requests
        for ($i = 0; $i < $this->apiClient->rate_limit; $i++) {
            \App\Models\ApiRequest::create([
                'api_client_id' => $this->apiClient->id,
                'endpoint' => '/api/test',
                'method' => 'GET',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test Agent',
                'response_status' => 200,
                'response_time' => 100.0,
                'request_timestamp' => now(),
            ]);
        }

        $request = Request::create('/api/test', 'GET');
        $request->attributes->set('api_client', $this->apiClient);

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(429, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('RATE_LIMIT_EXCEEDED', $responseData['code']);
        $this->assertArrayHasKey('rate_limit', $responseData);
    }

    public function test_jwt_rate_limiting_with_ip_based_limits()
    {
        // Clear any existing cache
        Cache::flush();

        $request = Request::create('/api/test', 'GET');
        $request->server->set('REMOTE_ADDR', '192.168.1.100');
        // No api_client attribute means JWT authentication

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
        $this->assertTrue($response->headers->has('X-RateLimit-Remaining'));
        $this->assertTrue($response->headers->has('X-RateLimit-Reset'));
        $this->assertEquals('1000', $response->headers->get('X-RateLimit-Limit'));
    }

    public function test_jwt_rate_limit_exceeded()
    {
        $ip = '192.168.1.101';
        $key = 'jwt_rate_limit:' . $ip;
        
        // Set cache to maximum limit
        Cache::put($key, 1000, 3600);

        $request = Request::create('/api/test', 'GET');
        $request->server->set('REMOTE_ADDR', $ip);

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(429, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('RATE_LIMIT_EXCEEDED', $responseData['code']);
        $this->assertEquals(1000, $responseData['limit']);
    }

    public function test_rate_limit_headers_are_correct()
    {
        $request = Request::create('/api/test', 'GET');
        $request->attributes->set('api_client', $this->apiClient);

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($this->apiClient->rate_limit, $response->headers->get('X-RateLimit-Limit'));
        
        // The remaining count should be limit minus current usage
        $usage = $this->apiClient->fresh()->getRateLimitUsage();
        $this->assertEquals($usage['remaining'], $response->headers->get('X-RateLimit-Remaining'));
        $this->assertEquals($usage['used'], $response->headers->get('X-RateLimit-Used'));
        $this->assertIsNumeric($response->headers->get('X-RateLimit-Reset'));
    }

    public function test_rate_limit_usage_calculation()
    {
        // Create some existing requests
        \App\Models\ApiRequest::create([
            'api_client_id' => $this->apiClient->id,
            'endpoint' => '/api/test',
            'method' => 'GET',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'response_status' => 200,
            'response_time' => 100.0,
            'request_timestamp' => now()->subMinutes(30),
        ]);

        \App\Models\ApiRequest::create([
            'api_client_id' => $this->apiClient->id,
            'endpoint' => '/api/test',
            'method' => 'GET',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'response_status' => 200,
            'response_time' => 100.0,
            'request_timestamp' => now()->subMinutes(10),
        ]);

        $usage = $this->apiClient->getRateLimitUsage();
        
        $this->assertEquals(2, $usage['used']);
        $this->assertEquals($this->apiClient->rate_limit, $usage['limit']);
        $this->assertEquals($this->apiClient->rate_limit - 2, $usage['remaining']);
        $this->assertInstanceOf(\Carbon\Carbon::class, $usage['reset_at']);
    }
}
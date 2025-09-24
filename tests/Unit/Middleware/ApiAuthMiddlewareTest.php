<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ApiAuthMiddleware;
use App\Models\ApiClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiAuthMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private ApiAuthMiddleware $middleware;
    private User $user;
    private ApiClient $apiClient;
    private array $credentials;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new ApiAuthMiddleware();
        
        $this->user = User::factory()->create();
        
        // Create API client with credentials
        $this->credentials = ApiClient::generateCredentials();
        $this->apiClient = ApiClient::create([
            'name' => 'Test API Client',
            'api_key_hash' => $this->credentials['api_key_hash'],
            'secret_hash' => $this->credentials['secret'],
            'user_id' => $this->user->id,
            'scopes' => [ApiClient::SCOPE_LICENSE_VALIDATE],
            'is_active' => true,
            'rate_limit' => 1000,
        ]);
    }

    public function test_jwt_authentication_success()
    {
        $token = $this->user->createToken('test-token', [ApiClient::SCOPE_LICENSE_VALIDATE]);
        
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $token->plainTextToken);

        $response = $this->middleware->handle($request, function ($req) {
            $this->assertEquals($this->user->id, Auth::id());
            $this->assertEquals('jwt', $req->attributes->get('auth_method'));
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_api_key_authentication_success()
    {
        $timestamp = time();
        $nonce = Str::random(16);
        $method = 'POST';
        $uri = '/api/test';
        $body = '{"test": "data"}';

        $signature = $this->generateSignature($method, $uri, $body, $timestamp, $nonce);

        $request = Request::create($uri, $method, [], [], [], [], $body);
        $request->headers->set('X-API-KEY', $this->credentials['api_key']);
        $request->headers->set('X-SIGNATURE', $signature);
        $request->headers->set('X-TIMESTAMP', $timestamp);
        $request->headers->set('X-NONCE', $nonce);

        $response = $this->middleware->handle($request, function ($req) {
            $this->assertEquals($this->user->id, Auth::id());
            $this->assertEquals('api_key', $req->attributes->get('auth_method'));
            $this->assertEquals($this->apiClient->id, $req->attributes->get('api_client')->id);
            return response()->json(['success' => true]);
        }, ApiClient::SCOPE_LICENSE_VALIDATE);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_authentication_fails_with_invalid_jwt()
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'Bearer invalid-token');

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('AUTH_FAILED', $responseData['code']);
    }

    public function test_authentication_fails_with_invalid_api_key()
    {
        $timestamp = time();
        $nonce = Str::random(16);

        $request = Request::create('/api/test', 'POST');
        $request->headers->set('X-API-KEY', 'invalid-api-key');
        $request->headers->set('X-SIGNATURE', 'invalid-signature');
        $request->headers->set('X-TIMESTAMP', $timestamp);
        $request->headers->set('X-NONCE', $nonce);

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_authentication_fails_with_expired_timestamp()
    {
        $timestamp = time() - 400; // 6+ minutes ago
        $nonce = Str::random(16);
        $method = 'POST';
        $uri = '/api/test';
        $body = '';

        $signature = $this->generateSignature($method, $uri, $body, $timestamp, $nonce);

        $request = Request::create($uri, $method);
        $request->headers->set('X-API-KEY', $this->credentials['api_key']);
        $request->headers->set('X-SIGNATURE', $signature);
        $request->headers->set('X-TIMESTAMP', $timestamp);
        $request->headers->set('X-NONCE', $nonce);

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_authentication_fails_with_insufficient_scope()
    {
        $timestamp = time();
        $nonce = Str::random(16);
        $method = 'POST';
        $uri = '/api/test';
        $body = '';

        $signature = $this->generateSignature($method, $uri, $body, $timestamp, $nonce);

        $request = Request::create($uri, $method);
        $request->headers->set('X-API-KEY', $this->credentials['api_key']);
        $request->headers->set('X-SIGNATURE', $signature);
        $request->headers->set('X-TIMESTAMP', $timestamp);
        $request->headers->set('X-NONCE', $nonce);

        // Request a scope that the client doesn't have
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, ApiClient::SCOPE_LICENSE_WRITE);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_nonce_uniqueness_validation()
    {
        $timestamp = time();
        $nonce = Str::random(16);
        $method = 'POST';
        $uri = '/api/test';
        $body = '';

        $signature = $this->generateSignature($method, $uri, $body, $timestamp, $nonce);

        // Create a logged request with the same nonce to simulate replay attack
        \App\Models\ApiRequest::create([
            'api_client_id' => $this->apiClient->id,
            'endpoint' => 'api/test',
            'method' => $method,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'response_status' => 200,
            'response_time' => 100.0,
            'nonce' => $nonce,
            'request_timestamp' => now()->subMinutes(2), // Within 5 minute window
        ]);

        // Request with duplicate nonce should fail
        $request = Request::create($uri, $method);
        $request->headers->set('X-API-KEY', $this->credentials['api_key']);
        $request->headers->set('X-SIGNATURE', $signature);
        $request->headers->set('X-TIMESTAMP', $timestamp);
        $request->headers->set('X-NONCE', $nonce);

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    private function generateSignature(string $method, string $uri, string $body, int $timestamp, string $nonce): string
    {
        $stringToSign = strtoupper($method) . "\n" . 
                       $uri . "\n" . 
                       $body . "\n" . 
                       $timestamp . "\n" . 
                       $nonce;

        return hash_hmac('sha256', $stringToSign, $this->credentials['secret']);
    }
}
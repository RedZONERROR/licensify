<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\ApiClient;
use App\Models\License;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LicenseValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ApiClient $apiClient;
    private License $license;
    private Product $product;
    private array $credentials;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create([
            'role' => Role::ADMIN,
        ]);

        // Create test product
        $this->product = Product::factory()->create();

        // Create test license
        $this->license = License::factory()->create([
            'product_id' => $this->product->id,
            'owner_id' => $this->user->id,
            'status' => License::STATUS_ACTIVE,
            'max_devices' => 2,
            'expires_at' => now()->addYear(),
        ]);

        // Create API client with credentials
        $this->credentials = ApiClient::generateCredentials();
        $this->apiClient = ApiClient::create([
            'name' => 'Test API Client',
            'api_key_hash' => $this->credentials['api_key_hash'],
            'secret_hash' => $this->credentials['secret'],
            'user_id' => $this->user->id,
            'scopes' => [ApiClient::SCOPE_LICENSE_VALIDATE, ApiClient::SCOPE_LICENSE_READ],
            'is_active' => true,
            'rate_limit' => 1000,
        ]);
    }

    public function test_license_validation_with_valid_api_key_authentication()
    {
        $deviceHash = 'test-device-hash-123';
        $timestamp = time();
        $nonce = Str::random(16);

        $requestData = [
            'license_key' => $this->license->license_key,
            'device_hash' => $deviceHash,
            'device_info' => [
                'name' => 'Test Device',
                'os' => 'Windows 11',
                'version' => '22H2',
            ],
        ];

        $signature = $this->generateSignature('POST', '/api/license/validate', $requestData, $timestamp, $nonce);

        $response = $this->postJson('/api/license/validate', $requestData, [
            'X-API-KEY' => $this->credentials['api_key'],
            'X-SIGNATURE' => $signature,
            'X-TIMESTAMP' => $timestamp,
            'X-NONCE' => $nonce,
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'valid' => true,
                        'license' => [
                            'key' => $this->license->license_key,
                            'status' => 'active',
                            'max_devices' => 2,
                            'active_devices' => 1,
                        ],
                        'device' => [
                            'hash' => $deviceHash,
                            'bound' => true,
                        ],
                    ],
                ]);

        // Verify device was bound
        $this->assertTrue($this->license->fresh()->isDeviceBound($deviceHash));
    }

    public function test_license_validation_with_jwt_authentication()
    {
        $token = $this->user->createToken('test-token', [ApiClient::SCOPE_LICENSE_VALIDATE])->plainTextToken;

        $deviceHash = 'test-device-hash-jwt';
        $requestData = [
            'license_key' => $this->license->license_key,
            'device_hash' => $deviceHash,
        ];

        $response = $this->postJson('/api/license/validate', $requestData, [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'valid' => true,
                    ],
                ]);
    }

    public function test_license_validation_fails_with_invalid_license_key()
    {
        $deviceHash = 'test-device-hash-123';
        $timestamp = time();
        $nonce = Str::random(16);

        $requestData = [
            'license_key' => Str::uuid(),
            'device_hash' => $deviceHash,
        ];

        $signature = $this->generateSignature('POST', '/api/license/validate', $requestData, $timestamp, $nonce);

        $response = $this->postJson('/api/license/validate', $requestData, [
            'X-API-KEY' => $this->credentials['api_key'],
            'X-SIGNATURE' => $signature,
            'X-TIMESTAMP' => $timestamp,
            'X-NONCE' => $nonce,
        ]);

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'error' => [
                        'code' => 'LICENSE_NOT_FOUND',
                    ],
                ]);
    }

    public function test_license_validation_fails_with_suspended_license()
    {
        $this->license->update(['status' => License::STATUS_SUSPENDED]);

        $deviceHash = 'test-device-hash-123';
        $timestamp = time();
        $nonce = Str::random(16);

        $requestData = [
            'license_key' => $this->license->license_key,
            'device_hash' => $deviceHash,
        ];

        $signature = $this->generateSignature('POST', '/api/license/validate', $requestData, $timestamp, $nonce);

        $response = $this->postJson('/api/license/validate', $requestData, [
            'X-API-KEY' => $this->credentials['api_key'],
            'X-SIGNATURE' => $signature,
            'X-TIMESTAMP' => $timestamp,
            'X-NONCE' => $nonce,
        ]);

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'error' => [
                        'code' => 'LICENSE_SUSPENDED',
                    ],
                ]);
    }

    public function test_license_validation_fails_with_expired_license()
    {
        $this->license->update(['expires_at' => now()->subDay()]);

        $deviceHash = 'test-device-hash-123';
        $timestamp = time();
        $nonce = Str::random(16);

        $requestData = [
            'license_key' => $this->license->license_key,
            'device_hash' => $deviceHash,
        ];

        $signature = $this->generateSignature('POST', '/api/license/validate', $requestData, $timestamp, $nonce);

        $response = $this->postJson('/api/license/validate', $requestData, [
            'X-API-KEY' => $this->credentials['api_key'],
            'X-SIGNATURE' => $signature,
            'X-TIMESTAMP' => $timestamp,
            'X-NONCE' => $nonce,
        ]);

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'error' => [
                        'code' => 'LICENSE_EXPIRED',
                    ],
                ]);
    }

    public function test_license_validation_fails_when_device_limit_exceeded()
    {
        // Bind maximum devices
        $this->license->bindDevice('device-1', []);
        $this->license->bindDevice('device-2', []);

        $deviceHash = 'device-3';
        $timestamp = time();
        $nonce = Str::random(16);

        $requestData = [
            'license_key' => $this->license->license_key,
            'device_hash' => $deviceHash,
        ];

        $signature = $this->generateSignature('POST', '/api/license/validate', $requestData, $timestamp, $nonce);

        $response = $this->postJson('/api/license/validate', $requestData, [
            'X-API-KEY' => $this->credentials['api_key'],
            'X-SIGNATURE' => $signature,
            'X-TIMESTAMP' => $timestamp,
            'X-NONCE' => $nonce,
        ]);

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'error' => [
                        'code' => 'DEVICE_LIMIT_EXCEEDED',
                    ],
                ]);
    }

    public function test_license_show_endpoint_with_api_key()
    {
        $timestamp = time();
        $nonce = Str::random(16);

        $signature = $this->generateSignature('GET', '/api/license/' . $this->license->license_key, [], $timestamp, $nonce);

        $response = $this->getJson('/api/license/' . $this->license->license_key, [
            'X-API-KEY' => $this->credentials['api_key'],
            'X-SIGNATURE' => $signature,
            'X-TIMESTAMP' => $timestamp,
            'X-NONCE' => $nonce,
        ]);



        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'license' => [
                            'key' => $this->license->license_key,
                            'status' => 'active',
                            'is_active' => true,
                        ],
                    ],
                ]);
    }

    public function test_authentication_fails_with_invalid_api_key()
    {
        $deviceHash = 'test-device-hash-123';
        $timestamp = time();
        $nonce = Str::random(16);

        $requestData = [
            'license_key' => $this->license->license_key,
            'device_hash' => $deviceHash,
        ];

        $signature = $this->generateSignature('POST', '/api/license/validate', $requestData, $timestamp, $nonce);

        $response = $this->postJson('/api/license/validate', $requestData, [
            'X-API-KEY' => 'invalid-api-key',
            'X-SIGNATURE' => $signature,
            'X-TIMESTAMP' => $timestamp,
            'X-NONCE' => $nonce,
        ]);

        $response->assertStatus(401)
                ->assertJson([
                    'error' => 'Unauthorized',
                    'code' => 'AUTH_FAILED',
                ]);
    }

    public function test_authentication_fails_with_invalid_signature()
    {
        $deviceHash = 'test-device-hash-123';
        $timestamp = time();
        $nonce = Str::random(16);

        $requestData = [
            'license_key' => $this->license->license_key,
            'device_hash' => $deviceHash,
        ];

        $response = $this->postJson('/api/license/validate', $requestData, [
            'X-API-KEY' => $this->credentials['api_key'],
            'X-SIGNATURE' => 'invalid-signature',
            'X-TIMESTAMP' => $timestamp,
            'X-NONCE' => $nonce,
        ]);

        $response->assertStatus(401)
                ->assertJson([
                    'error' => 'Unauthorized',
                    'code' => 'AUTH_FAILED',
                ]);
    }

    public function test_authentication_fails_with_expired_timestamp()
    {
        $deviceHash = 'test-device-hash-123';
        $timestamp = time() - 400; // 6+ minutes ago
        $nonce = Str::random(16);

        $requestData = [
            'license_key' => $this->license->license_key,
            'device_hash' => $deviceHash,
        ];

        $signature = $this->generateSignature('POST', '/api/license/validate', $requestData, $timestamp, $nonce);

        $response = $this->postJson('/api/license/validate', $requestData, [
            'X-API-KEY' => $this->credentials['api_key'],
            'X-SIGNATURE' => $signature,
            'X-TIMESTAMP' => $timestamp,
            'X-NONCE' => $nonce,
        ]);

        $response->assertStatus(401)
                ->assertJson([
                    'error' => 'Unauthorized',
                    'code' => 'AUTH_FAILED',
                ]);
    }

    public function test_rate_limiting_works()
    {
        // Set a very low rate limit
        $this->apiClient->update(['rate_limit' => 1]);

        $deviceHash = 'test-device-hash-123';
        $timestamp = time();

        // First request should succeed
        $nonce1 = Str::random(16);
        $requestData = [
            'license_key' => $this->license->license_key,
            'device_hash' => $deviceHash,
        ];

        $signature1 = $this->generateSignature('POST', '/api/license/validate', $requestData, $timestamp, $nonce1);

        $response1 = $this->postJson('/api/license/validate', $requestData, [
            'X-API-KEY' => $this->credentials['api_key'],
            'X-SIGNATURE' => $signature1,
            'X-TIMESTAMP' => $timestamp,
            'X-NONCE' => $nonce1,
        ]);

        $response1->assertStatus(200);

        // Second request should be rate limited
        $nonce2 = Str::random(16);
        $signature2 = $this->generateSignature('POST', '/api/license/validate', $requestData, $timestamp, $nonce2);

        $response2 = $this->postJson('/api/license/validate', $requestData, [
            'X-API-KEY' => $this->credentials['api_key'],
            'X-SIGNATURE' => $signature2,
            'X-TIMESTAMP' => $timestamp,
            'X-NONCE' => $nonce2,
        ]);

        $response2->assertStatus(429)
                ->assertJson([
                    'error' => 'Rate limit exceeded',
                    'code' => 'RATE_LIMIT_EXCEEDED',
                ]);
    }

    public function test_nonce_replay_attack_prevention()
    {
        $deviceHash = 'test-device-hash-123';
        $timestamp = time();
        $nonce = Str::random(16);

        $requestData = [
            'license_key' => $this->license->license_key,
            'device_hash' => $deviceHash,
        ];

        $signature = $this->generateSignature('POST', '/api/license/validate', $requestData, $timestamp, $nonce);

        // First request should succeed
        $response1 = $this->postJson('/api/license/validate', $requestData, [
            'X-API-KEY' => $this->credentials['api_key'],
            'X-SIGNATURE' => $signature,
            'X-TIMESTAMP' => $timestamp,
            'X-NONCE' => $nonce,
        ]);

        $response1->assertStatus(200);

        // Second request with same nonce should fail
        $response2 = $this->postJson('/api/license/validate', $requestData, [
            'X-API-KEY' => $this->credentials['api_key'],
            'X-SIGNATURE' => $signature,
            'X-TIMESTAMP' => $timestamp,
            'X-NONCE' => $nonce,
        ]);

        $response2->assertStatus(401)
                ->assertJson([
                    'error' => 'Unauthorized',
                    'code' => 'AUTH_FAILED',
                ]);
    }

    public function test_authentication_fails_with_missing_headers()
    {
        $deviceHash = 'test-device-hash-123';
        $requestData = [
            'license_key' => $this->license->license_key,
            'device_hash' => $deviceHash,
        ];

        // Missing X-API-KEY
        $response1 = $this->postJson('/api/license/validate', $requestData, [
            'X-SIGNATURE' => 'some-signature',
            'X-TIMESTAMP' => time(),
            'X-NONCE' => Str::random(16),
        ]);

        $response1->assertStatus(401);

        // Missing X-SIGNATURE
        $response2 = $this->postJson('/api/license/validate', $requestData, [
            'X-API-KEY' => $this->credentials['api_key'],
            'X-TIMESTAMP' => time(),
            'X-NONCE' => Str::random(16),
        ]);

        $response2->assertStatus(401);

        // Missing X-TIMESTAMP
        $response3 = $this->postJson('/api/license/validate', $requestData, [
            'X-API-KEY' => $this->credentials['api_key'],
            'X-SIGNATURE' => 'some-signature',
            'X-NONCE' => Str::random(16),
        ]);

        $response3->assertStatus(401);

        // Missing X-NONCE
        $response4 = $this->postJson('/api/license/validate', $requestData, [
            'X-API-KEY' => $this->credentials['api_key'],
            'X-SIGNATURE' => 'some-signature',
            'X-TIMESTAMP' => time(),
        ]);

        $response4->assertStatus(401);
    }

    public function test_authentication_fails_with_inactive_api_client()
    {
        // Deactivate the API client
        $this->apiClient->update(['is_active' => false]);

        $deviceHash = 'test-device-hash-123';
        $timestamp = time();
        $nonce = Str::random(16);

        $requestData = [
            'license_key' => $this->license->license_key,
            'device_hash' => $deviceHash,
        ];

        $signature = $this->generateSignature('POST', '/api/license/validate', $requestData, $timestamp, $nonce);

        $response = $this->postJson('/api/license/validate', $requestData, [
            'X-API-KEY' => $this->credentials['api_key'],
            'X-SIGNATURE' => $signature,
            'X-TIMESTAMP' => $timestamp,
            'X-NONCE' => $nonce,
        ]);

        $response->assertStatus(401)
                ->assertJson([
                    'error' => 'Unauthorized',
                    'code' => 'AUTH_FAILED',
                ]);
    }

    public function test_api_request_logging()
    {
        $deviceHash = 'test-device-hash-123';
        $timestamp = time();
        $nonce = Str::random(16);

        $requestData = [
            'license_key' => $this->license->license_key,
            'device_hash' => $deviceHash,
        ];

        $signature = $this->generateSignature('POST', '/api/license/validate', $requestData, $timestamp, $nonce);

        // Count requests before
        $requestCountBefore = \App\Models\ApiRequest::count();

        $response = $this->postJson('/api/license/validate', $requestData, [
            'X-API-KEY' => $this->credentials['api_key'],
            'X-SIGNATURE' => $signature,
            'X-TIMESTAMP' => $timestamp,
            'X-NONCE' => $nonce,
        ]);

        $response->assertStatus(200);

        // Verify request was logged
        $requestCountAfter = \App\Models\ApiRequest::count();
        $this->assertEquals($requestCountBefore + 1, $requestCountAfter);

        // Verify log details
        $loggedRequest = \App\Models\ApiRequest::latest()->first();
        $this->assertEquals($this->apiClient->id, $loggedRequest->api_client_id);
        $this->assertEquals('api/license/validate', $loggedRequest->endpoint);
        $this->assertEquals('POST', $loggedRequest->method);
        $this->assertEquals(200, $loggedRequest->response_status);
        $this->assertEquals($nonce, $loggedRequest->nonce);
        $this->assertNotNull($loggedRequest->response_time);
    }

    public function test_jwt_rate_limiting()
    {
        $token = $this->user->createToken('test-token', [ApiClient::SCOPE_LICENSE_VALIDATE])->plainTextToken;

        $deviceHash = 'test-device-hash-jwt';
        $requestData = [
            'license_key' => $this->license->license_key,
            'device_hash' => $deviceHash,
        ];

        // Make multiple requests to test JWT rate limiting
        // Note: JWT rate limiting is IP-based with a higher limit (1000/hour)
        // So we'll just verify the headers are present
        $response = $this->postJson('/api/license/validate', $requestData, [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200);
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
        $response->assertHeader('X-RateLimit-Reset');
    }

    public function test_standardized_response_format()
    {
        $deviceHash = 'test-device-hash-123';
        $timestamp = time();
        $nonce = Str::random(16);

        $requestData = [
            'license_key' => $this->license->license_key,
            'device_hash' => $deviceHash,
        ];

        $signature = $this->generateSignature('POST', '/api/license/validate', $requestData, $timestamp, $nonce);

        $response = $this->postJson('/api/license/validate', $requestData, [
            'X-API-KEY' => $this->credentials['api_key'],
            'X-SIGNATURE' => $signature,
            'X-TIMESTAMP' => $timestamp,
            'X-NONCE' => $nonce,
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'valid',
                        'license' => [
                            'key',
                            'status',
                            'expires_at',
                            'max_devices',
                            'active_devices',
                            'device_type',
                        ],
                        'device' => [
                            'hash',
                            'bound',
                            'bound_at',
                        ],
                        'product' => [
                            'id',
                        ],
                        'validated_at',
                    ],
                    'meta' => [
                        'timestamp',
                        'response_time_ms',
                        'api_version',
                    ],
                ]);

        // Verify meta information
        $responseData = $response->json();
        $this->assertTrue($responseData['success']);
        $this->assertEquals('1.0', $responseData['meta']['api_version']);
        $this->assertIsFloat($responseData['meta']['response_time_ms']);
        $this->assertNotNull($responseData['meta']['timestamp']);
    }

    private function generateSignature(string $method, string $uri, array $body, int $timestamp, string $nonce): string
    {
        // For GET requests, Laravel seems to return "[]" as the body content
        if (strtoupper($method) === 'GET' && empty($body)) {
            $bodyString = '[]';
        } else {
            $bodyString = empty($body) ? '' : json_encode($body);
        }
        
        $stringToSign = strtoupper($method) . "\n" . 
                       $uri . "\n" . 
                       $bodyString . "\n" . 
                       $timestamp . "\n" . 
                       $nonce;

        return hash_hmac('sha256', $stringToSign, $this->credentials['secret']);
    }
}

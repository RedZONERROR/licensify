<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\ApiRequest;
use App\Models\License;
use App\Models\LicenseActivation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LicenseValidationController extends Controller
{
    /**
     * Validate a license key
     */
    public function validateLicense(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        try {
            // Validate request data
            $validator = Validator::make($request->all(), [
                'license_key' => 'required|string|uuid',
                'device_hash' => 'required|string|max:255',
                'device_info' => 'sometimes|array',
                'device_info.name' => 'sometimes|string|max:255',
                'device_info.os' => 'sometimes|string|max:100',
                'device_info.version' => 'sometimes|string|max:100',
                'device_info.hardware' => 'sometimes|string|max:255',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    'Validation failed',
                    'VALIDATION_ERROR',
                    $validator->errors()->toArray(),
                    400,
                    $request,
                    $startTime
                );
            }

            $licenseKey = $request->input('license_key');
            $deviceHash = $request->input('device_hash');
            $deviceInfo = $request->input('device_info', []);

            // Find license
            $license = License::where('license_key', $licenseKey)->first();

            if (!$license) {
                return $this->errorResponse(
                    'License not found',
                    'LICENSE_NOT_FOUND',
                    ['license_key' => 'The specified license key does not exist'],
                    404,
                    $request,
                    $startTime
                );
            }

            // Check license status
            if ($license->isSuspended()) {
                return $this->errorResponse(
                    'License suspended',
                    'LICENSE_SUSPENDED',
                    ['status' => 'The license has been suspended'],
                    403,
                    $request,
                    $startTime
                );
            }

            if ($license->isExpired()) {
                return $this->errorResponse(
                    'License expired',
                    'LICENSE_EXPIRED',
                    ['expires_at' => $license->expires_at?->toISOString()],
                    403,
                    $request,
                    $startTime
                );
            }

            if ($license->needsReset()) {
                return $this->errorResponse(
                    'License requires device reset',
                    'LICENSE_RESET_REQUIRED',
                    ['status' => 'All device bindings have been reset. Please re-activate.'],
                    403,
                    $request,
                    $startTime
                );
            }

            // Check device binding
            $isDeviceBound = $license->isDeviceBound($deviceHash);
            $canBindDevice = $license->canBindDevice();
            $activeDeviceCount = $license->getActiveDeviceCount();

            // If device is not bound and we can't bind more devices
            if (!$isDeviceBound && !$canBindDevice) {
                return $this->errorResponse(
                    'Device limit exceeded',
                    'DEVICE_LIMIT_EXCEEDED',
                    [
                        'max_devices' => $license->max_devices,
                        'active_devices' => $activeDeviceCount,
                        'device_bound' => false
                    ],
                    403,
                    $request,
                    $startTime
                );
            }

            // Bind device if not already bound
            if (!$isDeviceBound) {
                $activation = $license->bindDevice($deviceHash, $deviceInfo);
                if (!$activation) {
                    return $this->errorResponse(
                        'Failed to bind device',
                        'DEVICE_BINDING_FAILED',
                        ['message' => 'Unable to bind device to license'],
                        500,
                        $request,
                        $startTime
                    );
                }
            }

            // License is valid - return success response
            $responseData = [
                'valid' => true,
                'license' => [
                    'key' => $license->license_key,
                    'status' => $license->status,
                    'expires_at' => $license->expires_at?->toISOString(),
                    'max_devices' => $license->max_devices,
                    'active_devices' => $license->getActiveDeviceCount(),
                    'device_type' => $license->device_type,
                ],
                'device' => [
                    'hash' => $deviceHash,
                    'bound' => true,
                    'bound_at' => $isDeviceBound 
                        ? $license->activations()->where('device_hash', $deviceHash)->first()?->activated_at?->toISOString()
                        : now()->toISOString()
                ],
                'product' => [
                    'id' => $license->product_id,
                ],
                'validated_at' => now()->toISOString()
            ];

            return $this->successResponse($responseData, $request, $startTime);

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Internal server error',
                'INTERNAL_ERROR',
                ['message' => 'An unexpected error occurred'],
                500,
                $request,
                $startTime
            );
        }
    }

    /**
     * Get license information (read-only)
     */
    public function show(Request $request, string $licenseKey): JsonResponse
    {
        $startTime = microtime(true);

        try {
            // Validate license key format
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $licenseKey)) {
                return $this->errorResponse(
                    'Invalid license key format',
                    'INVALID_FORMAT',
                    ['license_key' => 'License key must be a valid UUID'],
                    400,
                    $request,
                    $startTime
                );
            }

            // Find license
            $license = License::with(['product', 'activations'])
                             ->where('license_key', $licenseKey)
                             ->first();

            if (!$license) {
                return $this->errorResponse(
                    'License not found',
                    'LICENSE_NOT_FOUND',
                    ['license_key' => 'The specified license key does not exist'],
                    404,
                    $request,
                    $startTime
                );
            }

            // Check API client scope for detailed information
            $apiClient = $request->attributes->get('api_client');
            $includeDetails = $apiClient && $apiClient->hasScope(ApiClient::SCOPE_LICENSE_READ);

            $responseData = [
                'license' => [
                    'key' => $license->license_key,
                    'status' => $license->status,
                    'expires_at' => $license->expires_at?->toISOString(),
                    'max_devices' => $license->max_devices,
                    'active_devices' => $license->getActiveDeviceCount(),
                    'device_type' => $license->device_type,
                    'is_active' => $license->isActive(),
                    'is_expired' => $license->isExpired(),
                    'is_suspended' => $license->isSuspended(),
                    'needs_reset' => $license->needsReset(),
                ],
                'product' => [
                    'id' => $license->product_id,
                ]
            ];

            // Add detailed information if client has appropriate scope
            if ($includeDetails) {
                $responseData['license']['created_at'] = $license->created_at->toISOString();
                $responseData['license']['updated_at'] = $license->updated_at->toISOString();
                
                $responseData['activations'] = $license->activations->map(function ($activation) {
                    return [
                        'device_hash' => substr($activation->device_hash, 0, 8) . '...',
                        'activated_at' => $activation->activated_at->toISOString(),
                        'device_info' => $activation->device_info ?? [],
                    ];
                });
            }

            return $this->successResponse($responseData, $request, $startTime);

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Internal server error',
                'INTERNAL_ERROR',
                ['message' => 'An unexpected error occurred'],
                500,
                $request,
                $startTime
            );
        }
    }

    /**
     * Return a standardized success response
     */
    private function successResponse(array $data, Request $request, float $startTime): JsonResponse
    {
        $responseTime = (microtime(true) - $startTime) * 1000;
        $apiClient = $request->attributes->get('api_client');

        // Log successful request
        ApiRequest::logRequest(
            $apiClient,
            $request->path(),
            $request->method(),
            $request->ip(),
            $request->userAgent(),
            $request->all(),
            $data,
            200,
            $responseTime,
            $request->attributes->get('nonce')
        );

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'timestamp' => now()->toISOString(),
                'response_time_ms' => round($responseTime, 2),
                'api_version' => '1.0'
            ]
        ], 200);
    }

    /**
     * Return a standardized error response
     */
    private function errorResponse(
        string $message,
        string $code,
        array $details,
        int $status,
        Request $request,
        float $startTime
    ): JsonResponse {
        $responseTime = (microtime(true) - $startTime) * 1000;
        $apiClient = $request->attributes->get('api_client');

        $errorData = [
            'error' => $message,
            'code' => $code,
            'details' => $details
        ];

        // Log error request
        ApiRequest::logRequest(
            $apiClient,
            $request->path(),
            $request->method(),
            $request->ip(),
            $request->userAgent(),
            $request->all(),
            $errorData,
            $status,
            $responseTime,
            $request->attributes->get('nonce')
        );

        return response()->json([
            'success' => false,
            'error' => $errorData,
            'meta' => [
                'timestamp' => now()->toISOString(),
                'response_time_ms' => round($responseTime, 2),
                'api_version' => '1.0'
            ]
        ], $status);
    }
}

# License Validation API

This document describes the License Validation API endpoints, authentication methods, and usage examples.

## Base URL

```
https://your-domain.com/api
```

## Authentication

The API supports two authentication methods:

### 1. JWT Authentication (Bearer Token)

Use Laravel Sanctum personal access tokens:

```http
Authorization: Bearer {token}
```

### 2. API Key + HMAC Signature

More secure method using API key and HMAC signature:

```http
X-API-KEY: {api_key}
X-SIGNATURE: {hmac_signature}
X-TIMESTAMP: {unix_timestamp}
X-NONCE: {unique_nonce}
```

#### HMAC Signature Generation

The signature is generated using HMAC-SHA256 with the following string:

```
{METHOD}\n{URI}\n{BODY}\n{TIMESTAMP}\n{NONCE}
```

Example in PHP:
```php
$method = 'POST';
$uri = '/api/license/validate';
$body = json_encode(['license_key' => 'xxx', 'device_hash' => 'yyy']);
$timestamp = time();
$nonce = bin2hex(random_bytes(16));

$stringToSign = strtoupper($method) . "\n" . 
               $uri . "\n" . 
               $body . "\n" . 
               $timestamp . "\n" . 
               $nonce;

$signature = hash_hmac('sha256', $stringToSign, $secret);
```

## Endpoints

### POST /api/license/validate

Validates a license key and binds a device if necessary.

#### Request

```json
{
    "license_key": "550e8400-e29b-41d4-a716-446655440000",
    "device_hash": "unique-device-identifier",
    "device_info": {
        "name": "John's Laptop",
        "os": "Windows 11",
        "version": "22H2",
        "hardware": "Intel i7-12700K"
    }
}
```

#### Response (Success)

```json
{
    "success": true,
    "data": {
        "valid": true,
        "license": {
            "key": "550e8400-e29b-41d4-a716-446655440000",
            "status": "active",
            "expires_at": "2024-12-31T23:59:59.000000Z",
            "max_devices": 3,
            "active_devices": 1,
            "device_type": "desktop"
        },
        "device": {
            "hash": "unique-device-identifier",
            "bound": true,
            "bound_at": "2024-01-15T10:30:00.000000Z"
        },
        "product": {
            "id": 1
        },
        "validated_at": "2024-01-15T10:30:00.000000Z"
    },
    "meta": {
        "timestamp": "2024-01-15T10:30:00.000000Z",
        "response_time_ms": 45.23,
        "api_version": "1.0"
    }
}
```

#### Response (Error)

```json
{
    "success": false,
    "error": {
        "error": "License not found",
        "code": "LICENSE_NOT_FOUND",
        "details": {
            "license_key": "The specified license key does not exist"
        }
    },
    "meta": {
        "timestamp": "2024-01-15T10:30:00.000000Z",
        "response_time_ms": 12.45,
        "api_version": "1.0"
    }
}
```

### GET /api/license/{license_key}

Retrieves license information (read-only).

#### Response

```json
{
    "success": true,
    "data": {
        "license": {
            "key": "550e8400-e29b-41d4-a716-446655440000",
            "status": "active",
            "expires_at": "2024-12-31T23:59:59.000000Z",
            "max_devices": 3,
            "active_devices": 2,
            "device_type": "desktop",
            "is_active": true,
            "is_expired": false,
            "is_suspended": false,
            "needs_reset": false
        },
        "product": {
            "id": 1
        }
    },
    "meta": {
        "timestamp": "2024-01-15T10:30:00.000000Z",
        "response_time_ms": 23.12,
        "api_version": "1.0"
    }
}
```

## Error Codes

| Code | Description |
|------|-------------|
| `VALIDATION_ERROR` | Request validation failed |
| `LICENSE_NOT_FOUND` | License key does not exist |
| `LICENSE_SUSPENDED` | License has been suspended |
| `LICENSE_EXPIRED` | License has expired |
| `LICENSE_RESET_REQUIRED` | License requires device reset |
| `DEVICE_LIMIT_EXCEEDED` | Maximum device limit reached |
| `DEVICE_BINDING_FAILED` | Failed to bind device |
| `AUTH_FAILED` | Authentication failed |
| `RATE_LIMIT_EXCEEDED` | Rate limit exceeded |
| `INTERNAL_ERROR` | Internal server error |

## Rate Limiting

### API Key Authentication
- Rate limits are per API client
- Default: 1000 requests per hour
- Configurable per client

### JWT Authentication
- Rate limits are per IP address
- Default: 1000 requests per hour

### Rate Limit Headers

All responses include rate limit headers:

```http
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1642248000
X-RateLimit-Used: 1
```

## Security Features

### Timestamp Validation
- Requests must include a timestamp within 5 minutes of server time
- Prevents replay attacks with old requests

### Nonce Validation
- Each request must include a unique nonce
- Nonces are tracked for 5 minutes to prevent replay attacks

### Request Logging
- All API requests are logged with:
  - Client information
  - Request/response data
  - Response times
  - IP addresses and user agents

## Example Usage

### cURL Example

```bash
# Generate signature (this would be done programmatically)
TIMESTAMP=$(date +%s)
NONCE=$(openssl rand -hex 16)
BODY='{"license_key":"550e8400-e29b-41d4-a716-446655440000","device_hash":"device123"}'
STRING_TO_SIGN="POST\n/api/license/validate\n${BODY}\n${TIMESTAMP}\n${NONCE}"
SIGNATURE=$(echo -n "$STRING_TO_SIGN" | openssl dgst -sha256 -hmac "$SECRET" -binary | base64)

curl -X POST https://your-domain.com/api/license/validate \
  -H "Content-Type: application/json" \
  -H "X-API-KEY: your-api-key" \
  -H "X-SIGNATURE: $SIGNATURE" \
  -H "X-TIMESTAMP: $TIMESTAMP" \
  -H "X-NONCE: $NONCE" \
  -d "$BODY"
```

### PHP Example

```php
<?php

class LicenseValidator
{
    private $apiKey;
    private $secret;
    private $baseUrl;

    public function __construct($apiKey, $secret, $baseUrl)
    {
        $this->apiKey = $apiKey;
        $this->secret = $secret;
        $this->baseUrl = $baseUrl;
    }

    public function validateLicense($licenseKey, $deviceHash, $deviceInfo = [])
    {
        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        $body = json_encode([
            'license_key' => $licenseKey,
            'device_hash' => $deviceHash,
            'device_info' => $deviceInfo
        ]);

        $signature = $this->generateSignature('POST', '/api/license/validate', $body, $timestamp, $nonce);

        $headers = [
            'Content-Type: application/json',
            'X-API-KEY: ' . $this->apiKey,
            'X-SIGNATURE: ' . $signature,
            'X-TIMESTAMP: ' . $timestamp,
            'X-NONCE: ' . $nonce
        ];

        $ch = curl_init($this->baseUrl . '/api/license/validate');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status_code' => $httpCode,
            'response' => json_decode($response, true)
        ];
    }

    private function generateSignature($method, $uri, $body, $timestamp, $nonce)
    {
        $stringToSign = strtoupper($method) . "\n" . 
                       $uri . "\n" . 
                       $body . "\n" . 
                       $timestamp . "\n" . 
                       $nonce;

        return hash_hmac('sha256', $stringToSign, $this->secret);
    }
}

// Usage
$validator = new LicenseValidator('your-api-key', 'your-secret', 'https://your-domain.com');
$result = $validator->validateLicense(
    '550e8400-e29b-41d4-a716-446655440000',
    'unique-device-hash',
    ['name' => 'User Device', 'os' => 'Windows 11']
);

if ($result['status_code'] === 200 && $result['response']['success']) {
    echo "License is valid!";
} else {
    echo "License validation failed: " . $result['response']['error']['code'];
}
```

## Best Practices

1. **Store API secrets securely** - Never expose API secrets in client-side code
2. **Use HTTPS** - Always use HTTPS in production
3. **Handle rate limits** - Implement exponential backoff for rate limit responses
4. **Cache responses** - Cache validation responses when appropriate
5. **Monitor usage** - Track API usage and set up alerts for unusual activity
6. **Rotate keys** - Regularly rotate API keys and secrets
7. **Validate responses** - Always validate API responses before using the data
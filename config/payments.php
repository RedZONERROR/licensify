<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Providers Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for various payment providers
    | supported by the license management system.
    |
    */

    'providers' => [
        'stripe' => [
            'enabled' => env('STRIPE_ENABLED', false),
            'public_key' => env('STRIPE_PUBLIC_KEY'),
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'webhook_url' => env('APP_URL') . '/api/webhooks/stripe',
        ],

        'paypal' => [
            'enabled' => env('PAYPAL_ENABLED', false),
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'webhook_secret' => env('PAYPAL_WEBHOOK_SECRET'),
            'webhook_url' => env('APP_URL') . '/api/webhooks/paypal',
            'sandbox' => env('PAYPAL_SANDBOX', true),
        ],

        'razorpay' => [
            'enabled' => env('RAZORPAY_ENABLED', false),
            'key_id' => env('RAZORPAY_KEY_ID'),
            'key_secret' => env('RAZORPAY_KEY_SECRET'),
            'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
            'webhook_url' => env('APP_URL') . '/api/webhooks/razorpay',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Wallet Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the wallet system
    |
    */

    'wallet' => [
        'default_currency' => env('WALLET_DEFAULT_CURRENCY', 'USD'),
        'minimum_balance' => env('WALLET_MINIMUM_BALANCE', 0.01),
        'maximum_balance' => env('WALLET_MAXIMUM_BALANCE', 10000.00),
    ],

    /*
    |--------------------------------------------------------------------------
    | License Pricing Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic license activation pricing
    |
    */

    'pricing' => [
        'license_activation_cost' => env('LICENSE_ACTIVATION_COST', 10.00),
        'license_extension_cost_per_month' => env('LICENSE_EXTENSION_COST_PER_MONTH', 10.00),
        'auto_activate_licenses' => env('AUTO_ACTIVATE_LICENSES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for transaction processing
    |
    */

    'transactions' => [
        'idempotency_cache_ttl' => env('TRANSACTION_IDEMPOTENCY_TTL', 86400), // 24 hours
        'retry_failed_webhooks' => env('RETRY_FAILED_WEBHOOKS', true),
        'max_retry_attempts' => env('MAX_WEBHOOK_RETRY_ATTEMPTS', 3),
    ],
];
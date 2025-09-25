<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Transaction;
use App\Models\UserWallet;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_stripe_webhook_success()
    {
        $webhookData = [
            'type' => 'payment_intent.succeeded',
            'id' => 'evt_test_webhook',
            'data' => [
                'object' => [
                    'id' => 'pi_test_123',
                    'amount' => 2000, // $20.00 in cents
                    'currency' => 'usd',
                    'description' => 'Test payment',
                    'metadata' => [
                        'user_id' => $this->user->id
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/webhooks/stripe', $webhookData, [
            'Stripe-Signature' => 'test_signature'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Webhook processed successfully'
                ]);

        // Verify transaction was created
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'transaction_id' => 'pi_test_123',
            'provider' => 'stripe',
            'amount' => 20.00,
            'status' => 'completed'
        ]);

        // Verify wallet was credited
        $wallet = UserWallet::where('user_id', $this->user->id)->first();
        $this->assertNotNull($wallet);
        $this->assertEquals(20.00, $wallet->balance);
    }

    public function test_paypal_webhook_success()
    {
        $webhookData = [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'id' => 'WH-test-webhook',
            'resource' => [
                'id' => 'paypal_test_123',
                'amount' => [
                    'value' => '35.50',
                    'currency_code' => 'USD'
                ],
                'custom_id' => $this->user->id
            ]
        ];

        $response = $this->postJson('/api/webhooks/paypal', $webhookData, [
            'PAYPAL-TRANSMISSION-SIG' => 'test_signature'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Webhook processed successfully'
                ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'transaction_id' => 'paypal_test_123',
            'provider' => 'paypal',
            'amount' => 35.50,
            'status' => 'completed'
        ]);
    }

    public function test_razorpay_webhook_success()
    {
        $webhookData = [
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'razorpay_test_123',
                        'amount' => 4500, // ₹45.00 in paise
                        'currency' => 'INR',
                        'description' => 'Test payment',
                        'notes' => [
                            'user_id' => $this->user->id
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/webhooks/razorpay', $webhookData, [
            'X-Razorpay-Signature' => 'test_signature'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Webhook processed successfully'
                ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'transaction_id' => 'razorpay_test_123',
            'provider' => 'razorpay',
            'amount' => 45.00,
            'currency' => 'INR',
            'status' => 'completed'
        ]);
    }

    public function test_webhook_with_invalid_signature()
    {
        $webhookData = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_invalid',
                    'amount' => 1000,
                    'currency' => 'usd',
                    'metadata' => [
                        'user_id' => $this->user->id
                    ]
                ]
            ]
        ];

        // Don't provide signature header
        $response = $this->postJson('/api/webhooks/stripe', $webhookData);

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Webhook processing failed'
                ]);

        // Should not create any transactions
        $this->assertDatabaseMissing('transactions', [
            'transaction_id' => 'pi_test_invalid'
        ]);
    }

    public function test_webhook_with_missing_user_id()
    {
        $webhookData = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_no_user',
                    'amount' => 1000,
                    'currency' => 'usd',
                    'metadata' => [] // No user_id
                ]
            ]
        ];

        $response = $this->postJson('/api/webhooks/stripe', $webhookData, [
            'Stripe-Signature' => 'test_signature'
        ]);

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Webhook processing failed'
                ]);
    }

    public function test_webhook_with_invalid_user()
    {
        $webhookData = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_invalid_user',
                    'amount' => 1000,
                    'currency' => 'usd',
                    'metadata' => [
                        'user_id' => 99999 // Non-existent user
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/webhooks/stripe', $webhookData, [
            'Stripe-Signature' => 'test_signature'
        ]);

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Webhook processing failed'
                ]);
    }

    public function test_webhook_idempotency()
    {
        $webhookData = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_idempotent',
                    'amount' => 1500,
                    'currency' => 'usd',
                    'metadata' => [
                        'user_id' => $this->user->id
                    ]
                ]
            ]
        ];

        // First webhook call
        $response1 = $this->postJson('/api/webhooks/stripe', $webhookData, [
            'Stripe-Signature' => 'test_signature'
        ]);

        $response1->assertStatus(200);

        // Second webhook call with same data
        $response2 = $this->postJson('/api/webhooks/stripe', $webhookData, [
            'Stripe-Signature' => 'test_signature'
        ]);

        $response2->assertStatus(200)
                 ->assertJsonPath('data.status', 'already_processed');

        // Should only have one transaction
        $transactionCount = Transaction::where('transaction_id', 'pi_test_idempotent')->count();
        $this->assertEquals(1, $transactionCount);
    }

    public function test_ignored_webhook_events()
    {
        $webhookData = [
            'type' => 'payment_intent.created', // Not a success event
            'data' => [
                'object' => [
                    'id' => 'pi_test_ignored',
                    'amount' => 1000,
                    'currency' => 'usd'
                ]
            ]
        ];

        $response = $this->postJson('/api/webhooks/stripe', $webhookData, [
            'Stripe-Signature' => 'test_signature'
        ]);

        $response->assertStatus(200)
                ->assertJsonPath('data.status', 'ignored')
                ->assertJsonPath('data.event_type', 'payment_intent.created');

        // Should not create any transactions
        $this->assertDatabaseMissing('transactions', [
            'transaction_id' => 'pi_test_ignored'
        ]);
    }

    public function test_test_webhook_endpoint()
    {
        $testData = [
            'test' => 'data',
            'timestamp' => now()->toISOString()
        ];

        $response = $this->postJson('/api/webhooks/test', $testData, [
            'X-Test-Header' => 'test_value'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Test webhook received'
                ])
                ->assertJsonStructure([
                    'data' => [
                        'timestamp',
                        'headers',
                        'body'
                    ]
                ]);
    }

    public function test_webhook_logging()
    {
        Log::shouldReceive('info')
           ->once()
           ->with('Stripe webhook received', [
               'event_type' => 'payment_intent.succeeded',
               'id' => 'evt_test_webhook'
           ]);

        Log::shouldReceive('info')
           ->once()
           ->with('Webhook processed successfully', Mockery::any());

        $webhookData = [
            'type' => 'payment_intent.succeeded',
            'id' => 'evt_test_webhook',
            'data' => [
                'object' => [
                    'id' => 'pi_test_logging',
                    'amount' => 1000,
                    'currency' => 'usd',
                    'metadata' => [
                        'user_id' => $this->user->id
                    ]
                ]
            ]
        ];

        $this->postJson('/api/webhooks/stripe', $webhookData, [
            'Stripe-Signature' => 'test_signature'
        ]);
    }

    public function test_webhook_error_logging()
    {
        Log::shouldReceive('info')
           ->once()
           ->with('Stripe webhook received', Mockery::any());

        Log::shouldReceive('error')
           ->once()
           ->with('Stripe webhook processing failed', Mockery::any());

        $webhookData = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_error',
                    'amount' => 1000,
                    'currency' => 'usd',
                    'metadata' => [] // Missing user_id will cause error
                ]
            ]
        ];

        $this->postJson('/api/webhooks/stripe', $webhookData, [
            'Stripe-Signature' => 'test_signature'
        ]);
    }

    public function test_webhook_rate_limiting()
    {
        // This test would require implementing rate limiting middleware
        // For now, we'll just verify the endpoint is accessible
        $webhookData = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_rate_limit',
                    'amount' => 1000,
                    'currency' => 'usd',
                    'metadata' => [
                        'user_id' => $this->user->id
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/webhooks/stripe', $webhookData, [
            'Stripe-Signature' => 'test_signature'
        ]);

        $response->assertStatus(200);
    }
}
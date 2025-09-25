<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\PaymentService;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\License;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $paymentService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentService = new PaymentService();
        $this->user = User::factory()->create();
    }

    public function test_process_stripe_webhook_success()
    {
        $webhookData = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_123',
                    'amount' => 1000, // $10.00 in cents
                    'currency' => 'usd',
                    'description' => 'Test payment',
                    'metadata' => [
                        'user_id' => $this->user->id
                    ]
                ]
            ]
        ];

        $result = $this->paymentService->processWebhook('stripe', $webhookData, 'test_signature');

        $this->assertEquals('processed', $result['status']);
        $this->assertEquals('pi_test_123', $result['transaction_id']);
        $this->assertEquals($this->user->id, $result['user_id']);
        $this->assertEquals(10.00, $result['amount']);

        // Check transaction was created
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'transaction_id' => 'pi_test_123',
            'provider' => 'stripe',
            'amount' => 10.00,
            'type' => 'credit',
            'status' => 'completed'
        ]);

        // Check wallet was credited
        $wallet = UserWallet::where('user_id', $this->user->id)->first();
        $this->assertNotNull($wallet);
        $this->assertEquals(10.00, $wallet->balance);
    }

    public function test_process_paypal_webhook_success()
    {
        $webhookData = [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'paypal_test_123',
                'amount' => [
                    'value' => '15.50',
                    'currency_code' => 'USD'
                ],
                'custom_id' => $this->user->id
            ]
        ];

        $result = $this->paymentService->processWebhook('paypal', $webhookData, 'test_signature');

        $this->assertEquals('processed', $result['status']);
        $this->assertEquals('paypal_test_123', $result['transaction_id']);
        $this->assertEquals(15.50, $result['amount']);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'transaction_id' => 'paypal_test_123',
            'provider' => 'paypal',
            'amount' => 15.50,
            'type' => 'credit',
            'status' => 'completed'
        ]);
    }

    public function test_process_razorpay_webhook_success()
    {
        $webhookData = [
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'razorpay_test_123',
                        'amount' => 2500, // ₹25.00 in paise
                        'currency' => 'INR',
                        'description' => 'Test payment',
                        'notes' => [
                            'user_id' => $this->user->id
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->paymentService->processWebhook('razorpay', $webhookData, 'test_signature');

        $this->assertEquals('processed', $result['status']);
        $this->assertEquals('razorpay_test_123', $result['transaction_id']);
        $this->assertEquals(25.00, $result['amount']);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'transaction_id' => 'razorpay_test_123',
            'provider' => 'razorpay',
            'amount' => 25.00,
            'currency' => 'INR',
            'type' => 'credit',
            'status' => 'completed'
        ]);
    }

    public function test_webhook_idempotency()
    {
        $webhookData = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_idempotent',
                    'amount' => 1000,
                    'currency' => 'usd',
                    'metadata' => [
                        'user_id' => $this->user->id
                    ]
                ]
            ]
        ];

        // Process webhook first time
        $result1 = $this->paymentService->processWebhook('stripe', $webhookData, 'test_signature');
        $this->assertEquals('processed', $result1['status']);

        // Process same webhook again
        $result2 = $this->paymentService->processWebhook('stripe', $webhookData, 'test_signature');
        $this->assertEquals('already_processed', $result2['status']);

        // Should only have one transaction
        $transactionCount = Transaction::where('transaction_id', 'pi_test_idempotent')->count();
        $this->assertEquals(1, $transactionCount);
    }

    public function test_webhook_fails_with_invalid_signature()
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

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid webhook signature');

        $this->paymentService->processWebhook('stripe', $webhookData, null);
    }

    public function test_webhook_fails_with_missing_user_id()
    {
        $webhookData = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_no_user',
                    'amount' => 1000,
                    'currency' => 'usd',
                    'metadata' => []
                ]
            ]
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User ID not found in payment metadata');

        $this->paymentService->processWebhook('stripe', $webhookData, 'test_signature');
    }

    public function test_webhook_fails_with_invalid_user()
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

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User not found: 99999');

        $this->paymentService->processWebhook('stripe', $webhookData, 'test_signature');
    }

    public function test_process_refund()
    {
        // Create original transaction
        $originalTransaction = Transaction::create([
            'user_id' => $this->user->id,
            'transaction_id' => 'original_123',
            'provider' => 'stripe',
            'amount' => 50.00,
            'currency' => 'USD',
            'type' => 'credit',
            'status' => 'completed',
            'description' => 'Original payment'
        ]);

        // Create wallet with sufficient balance
        $wallet = UserWallet::create([
            'user_id' => $this->user->id,
            'balance' => 50.00,
            'currency' => 'USD'
        ]);

        $refundTransaction = $this->paymentService->processRefund(
            $originalTransaction,
            25.00,
            'Customer requested refund'
        );

        $this->assertEquals('refund', $refundTransaction->type);
        $this->assertEquals('completed', $refundTransaction->status);
        $this->assertEquals(25.00, $refundTransaction->amount);
        $this->assertEquals('Customer requested refund', $refundTransaction->description);

        // Check wallet balance was debited
        $wallet->refresh();
        $this->assertEquals(25.00, $wallet->balance);
    }

    public function test_refund_fails_for_incomplete_transaction()
    {
        $pendingTransaction = Transaction::create([
            'user_id' => $this->user->id,
            'transaction_id' => 'pending_123',
            'provider' => 'stripe',
            'amount' => 50.00,
            'currency' => 'USD',
            'type' => 'credit',
            'status' => 'pending',
            'description' => 'Pending payment'
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Can only refund completed transactions');

        $this->paymentService->processRefund($pendingTransaction, 25.00, 'Test refund');
    }

    public function test_refund_fails_for_excessive_amount()
    {
        $originalTransaction = Transaction::create([
            'user_id' => $this->user->id,
            'transaction_id' => 'original_123',
            'provider' => 'stripe',
            'amount' => 50.00,
            'currency' => 'USD',
            'type' => 'credit',
            'status' => 'completed',
            'description' => 'Original payment'
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Refund amount cannot exceed original transaction amount');

        $this->paymentService->processRefund($originalTransaction, 75.00, 'Test refund');
    }

    public function test_get_transaction_statistics()
    {
        // Create test transactions
        Transaction::create([
            'user_id' => $this->user->id,
            'transaction_id' => 'completed_1',
            'provider' => 'stripe',
            'amount' => 100.00,
            'currency' => 'USD',
            'type' => 'credit',
            'status' => 'completed',
            'description' => 'Completed payment'
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'transaction_id' => 'pending_1',
            'provider' => 'paypal',
            'amount' => 50.00,
            'currency' => 'USD',
            'type' => 'credit',
            'status' => 'pending',
            'description' => 'Pending payment'
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'transaction_id' => 'failed_1',
            'provider' => 'razorpay',
            'amount' => 25.00,
            'currency' => 'USD',
            'type' => 'credit',
            'status' => 'failed',
            'description' => 'Failed payment'
        ]);

        $statistics = $this->paymentService->getTransactionStatistics();

        $this->assertEquals(3, $statistics['total_transactions']);
        $this->assertEquals(175.00, $statistics['total_amount']);
        $this->assertEquals(1, $statistics['completed_transactions']);
        $this->assertEquals(100.00, $statistics['completed_amount']);
        $this->assertEquals(1, $statistics['pending_transactions']);
        $this->assertEquals(1, $statistics['failed_transactions']);

        // Check provider breakdown
        $this->assertArrayHasKey('stripe', $statistics['by_provider']);
        $this->assertArrayHasKey('paypal', $statistics['by_provider']);
        $this->assertArrayHasKey('razorpay', $statistics['by_provider']);
    }

    public function test_auto_activate_pending_licenses()
    {
        // Create a pending license
        $license = License::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending'
        ]);

        // Create wallet with sufficient balance
        $wallet = UserWallet::create([
            'user_id' => $this->user->id,
            'balance' => 20.00,
            'currency' => 'USD'
        ]);

        // Process a payment that should trigger auto-activation
        $webhookData = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_auto_activate',
                    'amount' => 1000, // $10.00
                    'currency' => 'usd',
                    'metadata' => [
                        'user_id' => $this->user->id
                    ]
                ]
            ]
        ];

        $this->paymentService->processWebhook('stripe', $webhookData, 'test_signature');

        // Check license was activated
        $license->refresh();
        $this->assertEquals('active', $license->status);
        $this->assertNotNull($license->expires_at);

        // Check wallet was debited for activation
        $wallet->refresh();
        $this->assertEquals(20.00, $wallet->balance); // $30 total - $10 activation cost
    }

    public function test_unsupported_provider_throws_exception()
    {
        $webhookData = ['test' => 'data'];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported payment provider: unsupported');

        $this->paymentService->processWebhook('unsupported', $webhookData, 'signature');
    }

    public function test_ignored_webhook_events()
    {
        $webhookData = [
            'type' => 'payment_intent.created', // Not a success event
            'data' => [
                'object' => [
                    'id' => 'pi_ignored',
                    'amount' => 1000,
                    'currency' => 'usd'
                ]
            ]
        ];

        $result = $this->paymentService->processWebhook('stripe', $webhookData, 'test_signature');

        $this->assertEquals('ignored', $result['status']);
        $this->assertEquals('payment_intent.created', $result['event_type']);

        // Should not create any transactions
        $this->assertDatabaseMissing('transactions', [
            'transaction_id' => 'pi_ignored'
        ]);
    }
}
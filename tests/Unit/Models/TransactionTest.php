<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_transaction_belongs_to_user()
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id
        ]);

        $this->assertInstanceOf(User::class, $transaction->user);
        $this->assertEquals($this->user->id, $transaction->user->id);
    }

    public function test_transaction_status_methods()
    {
        $completedTransaction = Transaction::factory()->create([
            'status' => Transaction::STATUS_COMPLETED
        ]);

        $pendingTransaction = Transaction::factory()->create([
            'status' => Transaction::STATUS_PENDING
        ]);

        $failedTransaction = Transaction::factory()->create([
            'status' => Transaction::STATUS_FAILED
        ]);

        $cancelledTransaction = Transaction::factory()->create([
            'status' => Transaction::STATUS_CANCELLED
        ]);

        $this->assertTrue($completedTransaction->isCompleted());
        $this->assertFalse($completedTransaction->isPending());
        $this->assertFalse($completedTransaction->isFailed());
        $this->assertFalse($completedTransaction->isCancelled());

        $this->assertTrue($pendingTransaction->isPending());
        $this->assertFalse($pendingTransaction->isCompleted());

        $this->assertTrue($failedTransaction->isFailed());
        $this->assertFalse($failedTransaction->isCompleted());

        $this->assertTrue($cancelledTransaction->isCancelled());
        $this->assertFalse($cancelledTransaction->isCompleted());
    }

    public function test_mark_transaction_status_methods()
    {
        $transaction = Transaction::factory()->create([
            'status' => Transaction::STATUS_PENDING
        ]);

        $transaction->markAsCompleted();
        $this->assertEquals(Transaction::STATUS_COMPLETED, $transaction->fresh()->status);

        $transaction->markAsFailed();
        $this->assertEquals(Transaction::STATUS_FAILED, $transaction->fresh()->status);

        $transaction->markAsCancelled();
        $this->assertEquals(Transaction::STATUS_CANCELLED, $transaction->fresh()->status);
    }

    public function test_formatted_amount_attribute()
    {
        $transaction = Transaction::factory()->create([
            'amount' => 123.45,
            'currency' => 'USD'
        ]);

        $this->assertEquals('123.45 USD', $transaction->formatted_amount);
    }

    public function test_transaction_scopes()
    {
        // Clear any existing transactions
        Transaction::query()->delete();

        // Create transactions with specific statuses only
        $completedTransaction = Transaction::factory()->create(['status' => Transaction::STATUS_COMPLETED]);
        $pendingTransaction = Transaction::factory()->create(['status' => Transaction::STATUS_PENDING]);
        $failedTransaction = Transaction::factory()->create(['status' => Transaction::STATUS_FAILED]);

        // Create transactions with specific types only
        $creditTransaction = Transaction::factory()->create(['type' => Transaction::TYPE_CREDIT]);
        $debitTransaction = Transaction::factory()->create(['type' => Transaction::TYPE_DEBIT]);
        $refundTransaction = Transaction::factory()->create(['type' => Transaction::TYPE_REFUND]);

        // Create transactions with specific providers only
        $stripeTransaction = Transaction::factory()->create(['provider' => Transaction::PROVIDER_STRIPE]);
        $paypalTransaction = Transaction::factory()->create(['provider' => Transaction::PROVIDER_PAYPAL]);

        // Test status scopes
        $this->assertGreaterThanOrEqual(1, Transaction::completed()->count());
        $this->assertGreaterThanOrEqual(1, Transaction::pending()->count());
        $this->assertGreaterThanOrEqual(1, Transaction::failed()->count());

        // Test type scopes
        $this->assertGreaterThanOrEqual(1, Transaction::credits()->count());
        $this->assertGreaterThanOrEqual(1, Transaction::debits()->count());
        $this->assertGreaterThanOrEqual(1, Transaction::refunds()->count());

        // Test provider scope
        $this->assertGreaterThanOrEqual(1, Transaction::provider(Transaction::PROVIDER_STRIPE)->count());
        $this->assertGreaterThanOrEqual(1, Transaction::provider(Transaction::PROVIDER_PAYPAL)->count());

        // Test type scope
        $this->assertGreaterThanOrEqual(1, Transaction::type(Transaction::TYPE_CREDIT)->count());
    }

    public function test_transaction_casts()
    {
        $transaction = Transaction::factory()->create([
            'amount' => '123.45',
            'provider_data' => ['key' => 'value'],
            'metadata' => ['meta' => 'data']
        ]);

        $this->assertIsFloat($transaction->amount);
        $this->assertEquals(123.45, $transaction->amount);
        $this->assertIsArray($transaction->provider_data);
        $this->assertIsArray($transaction->metadata);
        $this->assertEquals(['key' => 'value'], $transaction->provider_data);
        $this->assertEquals(['meta' => 'data'], $transaction->metadata);
    }

    public function test_transaction_constants()
    {
        $this->assertEquals('credit', Transaction::TYPE_CREDIT);
        $this->assertEquals('debit', Transaction::TYPE_DEBIT);
        $this->assertEquals('refund', Transaction::TYPE_REFUND);

        $this->assertEquals('pending', Transaction::STATUS_PENDING);
        $this->assertEquals('completed', Transaction::STATUS_COMPLETED);
        $this->assertEquals('failed', Transaction::STATUS_FAILED);
        $this->assertEquals('cancelled', Transaction::STATUS_CANCELLED);

        $this->assertEquals('stripe', Transaction::PROVIDER_STRIPE);
        $this->assertEquals('paypal', Transaction::PROVIDER_PAYPAL);
        $this->assertEquals('razorpay', Transaction::PROVIDER_RAZORPAY);
    }
}
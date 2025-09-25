<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\UserWallet;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserWalletTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private UserWallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->wallet = UserWallet::factory()->create([
            'user_id' => $this->user->id,
            'balance' => 100.00
        ]);
    }

    public function test_wallet_belongs_to_user()
    {
        $this->assertInstanceOf(User::class, $this->wallet->user);
        $this->assertEquals($this->user->id, $this->wallet->user->id);
    }

    public function test_wallet_has_many_transactions()
    {
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => Transaction::TYPE_CREDIT
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => Transaction::TYPE_DEBIT
        ]);

        $this->assertEquals(2, $this->wallet->transactions()->count());
    }

    public function test_credit_wallet()
    {
        $initialBalance = $this->wallet->balance;
        $creditAmount = 50.00;

        $transaction = $this->wallet->credit($creditAmount, 'Test credit', ['test' => 'metadata']);

        $this->wallet->refresh();
        $this->assertEquals($initialBalance + $creditAmount, $this->wallet->balance);
        $this->assertEquals(Transaction::TYPE_CREDIT, $transaction->type);
        $this->assertEquals(Transaction::STATUS_COMPLETED, $transaction->status);
        $this->assertEquals($creditAmount, $transaction->amount);
        $this->assertEquals('Test credit', $transaction->description);
        $this->assertEquals(['test' => 'metadata'], $transaction->metadata);
    }

    public function test_debit_wallet()
    {
        $initialBalance = $this->wallet->balance;
        $debitAmount = 30.00;

        $transaction = $this->wallet->debit($debitAmount, 'Test debit', ['test' => 'metadata']);

        $this->wallet->refresh();
        $this->assertEquals($initialBalance - $debitAmount, $this->wallet->balance);
        $this->assertEquals(Transaction::TYPE_DEBIT, $transaction->type);
        $this->assertEquals(Transaction::STATUS_COMPLETED, $transaction->status);
        $this->assertEquals($debitAmount, $transaction->amount);
        $this->assertEquals('Test debit', $transaction->description);
        $this->assertEquals(['test' => 'metadata'], $transaction->metadata);
    }

    public function test_debit_wallet_insufficient_balance()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient wallet balance');

        $this->wallet->debit(150.00, 'Test debit'); // More than available balance
    }

    public function test_has_sufficient_balance()
    {
        $this->assertTrue($this->wallet->hasSufficientBalance(50.00));
        $this->assertTrue($this->wallet->hasSufficientBalance(100.00));
        $this->assertFalse($this->wallet->hasSufficientBalance(150.00));
    }

    public function test_formatted_balance_attribute()
    {
        $wallet = UserWallet::factory()->create([
            'balance' => 123.45,
            'currency' => 'USD'
        ]);

        $this->assertEquals('123.45 USD', $wallet->formatted_balance);
    }

    public function test_get_total_credits()
    {
        // Create credit transactions
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => Transaction::TYPE_CREDIT,
            'amount' => 50.00,
            'status' => Transaction::STATUS_COMPLETED
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => Transaction::TYPE_CREDIT,
            'amount' => 30.00,
            'status' => Transaction::STATUS_COMPLETED
        ]);

        // Create a pending credit (should not be counted)
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => Transaction::TYPE_CREDIT,
            'amount' => 20.00,
            'status' => Transaction::STATUS_PENDING
        ]);

        $this->assertEquals(80.00, $this->wallet->getTotalCredits());
    }

    public function test_get_total_debits()
    {
        // Create debit transactions
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => Transaction::TYPE_DEBIT,
            'amount' => 25.00,
            'status' => Transaction::STATUS_COMPLETED
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => Transaction::TYPE_DEBIT,
            'amount' => 15.00,
            'status' => Transaction::STATUS_COMPLETED
        ]);

        // Create a failed debit (should not be counted)
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => Transaction::TYPE_DEBIT,
            'amount' => 10.00,
            'status' => Transaction::STATUS_FAILED
        ]);

        $this->assertEquals(40.00, $this->wallet->getTotalDebits());
    }

    public function test_get_recent_transactions()
    {
        // Create multiple transactions
        for ($i = 0; $i < 15; $i++) {
            Transaction::factory()->create([
                'user_id' => $this->user->id,
                'created_at' => now()->subMinutes($i)
            ]);
        }

        $recentTransactions = $this->wallet->getRecentTransactions(10);

        $this->assertEquals(10, $recentTransactions->count());
        
        // Should be ordered by created_at desc (most recent first)
        $this->assertTrue(
            $recentTransactions->first()->created_at->gte(
                $recentTransactions->last()->created_at
            )
        );
    }

    public function test_get_or_create_for_user_creates_new_wallet()
    {
        $newUser = User::factory()->create();
        
        $this->assertDatabaseMissing('user_wallets', [
            'user_id' => $newUser->id
        ]);

        $wallet = UserWallet::getOrCreateForUser($newUser, 'EUR');

        $this->assertDatabaseHas('user_wallets', [
            'user_id' => $newUser->id,
            'balance' => 0.00,
            'currency' => 'EUR'
        ]);

        $this->assertEquals($newUser->id, $wallet->user_id);
        $this->assertEquals(0.00, $wallet->balance);
        $this->assertEquals('EUR', $wallet->currency);
    }

    public function test_get_or_create_for_user_returns_existing_wallet()
    {
        $existingWallet = $this->wallet;
        
        $wallet = UserWallet::getOrCreateForUser($this->user);

        $this->assertEquals($existingWallet->id, $wallet->id);
        $this->assertEquals($existingWallet->balance, $wallet->balance);
    }

    public function test_wallet_casts()
    {
        $wallet = UserWallet::factory()->create([
            'balance' => '123.45',
            'metadata' => ['key' => 'value']
        ]);

        $this->assertIsFloat($wallet->balance);
        $this->assertEquals(123.45, $wallet->balance);
        $this->assertIsArray($wallet->metadata);
        $this->assertEquals(['key' => 'value'], $wallet->metadata);
    }

    public function test_credit_with_default_description()
    {
        $transaction = $this->wallet->credit(25.00);

        $this->assertEquals('Wallet credit', $transaction->description);
        $this->assertEquals([], $transaction->metadata);
    }

    public function test_debit_with_default_description()
    {
        $transaction = $this->wallet->debit(25.00);

        $this->assertEquals('Wallet debit', $transaction->description);
        $this->assertEquals([], $transaction->metadata);
    }
}
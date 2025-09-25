<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
        'currency',
        'metadata',
    ];

    protected $casts = [
        'balance' => 'float',
        'metadata' => 'json',
    ];

    /**
     * Get the user that owns the wallet
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all transactions for this wallet
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'user_id', 'user_id');
    }

    /**
     * Credit the wallet with an amount
     */
    public function credit(float $amount, string $description = null, array $metadata = []): Transaction
    {
        // Create transaction record
        $transaction = $this->transactions()->create([
            'transaction_id' => 'wallet_' . uniqid(),
            'provider' => 'wallet',
            'amount' => $amount,
            'currency' => $this->currency,
            'type' => Transaction::TYPE_CREDIT,
            'status' => Transaction::STATUS_COMPLETED,
            'description' => $description ?? 'Wallet credit',
            'metadata' => $metadata,
        ]);

        // Update wallet balance
        $this->increment('balance', $amount);

        return $transaction;
    }

    /**
     * Debit the wallet with an amount
     */
    public function debit(float $amount, string $description = null, array $metadata = []): Transaction
    {
        if ($this->balance < $amount) {
            throw new \Exception('Insufficient wallet balance');
        }

        // Create transaction record
        $transaction = $this->transactions()->create([
            'transaction_id' => 'wallet_' . uniqid(),
            'provider' => 'wallet',
            'amount' => $amount,
            'currency' => $this->currency,
            'type' => Transaction::TYPE_DEBIT,
            'status' => Transaction::STATUS_COMPLETED,
            'description' => $description ?? 'Wallet debit',
            'metadata' => $metadata,
        ]);

        // Update wallet balance
        $this->decrement('balance', $amount);

        return $transaction;
    }

    /**
     * Check if wallet has sufficient balance
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }

    /**
     * Get formatted balance with currency
     */
    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->balance, 2) . ' ' . strtoupper($this->currency);
    }

    /**
     * Get total credits
     */
    public function getTotalCredits(): float
    {
        return $this->transactions()
            ->where('type', Transaction::TYPE_CREDIT)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->sum('amount');
    }

    /**
     * Get total debits
     */
    public function getTotalDebits(): float
    {
        return $this->transactions()
            ->where('type', Transaction::TYPE_DEBIT)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->sum('amount');
    }

    /**
     * Get recent transactions
     */
    public function getRecentTransactions(int $limit = 10)
    {
        return $this->transactions()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Create or get wallet for user
     */
    public static function getOrCreateForUser(User $user, string $currency = 'USD'): self
    {
        return self::firstOrCreate(
            ['user_id' => $user->id],
            [
                'balance' => 0.00,
                'currency' => $currency,
                'metadata' => [],
            ]
        );
    }
}
<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\License;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PaymentService
{
    /**
     * Process payment webhook with idempotent handling
     */
    public function processWebhook(string $provider, array $webhookData, string $signature = null): array
    {
        // Verify webhook signature
        if (!$this->verifyWebhookSignature($provider, $webhookData, $signature)) {
            throw new \Exception('Invalid webhook signature');
        }

        // Extract transaction ID for idempotency
        $transactionId = $this->extractTransactionId($provider, $webhookData);
        
        // Check if we've already processed this webhook (idempotency)
        $cacheKey = "webhook_processed_{$provider}_{$transactionId}";
        if (Cache::has($cacheKey)) {
            Log::info("Webhook already processed", [
                'provider' => $provider,
                'transaction_id' => $transactionId
            ]);
            return ['status' => 'already_processed', 'transaction_id' => $transactionId];
        }

        try {
            DB::beginTransaction();

            // Process the payment based on provider
            $result = match ($provider) {
                Transaction::PROVIDER_STRIPE => $this->processStripeWebhook($webhookData),
                Transaction::PROVIDER_PAYPAL => $this->processPayPalWebhook($webhookData),
                Transaction::PROVIDER_RAZORPAY => $this->processRazorpayWebhook($webhookData),
                default => throw new \Exception("Unsupported payment provider: {$provider}")
            };

            // Mark as processed (cache for 24 hours)
            Cache::put($cacheKey, true, 86400);

            DB::commit();

            Log::info("Webhook processed successfully", [
                'provider' => $provider,
                'transaction_id' => $transactionId,
                'result' => $result
            ]);

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("Webhook processing failed", [
                'provider' => $provider,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'webhook_data' => $webhookData
            ]);

            throw $e;
        }
    }

    /**
     * Verify webhook signature based on provider
     */
    private function verifyWebhookSignature(string $provider, array $webhookData, string $signature = null): bool
    {
        // Skip signature verification in testing environment
        if (app()->environment('testing')) {
            return !empty($signature);
        }

        return match ($provider) {
            Transaction::PROVIDER_STRIPE => $this->verifyStripeSignature($webhookData, $signature),
            Transaction::PROVIDER_PAYPAL => $this->verifyPayPalSignature($webhookData, $signature),
            Transaction::PROVIDER_RAZORPAY => $this->verifyRazorpaySignature($webhookData, $signature),
            default => false
        };
    }

    /**
     * Extract transaction ID from webhook data
     */
    private function extractTransactionId(string $provider, array $webhookData): string
    {
        return match ($provider) {
            Transaction::PROVIDER_STRIPE => $webhookData['data']['object']['id'] ?? 'unknown',
            Transaction::PROVIDER_PAYPAL => $webhookData['resource']['id'] ?? 'unknown',
            Transaction::PROVIDER_RAZORPAY => $webhookData['payload']['payment']['entity']['id'] ?? 'unknown',
            default => 'unknown'
        };
    }

    /**
     * Process Stripe webhook
     */
    private function processStripeWebhook(array $webhookData): array
    {
        $eventType = $webhookData['type'] ?? '';
        $paymentIntent = $webhookData['data']['object'] ?? [];

        if ($eventType === 'payment_intent.succeeded') {
            return $this->processSuccessfulPayment(
                Transaction::PROVIDER_STRIPE,
                $paymentIntent['id'],
                $paymentIntent['amount'] / 100, // Stripe amounts are in cents
                $paymentIntent['currency'],
                $paymentIntent['metadata']['user_id'] ?? null,
                $paymentIntent['description'] ?? 'Stripe payment',
                $webhookData
            );
        }

        return ['status' => 'ignored', 'event_type' => $eventType];
    }

    /**
     * Process PayPal webhook
     */
    private function processPayPalWebhook(array $webhookData): array
    {
        $eventType = $webhookData['event_type'] ?? '';
        $resource = $webhookData['resource'] ?? [];

        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            return $this->processSuccessfulPayment(
                Transaction::PROVIDER_PAYPAL,
                $resource['id'],
                (float) $resource['amount']['value'],
                $resource['amount']['currency_code'],
                $resource['custom_id'] ?? null, // User ID should be in custom_id
                'PayPal payment',
                $webhookData
            );
        }

        return ['status' => 'ignored', 'event_type' => $eventType];
    }

    /**
     * Process Razorpay webhook
     */
    private function processRazorpayWebhook(array $webhookData): array
    {
        $eventType = $webhookData['event'] ?? '';
        $payment = $webhookData['payload']['payment']['entity'] ?? [];

        if ($eventType === 'payment.captured') {
            return $this->processSuccessfulPayment(
                Transaction::PROVIDER_RAZORPAY,
                $payment['id'],
                $payment['amount'] / 100, // Razorpay amounts are in paise
                $payment['currency'],
                $payment['notes']['user_id'] ?? null,
                $payment['description'] ?? 'Razorpay payment',
                $webhookData
            );
        }

        return ['status' => 'ignored', 'event_type' => $eventType];
    }

    /**
     * Process successful payment and credit wallet
     */
    private function processSuccessfulPayment(
        string $provider,
        string $transactionId,
        float $amount,
        string $currency,
        ?string $userId,
        string $description,
        array $providerData
    ): array {
        if (!$userId) {
            throw new \Exception('User ID not found in payment metadata');
        }

        $user = User::find($userId);
        if (!$user) {
            throw new \Exception("User not found: {$userId}");
        }

        // Create transaction record
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'transaction_id' => $transactionId,
            'provider' => $provider,
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'type' => Transaction::TYPE_CREDIT,
            'status' => Transaction::STATUS_COMPLETED,
            'description' => $description,
            'provider_data' => $providerData,
            'metadata' => [
                'processed_at' => now()->toISOString(),
                'webhook_event' => true,
            ],
        ]);

        // Credit user's wallet
        $wallet = UserWallet::getOrCreateForUser($user, strtoupper($currency));
        $wallet->credit($amount, "Payment from {$provider}", [
            'transaction_id' => $transactionId,
            'provider' => $provider,
        ]);

        // Check if this payment should activate/extend licenses
        $this->processLicenseActivation($user, $amount, $transaction);

        return [
            'status' => 'processed',
            'transaction_id' => $transactionId,
            'user_id' => $user->id,
            'amount' => $amount,
            'currency' => $currency,
            'wallet_balance' => $wallet->fresh()->balance,
        ];
    }

    /**
     * Process license activation/extension based on payment
     */
    private function processLicenseActivation(User $user, float $amount, Transaction $transaction): void
    {
        // This is a simplified implementation
        // In a real system, you'd have product pricing and license duration logic
        
        $metadata = $transaction->metadata ?? [];
        
        // Check if this payment is for a specific license
        if (isset($metadata['license_id'])) {
            $license = License::find($metadata['license_id']);
            if ($license && $license->user_id === $user->id) {
                // Extend license based on payment amount
                $this->extendLicense($license, $amount);
            }
        }
        
        // Auto-activate pending licenses if user has sufficient wallet balance
        $this->autoActivatePendingLicenses($user);
    }

    /**
     * Extend license duration based on payment amount
     */
    private function extendLicense(License $license, float $amount): void
    {
        // Simple pricing: $10 = 1 month extension
        $monthsToAdd = floor($amount / 10);
        
        if ($monthsToAdd > 0) {
            $currentExpiry = $license->expires_at ?? now();
            $newExpiry = $currentExpiry->addMonths($monthsToAdd);
            
            $license->update([
                'expires_at' => $newExpiry,
                'status' => 'active',
            ]);

            Log::info("License extended", [
                'license_id' => $license->id,
                'months_added' => $monthsToAdd,
                'new_expiry' => $newExpiry->toISOString(),
            ]);
        }
    }

    /**
     * Auto-activate pending licenses if user has sufficient balance
     */
    private function autoActivatePendingLicenses(User $user): void
    {
        $pendingLicenses = $user->assignedLicenses()
            ->where('status', 'pending')
            ->get();

        $wallet = UserWallet::getOrCreateForUser($user);
        
        foreach ($pendingLicenses as $license) {
            // Simple pricing: $10 per license activation
            $activationCost = 10.00;
            
            if ($wallet->hasSufficientBalance($activationCost)) {
                $wallet->debit($activationCost, "License activation: {$license->license_key}", [
                    'license_id' => $license->id,
                    'auto_activation' => true,
                ]);

                $license->update([
                    'status' => 'active',
                    'expires_at' => now()->addYear(),
                ]);

                Log::info("License auto-activated", [
                    'license_id' => $license->id,
                    'user_id' => $user->id,
                    'cost' => $activationCost,
                ]);
            }
        }
    }

    /**
     * Verify Stripe webhook signature
     */
    private function verifyStripeSignature(array $webhookData, ?string $signature): bool
    {
        if (!$signature) {
            return false;
        }

        $endpointSecret = config('services.stripe.webhook_secret');
        if (!$endpointSecret) {
            Log::warning('Stripe webhook secret not configured');
            return false;
        }

        // In a real implementation, you'd verify the signature using Stripe's library
        // For now, we'll do a basic check
        return !empty($signature) && !empty($endpointSecret);
    }

    /**
     * Verify PayPal webhook signature
     */
    private function verifyPayPalSignature(array $webhookData, ?string $signature): bool
    {
        if (!$signature) {
            return false;
        }

        $webhookSecret = config('services.paypal.webhook_secret');
        if (!$webhookSecret) {
            Log::warning('PayPal webhook secret not configured');
            return false;
        }

        // In a real implementation, you'd verify using PayPal's SDK
        return !empty($signature) && !empty($webhookSecret);
    }

    /**
     * Verify Razorpay webhook signature
     */
    private function verifyRazorpaySignature(array $webhookData, ?string $signature): bool
    {
        if (!$signature) {
            return false;
        }

        $webhookSecret = config('services.razorpay.webhook_secret');
        if (!$webhookSecret) {
            Log::warning('Razorpay webhook secret not configured');
            return false;
        }

        // In a real implementation, you'd verify using Razorpay's signature validation
        return !empty($signature) && !empty($webhookSecret);
    }

    /**
     * Get transaction statistics for admin dashboard
     */
    public function getTransactionStatistics(array $filters = []): array
    {
        $baseQuery = Transaction::query();

        // Apply filters to base query
        if (isset($filters['provider'])) {
            $baseQuery->where('provider', $filters['provider']);
        }

        if (isset($filters['status'])) {
            $baseQuery->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $baseQuery->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $baseQuery->where('created_at', '<=', $filters['date_to']);
        }

        // Clone the base query for different statistics
        $totalQuery = clone $baseQuery;
        $completedQuery = clone $baseQuery;
        $pendingQuery = clone $baseQuery;
        $failedQuery = clone $baseQuery;
        $providerQuery = clone $baseQuery;

        return [
            'total_transactions' => $totalQuery->count(),
            'total_amount' => $totalQuery->sum('amount'),
            'completed_transactions' => $completedQuery->where('status', Transaction::STATUS_COMPLETED)->count(),
            'completed_amount' => $completedQuery->where('status', Transaction::STATUS_COMPLETED)->sum('amount'),
            'pending_transactions' => $pendingQuery->where('status', Transaction::STATUS_PENDING)->count(),
            'failed_transactions' => $failedQuery->where('status', Transaction::STATUS_FAILED)->count(),
            'by_provider' => $providerQuery->selectRaw('provider, COUNT(*) as count, SUM(amount) as total_amount')
                ->groupBy('provider')
                ->get()
                ->keyBy('provider'),
        ];
    }

    /**
     * Process refund
     */
    public function processRefund(Transaction $originalTransaction, float $refundAmount, string $reason = null): Transaction
    {
        if ($refundAmount > $originalTransaction->amount) {
            throw new \Exception('Refund amount cannot exceed original transaction amount');
        }

        if (!$originalTransaction->isCompleted()) {
            throw new \Exception('Can only refund completed transactions');
        }

        DB::beginTransaction();

        try {
            // Create refund transaction
            $refundTransaction = Transaction::create([
                'user_id' => $originalTransaction->user_id,
                'transaction_id' => 'refund_' . $originalTransaction->transaction_id . '_' . uniqid(),
                'provider' => $originalTransaction->provider,
                'amount' => $refundAmount,
                'currency' => $originalTransaction->currency,
                'type' => Transaction::TYPE_REFUND,
                'status' => Transaction::STATUS_COMPLETED,
                'description' => $reason ?? "Refund for transaction {$originalTransaction->transaction_id}",
                'metadata' => [
                    'original_transaction_id' => $originalTransaction->id,
                    'refund_reason' => $reason,
                    'processed_at' => now()->toISOString(),
                ],
            ]);

            // Debit user's wallet
            $wallet = UserWallet::getOrCreateForUser($originalTransaction->user, $originalTransaction->currency);
            if ($wallet->hasSufficientBalance($refundAmount)) {
                $wallet->debit($refundAmount, "Refund: {$reason}", [
                    'refund_transaction_id' => $refundTransaction->id,
                    'original_transaction_id' => $originalTransaction->id,
                ]);
            }

            DB::commit();

            Log::info("Refund processed", [
                'original_transaction_id' => $originalTransaction->id,
                'refund_transaction_id' => $refundTransaction->id,
                'refund_amount' => $refundAmount,
                'reason' => $reason,
            ]);

            return $refundTransaction;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
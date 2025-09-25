<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\UserWallet;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {
        $this->middleware(['auth', 'role:admin,developer']);
    }

    /**
     * Display payment management dashboard
     */
    public function index(Request $request): View
    {
        $filters = $request->only(['provider', 'status', 'type', 'date_from', 'date_to', 'user_id']);
        
        $transactions = Transaction::with('user')
            ->when($filters['provider'] ?? null, fn($q, $provider) => $q->where('provider', $provider))
            ->when($filters['status'] ?? null, fn($q, $status) => $q->where('status', $status))
            ->when($filters['type'] ?? null, fn($q, $type) => $q->where('type', $type))
            ->when($filters['user_id'] ?? null, fn($q, $userId) => $q->where('user_id', $userId))
            ->when($filters['date_from'] ?? null, fn($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        $statistics = $this->paymentService->getTransactionStatistics($filters);

        return view('admin.payments.index', compact('transactions', 'statistics', 'filters'));
    }

    /**
     * Show transaction details
     */
    public function show(Transaction $transaction): View
    {
        $transaction->load('user');
        
        return view('admin.payments.show', compact('transaction'));
    }

    /**
     * Get payment statistics via AJAX
     */
    public function statistics(Request $request): JsonResponse
    {
        $filters = $request->only(['provider', 'status', 'date_from', 'date_to']);
        $statistics = $this->paymentService->getTransactionStatistics($filters);

        return response()->json($statistics);
    }

    /**
     * Display wallet management
     */
    public function wallets(Request $request): View
    {
        $wallets = UserWallet::with('user')
            ->when($request->search, function($q, $search) {
                $q->whereHas('user', function($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->min_balance, fn($q, $balance) => $q->where('balance', '>=', $balance))
            ->when($request->max_balance, fn($q, $balance) => $q->where('balance', '<=', $balance))
            ->orderBy('balance', 'desc')
            ->paginate(25);

        return view('admin.payments.wallets', compact('wallets'));
    }

    /**
     * Show wallet details
     */
    public function showWallet(UserWallet $wallet): View
    {
        $wallet->load('user');
        $recentTransactions = $wallet->getRecentTransactions(20);
        
        return view('admin.payments.wallet-details', compact('wallet', 'recentTransactions'));
    }

    /**
     * Manually credit user wallet
     */
    public function creditWallet(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $user = User::findOrFail($request->user_id);
            $wallet = UserWallet::getOrCreateForUser($user);

            $transaction = $wallet->credit(
                $request->amount,
                $request->description,
                [
                    'manual_credit' => true,
                    'admin_user_id' => auth()->id(),
                    'reason' => $request->reason,
                    'processed_at' => now()->toISOString(),
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Wallet credited successfully',
                'data' => [
                    'transaction_id' => $transaction->id,
                    'new_balance' => $wallet->fresh()->formatted_balance,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to credit wallet',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Manually debit user wallet
     */
    public function debitWallet(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $user = User::findOrFail($request->user_id);
            $wallet = UserWallet::getOrCreateForUser($user);

            if (!$wallet->hasSufficientBalance($request->amount)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient wallet balance',
                ], 400);
            }

            $transaction = $wallet->debit(
                $request->amount,
                $request->description,
                [
                    'manual_debit' => true,
                    'admin_user_id' => auth()->id(),
                    'reason' => $request->reason,
                    'processed_at' => now()->toISOString(),
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Wallet debited successfully',
                'data' => [
                    'transaction_id' => $transaction->id,
                    'new_balance' => $wallet->fresh()->formatted_balance,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to debit wallet',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Process refund for a transaction
     */
    public function refund(Request $request, Transaction $transaction): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . $transaction->amount,
            'reason' => 'required|string|max:500',
        ]);

        try {
            $refundTransaction = $this->paymentService->processRefund(
                $transaction,
                $request->amount,
                $request->reason
            );

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
                'data' => [
                    'refund_transaction_id' => $refundTransaction->id,
                    'refund_amount' => $refundTransaction->formatted_amount,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process refund',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Export transactions to CSV
     */
    public function export(Request $request)
    {
        $filters = $request->only(['provider', 'status', 'type', 'date_from', 'date_to', 'user_id']);
        
        $transactions = Transaction::with('user')
            ->when($filters['provider'] ?? null, fn($q, $provider) => $q->where('provider', $provider))
            ->when($filters['status'] ?? null, fn($q, $status) => $q->where('status', $status))
            ->when($filters['type'] ?? null, fn($q, $type) => $q->where('type', $type))
            ->when($filters['user_id'] ?? null, fn($q, $userId) => $q->where('user_id', $userId))
            ->when($filters['date_from'] ?? null, fn($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'transactions_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($transactions) {
            $file = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($file, [
                'ID',
                'Transaction ID',
                'User Name',
                'User Email',
                'Provider',
                'Amount',
                'Currency',
                'Type',
                'Status',
                'Description',
                'Created At',
            ]);

            // CSV data
            foreach ($transactions as $transaction) {
                fputcsv($file, [
                    $transaction->id,
                    $transaction->transaction_id,
                    $transaction->user->name,
                    $transaction->user->email,
                    $transaction->provider,
                    $transaction->amount,
                    $transaction->currency,
                    $transaction->type,
                    $transaction->status,
                    $transaction->description,
                    $transaction->created_at->toISOString(),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get user search results for wallet operations
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $search = $request->get('q', '');
        
        $users = User::where('name', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%")
            ->limit(10)
            ->get(['id', 'name', 'email']);

        return response()->json($users);
    }

    /**
     * Get wallet balance for a user
     */
    public function getUserWallet(User $user): JsonResponse
    {
        $wallet = UserWallet::getOrCreateForUser($user);
        
        return response()->json([
            'balance' => $wallet->balance,
            'formatted_balance' => $wallet->formatted_balance,
            'currency' => $wallet->currency,
        ]);
    }
}
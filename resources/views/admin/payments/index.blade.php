@extends('layouts.app')

@section('title', 'Payment Management')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Payment Management</h1>
        <div class="flex space-x-2">
            <a href="{{ route('admin.payments.export', request()->query()) }}" 
               class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                Export CSV
            </a>
            <a href="{{ route('admin.wallets.index') }}" 
               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                Manage Wallets
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total Transactions</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ number_format($statistics['total_transactions']) }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-green-100 rounded-lg">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Completed</p>
                    <p class="text-2xl font-semibold text-gray-900">${{ number_format($statistics['completed_amount'], 2) }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-yellow-100 rounded-lg">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Pending</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ number_format($statistics['pending_transactions']) }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-red-100 rounded-lg">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Failed</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ number_format($statistics['failed_transactions']) }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Provider</label>
                    <select name="provider" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        <option value="">All Providers</option>
                        <option value="stripe" {{ ($filters['provider'] ?? '') === 'stripe' ? 'selected' : '' }}>Stripe</option>
                        <option value="paypal" {{ ($filters['provider'] ?? '') === 'paypal' ? 'selected' : '' }}>PayPal</option>
                        <option value="razorpay" {{ ($filters['provider'] ?? '') === 'razorpay' ? 'selected' : '' }}>Razorpay</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        <option value="">All Statuses</option>
                        <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="completed" {{ ($filters['status'] ?? '') === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="failed" {{ ($filters['status'] ?? '') === 'failed' ? 'selected' : '' }}>Failed</option>
                        <option value="cancelled" {{ ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select name="type" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        <option value="">All Types</option>
                        <option value="credit" {{ ($filters['type'] ?? '') === 'credit' ? 'selected' : '' }}>Credit</option>
                        <option value="debit" {{ ($filters['type'] ?? '') === 'debit' ? 'selected' : '' }}>Debit</option>
                        <option value="refund" {{ ($filters['type'] ?? '') === 'refund' ? 'selected' : '' }}>Refund</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" 
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" 
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>

                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                        Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Provider</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($transactions as $transaction)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">{{ $transaction->transaction_id }}</div>
                            <div class="text-sm text-gray-500">ID: {{ $transaction->id }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">{{ $transaction->user->name }}</div>
                            <div class="text-sm text-gray-500">{{ $transaction->user->email }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $transaction->provider === 'stripe' ? 'bg-purple-100 text-purple-800' : '' }}
                                {{ $transaction->provider === 'paypal' ? 'bg-blue-100 text-blue-800' : '' }}
                                {{ $transaction->provider === 'razorpay' ? 'bg-green-100 text-green-800' : '' }}">
                                {{ ucfirst($transaction->provider) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">{{ $transaction->formatted_amount }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $transaction->type === 'credit' ? 'bg-green-100 text-green-800' : '' }}
                                {{ $transaction->type === 'debit' ? 'bg-red-100 text-red-800' : '' }}
                                {{ $transaction->type === 'refund' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                                {{ ucfirst($transaction->type) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $transaction->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                                {{ $transaction->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                {{ $transaction->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}
                                {{ $transaction->status === 'cancelled' ? 'bg-gray-100 text-gray-800' : '' }}">
                                {{ ucfirst($transaction->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $transaction->created_at->format('M j, Y H:i') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <a href="{{ route('admin.payments.show', $transaction) }}" 
                               class="text-blue-600 hover:text-blue-900 mr-3">View</a>
                            @if($transaction->isCompleted() && $transaction->type === 'credit')
                            <button onclick="showRefundModal({{ $transaction->id }}, '{{ $transaction->transaction_id }}', {{ $transaction->amount }})"
                                    class="text-red-600 hover:text-red-900">Refund</button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                            No transactions found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $transactions->appends(request()->query())->links() }}
        </div>
    </div>
</div>

<!-- Refund Modal -->
<div id="refundModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Process Refund</h3>
            <form id="refundForm">
                <input type="hidden" id="refundTransactionId">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Transaction ID</label>
                    <input type="text" id="refundTransactionIdDisplay" readonly 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-50">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Refund Amount</label>
                    <input type="number" id="refundAmount" step="0.01" min="0.01" required
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                    <textarea id="refundReason" rows="3" required
                              class="w-full border border-gray-300 rounded-md px-3 py-2"></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideRefundModal()" 
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        Process Refund
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showRefundModal(transactionId, transactionIdDisplay, maxAmount) {
    document.getElementById('refundTransactionId').value = transactionId;
    document.getElementById('refundTransactionIdDisplay').value = transactionIdDisplay;
    document.getElementById('refundAmount').max = maxAmount;
    document.getElementById('refundAmount').value = maxAmount;
    document.getElementById('refundModal').classList.remove('hidden');
    document.getElementById('refundModal').classList.add('flex');
}

function hideRefundModal() {
    document.getElementById('refundModal').classList.add('hidden');
    document.getElementById('refundModal').classList.remove('flex');
    document.getElementById('refundForm').reset();
}

document.getElementById('refundForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const transactionId = document.getElementById('refundTransactionId').value;
    const amount = document.getElementById('refundAmount').value;
    const reason = document.getElementById('refundReason').value;
    
    try {
        const response = await fetch(`/admin/payments/${transactionId}/refund`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ amount, reason })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Refund processed successfully');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error processing refund: ' + error.message);
    }
});
</script>
@endsection
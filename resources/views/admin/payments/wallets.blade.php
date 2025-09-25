@extends('layouts.app')

@section('title', 'Wallet Management')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Wallet Management</h1>
        <div class="flex space-x-2">
            <button onclick="showCreditModal()" 
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                Credit Wallet
            </button>
            <button onclick="showDebitModal()" 
                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg">
                Debit Wallet
            </button>
            <a href="{{ route('admin.payments.index') }}" 
               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                View Transactions
            </a>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search Users</label>
                    <input type="text" name="search" value="{{ request('search') }}" 
                           placeholder="Name or email..."
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Min Balance</label>
                    <input type="number" name="min_balance" value="{{ request('min_balance') }}" 
                           step="0.01" min="0"
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Max Balance</label>
                    <input type="number" name="max_balance" value="{{ request('max_balance') }}" 
                           step="0.01" min="0"
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

    <!-- Wallets Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Currency</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($wallets as $wallet)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    @if($wallet->user->avatar)
                                        <img class="h-10 w-10 rounded-full" src="{{ $wallet->user->avatar }}" alt="">
                                    @else
                                        <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                            <span class="text-sm font-medium text-gray-700">
                                                {{ substr($wallet->user->name, 0, 1) }}
                                            </span>
                                        </div>
                                    @endif
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $wallet->user->name }}</div>
                                    <div class="text-sm text-gray-500">{{ $wallet->user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                ${{ number_format($wallet->balance, 2) }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                {{ $wallet->currency }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $wallet->updated_at->format('M j, Y H:i') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <a href="{{ route('admin.wallets.show', $wallet) }}" 
                               class="text-blue-600 hover:text-blue-900 mr-3">View Details</a>
                            <button onclick="quickCredit({{ $wallet->user->id }}, '{{ $wallet->user->name }}')"
                                    class="text-green-600 hover:text-green-900 mr-3">Credit</button>
                            <button onclick="quickDebit({{ $wallet->user->id }}, '{{ $wallet->user->name }}', {{ $wallet->balance }})"
                                    class="text-red-600 hover:text-red-900">Debit</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                            No wallets found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $wallets->appends(request()->query())->links() }}
        </div>
    </div>
</div>

<!-- Credit Wallet Modal -->
<div id="creditModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Credit Wallet</h3>
            <form id="creditForm">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">User</label>
                    <input type="text" id="creditUserSearch" placeholder="Search users..." 
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                    <input type="hidden" id="creditUserId">
                    <div id="creditUserResults" class="mt-2 max-h-40 overflow-y-auto border border-gray-200 rounded-md hidden"></div>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                    <input type="number" id="creditAmount" step="0.01" min="0.01" required
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <input type="text" id="creditDescription" required
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                    <textarea id="creditReason" rows="3"
                              class="w-full border border-gray-300 rounded-md px-3 py-2"></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideCreditModal()" 
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        Credit Wallet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Debit Wallet Modal -->
<div id="debitModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Debit Wallet</h3>
            <form id="debitForm">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">User</label>
                    <input type="text" id="debitUserSearch" placeholder="Search users..." 
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                    <input type="hidden" id="debitUserId">
                    <div id="debitUserResults" class="mt-2 max-h-40 overflow-y-auto border border-gray-200 rounded-md hidden"></div>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Current Balance</label>
                    <input type="text" id="currentBalance" readonly 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-50">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                    <input type="number" id="debitAmount" step="0.01" min="0.01" required
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <input type="text" id="debitDescription" required
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                    <textarea id="debitReason" rows="3"
                              class="w-full border border-gray-300 rounded-md px-3 py-2"></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideDebitModal()" 
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        Debit Wallet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// User search functionality
let searchTimeout;

function setupUserSearch(inputId, resultsId, userIdInputId) {
    const input = document.getElementById(inputId);
    const results = document.getElementById(resultsId);
    const userIdInput = document.getElementById(userIdInputId);
    
    input.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            results.classList.add('hidden');
            return;
        }
        
        searchTimeout = setTimeout(async () => {
            try {
                const response = await fetch(`/admin/users/search?q=${encodeURIComponent(query)}`);
                const users = await response.json();
                
                results.innerHTML = '';
                
                if (users.length === 0) {
                    results.innerHTML = '<div class="p-2 text-gray-500">No users found</div>';
                } else {
                    users.forEach(user => {
                        const div = document.createElement('div');
                        div.className = 'p-2 hover:bg-gray-100 cursor-pointer';
                        div.innerHTML = `<div class="font-medium">${user.name}</div><div class="text-sm text-gray-500">${user.email}</div>`;
                        div.onclick = () => {
                            input.value = `${user.name} (${user.email})`;
                            userIdInput.value = user.id;
                            results.classList.add('hidden');
                            
                            // Load wallet balance for debit modal
                            if (inputId === 'debitUserSearch') {
                                loadUserWallet(user.id);
                            }
                        };
                        results.appendChild(div);
                    });
                }
                
                results.classList.remove('hidden');
            } catch (error) {
                console.error('Error searching users:', error);
            }
        }, 300);
    });
}

async function loadUserWallet(userId) {
    try {
        const response = await fetch(`/admin/users/${userId}/wallet`);
        const wallet = await response.json();
        document.getElementById('currentBalance').value = wallet.formatted_balance;
        document.getElementById('debitAmount').max = wallet.balance;
    } catch (error) {
        console.error('Error loading wallet:', error);
    }
}

function showCreditModal() {
    document.getElementById('creditModal').classList.remove('hidden');
    document.getElementById('creditModal').classList.add('flex');
    setupUserSearch('creditUserSearch', 'creditUserResults', 'creditUserId');
}

function hideCreditModal() {
    document.getElementById('creditModal').classList.add('hidden');
    document.getElementById('creditModal').classList.remove('flex');
    document.getElementById('creditForm').reset();
    document.getElementById('creditUserId').value = '';
}

function showDebitModal() {
    document.getElementById('debitModal').classList.remove('hidden');
    document.getElementById('debitModal').classList.add('flex');
    setupUserSearch('debitUserSearch', 'debitUserResults', 'debitUserId');
}

function hideDebitModal() {
    document.getElementById('debitModal').classList.add('hidden');
    document.getElementById('debitModal').classList.remove('flex');
    document.getElementById('debitForm').reset();
    document.getElementById('debitUserId').value = '';
}

function quickCredit(userId, userName) {
    document.getElementById('creditUserId').value = userId;
    document.getElementById('creditUserSearch').value = userName;
    showCreditModal();
}

function quickDebit(userId, userName, balance) {
    document.getElementById('debitUserId').value = userId;
    document.getElementById('debitUserSearch').value = userName;
    document.getElementById('currentBalance').value = `$${balance.toFixed(2)} USD`;
    document.getElementById('debitAmount').max = balance;
    showDebitModal();
}

// Form submissions
document.getElementById('creditForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const userId = document.getElementById('creditUserId').value;
    const amount = document.getElementById('creditAmount').value;
    const description = document.getElementById('creditDescription').value;
    const reason = document.getElementById('creditReason').value;
    
    if (!userId) {
        alert('Please select a user');
        return;
    }
    
    try {
        const response = await fetch('/admin/wallets/credit', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ user_id: userId, amount, description, reason })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Wallet credited successfully');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error crediting wallet: ' + error.message);
    }
});

document.getElementById('debitForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const userId = document.getElementById('debitUserId').value;
    const amount = document.getElementById('debitAmount').value;
    const description = document.getElementById('debitDescription').value;
    const reason = document.getElementById('debitReason').value;
    
    if (!userId) {
        alert('Please select a user');
        return;
    }
    
    try {
        const response = await fetch('/admin/wallets/debit', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ user_id: userId, amount, description, reason })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Wallet debited successfully');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error debiting wallet: ' + error.message);
    }
});
</script>
@endsection
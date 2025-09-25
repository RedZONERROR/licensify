@extends('layouts.app')

@section('title', 'Reseller Dashboard')

@section('content')
<div class="container mx-auto px-4 py-6" x-data="resellerDashboard()">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Reseller Dashboard</h1>
        <div class="text-sm text-gray-500">
            Welcome back, {{ auth()->user()->name }}
        </div>
    </div>

    <!-- Quota Status Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <!-- User Quota Card -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">User Quota</h3>
                @if($quota_warnings['user_near_limit'])
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                        Near Limit
                    </span>
                @endif
            </div>
            
            <div class="mb-4">
                <div class="flex justify-between text-sm text-gray-600 mb-1">
                    <span>{{ $stats['total_users'] }} / {{ $stats['max_users'] ?? '∞' }} users</span>
                    @if($stats['max_users'])
                        <span>{{ number_format($stats['user_quota_percentage'], 1) }}%</span>
                    @endif
                </div>
                @if($stats['max_users'])
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="bg-blue-600 h-3 rounded-full transition-all duration-300" 
                             style="width: {{ min(100, $stats['user_quota_percentage']) }}%"></div>
                    </div>
                @endif
            </div>
            
            <div class="flex justify-between items-center">
                <a href="{{ route('reseller.users') }}" 
                   class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    Manage Users →
                </a>
                @if(auth()->user()->canAddUser())
                    <a href="{{ route('reseller.users.create') }}" 
                       class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                        Add User
                    </a>
                @else
                    <span class="text-gray-400 text-sm">Quota Full</span>
                @endif
            </div>
        </div>

        <!-- License Quota Card -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">License Quota</h3>
                @if($quota_warnings['license_near_limit'])
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                        Near Limit
                    </span>
                @endif
            </div>
            
            <div class="mb-4">
                <div class="flex justify-between text-sm text-gray-600 mb-1">
                    <span>{{ $stats['total_licenses'] }} / {{ $stats['max_licenses'] ?? '∞' }} licenses</span>
                    @if($stats['max_licenses'])
                        <span>{{ number_format($stats['license_quota_percentage'], 1) }}%</span>
                    @endif
                </div>
                @if($stats['max_licenses'])
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="bg-green-600 h-3 rounded-full transition-all duration-300" 
                             style="width: {{ min(100, $stats['license_quota_percentage']) }}%"></div>
                    </div>
                @endif
            </div>
            
            <div class="flex justify-between items-center">
                <a href="{{ route('reseller.licenses') }}" 
                   class="text-green-600 hover:text-green-800 text-sm font-medium">
                    Manage Licenses →
                </a>
                <div class="text-sm text-gray-500">
                    {{ $stats['active_licenses'] }} active, {{ $stats['expired_licenses'] }} expired
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Users -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Recent Users</h3>
            </div>
            <div class="p-6">
                @if($recent_users->count() > 0)
                    <div class="space-y-4">
                        @foreach($recent_users as $user)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8">
                                        @if($user->avatar)
                                            <img class="h-8 w-8 rounded-full" src="{{ $user->avatar }}" alt="{{ $user->name }}">
                                        @else
                                            <div class="h-8 w-8 rounded-full bg-gray-300 flex items-center justify-center">
                                                <span class="text-xs font-medium text-gray-700">{{ substr($user->name, 0, 2) }}</span>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900">{{ $user->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $user->assigned_licenses_count }} licenses</p>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-400">
                                    {{ $user->created_at->diffForHumans() }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <a href="{{ route('reseller.users') }}" 
                           class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            View all users →
                        </a>
                    </div>
                @else
                    <p class="text-gray-500 text-center py-4">No users yet.</p>
                @endif
            </div>
        </div>

        <!-- Recent Licenses -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Recent Licenses</h3>
            </div>
            <div class="p-6">
                @if($recent_licenses->count() > 0)
                    <div class="space-y-4">
                        @foreach($recent_licenses as $license)
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $license->product->name ?? 'Unknown Product' }}</p>
                                    <p class="text-xs text-gray-500">
                                        @if($license->user)
                                            Assigned to {{ $license->user->name }}
                                        @else
                                            Unassigned
                                        @endif
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                        @if($license->status === 'active') bg-green-100 text-green-800
                                        @elseif($license->status === 'expired') bg-red-100 text-red-800
                                        @elseif($license->status === 'suspended') bg-yellow-100 text-yellow-800
                                        @else bg-gray-100 text-gray-800 @endif">
                                        {{ ucfirst($license->status) }}
                                    </span>
                                    <p class="text-xs text-gray-400 mt-1">
                                        {{ $license->created_at->diffForHumans() }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <a href="{{ route('reseller.licenses') }}" 
                           class="text-green-600 hover:text-green-800 text-sm font-medium">
                            View all licenses →
                        </a>
                    </div>
                @else
                    <p class="text-gray-500 text-center py-4">No licenses yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function resellerDashboard() {
    return {
        stats: @json($stats),
        
        init() {
            // Auto-refresh dashboard data every 30 seconds
            setInterval(() => {
                this.refreshStats();
            }, 30000);
        },
        
        refreshStats() {
            fetch('{{ route("reseller.statistics") }}')
                .then(response => response.json())
                .then(data => {
                    this.stats = data.stats;
                    // Update progress bars
                    this.updateProgressBars();
                })
                .catch(error => console.error('Error refreshing stats:', error));
        },
        
        updateProgressBars() {
            // Update user quota progress bar
            const userProgressBar = document.querySelector('[data-progress="user"]');
            if (userProgressBar && this.stats.max_users) {
                const percentage = (this.stats.total_users / this.stats.max_users) * 100;
                userProgressBar.style.width = Math.min(100, percentage) + '%';
            }
            
            // Update license quota progress bar
            const licenseProgressBar = document.querySelector('[data-progress="license"]');
            if (licenseProgressBar && this.stats.max_licenses) {
                const percentage = (this.stats.total_licenses / this.stats.max_licenses) * 100;
                licenseProgressBar.style.width = Math.min(100, percentage) + '%';
            }
        }
    }
}
</script>
@endpush
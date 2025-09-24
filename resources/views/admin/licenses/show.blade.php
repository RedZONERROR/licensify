@extends('layouts.app')

@section('title', 'License Details')

@section('content')
<div class="container mx-auto px-4 py-6" x-data="licenseDetails()">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">License Details</h1>
            <p class="text-gray-600 mt-1">{{ $license->license_key }}</p>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('admin.licenses.index') }}" 
               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                Back to Licenses
            </a>
            @can('update', $license)
            <a href="{{ route('admin.licenses.edit', $license) }}" 
               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                Edit License
            </a>
            @endcan
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- License Information -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Basic Info -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">License Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">License Key</label>
                        <div class="mt-1 flex items-center space-x-2">
                            <code class="bg-gray-100 px-3 py-2 rounded text-sm font-mono">{{ $license->license_key }}</code>
                            <button @click="copyToClipboard('{{ $license->license_key }}')" 
                                    class="text-blue-600 hover:text-blue-800">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <div class="mt-1">
                            @php
                                $statusColors = [
                                    'active' => 'bg-green-100 text-green-800',
                                    'expired' => 'bg-red-100 text-red-800',
                                    'suspended' => 'bg-yellow-100 text-yellow-800',
                                    'reset' => 'bg-blue-100 text-blue-800',
                                ];
                            @endphp
                            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ $statusColors[$license->status] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ ucfirst($license->status) }}
                            </span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Product</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $license->product->name }}</p>
                        <p class="text-xs text-gray-500">Version {{ $license->product->version ?? 'N/A' }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Device Type</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $license->device_type ?? 'Any' }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Max Devices</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $license->max_devices }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Expires At</label>
                        <p class="mt-1 text-sm text-gray-900">
                            @if($license->expires_at)
                                {{ $license->expires_at->format('M j, Y g:i A') }}
                                @if($license->expires_at->isPast())
                                    <span class="text-red-600">(Expired)</span>
                                @elseif($license->expires_at->diffInDays() <= 30)
                                    <span class="text-orange-600">({{ $license->expires_at->diffForHumans() }})</span>
                                @endif
                            @else
                                <span class="text-gray-500">Never</span>
                            @endif
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Created</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $license->created_at->format('M j, Y g:i A') }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Last Updated</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $license->updated_at->format('M j, Y g:i A') }}</p>
                    </div>
                </div>
            </div>

            <!-- Owner and User Info -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Owner & User Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Owner</h3>
                        <div class="flex items-center space-x-3">
                            @if($license->owner->avatar)
                            <img src="{{ $license->owner->avatar }}" alt="{{ $license->owner->name }}" class="w-10 h-10 rounded-full">
                            @else
                            <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center">
                                <span class="text-gray-600 font-medium">{{ substr($license->owner->name, 0, 1) }}</span>
                            </div>
                            @endif
                            <div>
                                <p class="font-medium text-gray-900">{{ $license->owner->name }}</p>
                                <p class="text-sm text-gray-500">{{ $license->owner->email }}</p>
                                <p class="text-xs text-gray-400">{{ ucfirst($license->owner->role->value) }}</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Assigned User</h3>
                        @if($license->user)
                        <div class="flex items-center space-x-3">
                            @if($license->user->avatar)
                            <img src="{{ $license->user->avatar }}" alt="{{ $license->user->name }}" class="w-10 h-10 rounded-full">
                            @else
                            <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center">
                                <span class="text-gray-600 font-medium">{{ substr($license->user->name, 0, 1) }}</span>
                            </div>
                            @endif
                            <div>
                                <p class="font-medium text-gray-900">{{ $license->user->name }}</p>
                                <p class="text-sm text-gray-500">{{ $license->user->email }}</p>
                                <p class="text-xs text-gray-400">{{ ucfirst($license->user->role->value) }}</p>
                            </div>
                        </div>
                        @else
                        <p class="text-gray-500">Not assigned to any user</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Device Activations -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-900">Device Activations</h2>
                    <span class="text-sm text-gray-500">{{ $license->activations->count() }} / {{ $license->max_devices }} devices</span>
                </div>

                @if($license->activations->count() > 0)
                <div class="space-y-4">
                    @foreach($license->activations as $activation)
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center space-x-2">
                                    <h3 class="font-medium text-gray-900">{{ $activation->getDeviceInfoString() }}</h3>
                                    @if($activation->isRecentlyActive())
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                        Active
                                    </span>
                                    @else
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                        Inactive
                                    </span>
                                    @endif
                                </div>
                                <p class="text-sm text-gray-500 mt-1">
                                    Device Hash: <code class="bg-gray-100 px-1 rounded text-xs">{{ Str::limit($activation->device_hash, 20) }}</code>
                                </p>
                                <div class="text-xs text-gray-400 mt-2 space-y-1">
                                    <p>Activated: {{ $activation->activated_at->format('M j, Y g:i A') }}</p>
                                    @if($activation->last_seen_at)
                                    <p>Last Seen: {{ $activation->last_seen_at->diffForHumans() }}</p>
                                    @endif
                                </div>
                            </div>
                            @can('manageDevices', $license)
                            <button @click="unbindDevice('{{ $activation->device_hash }}')" 
                                    class="text-red-600 hover:text-red-800 text-sm font-medium">
                                Unbind
                            </button>
                            @endcan
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-8">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                    <p class="text-gray-500">No devices activated</p>
                </div>
                @endif
            </div>
        </div>

        <!-- Actions Sidebar -->
        <div class="space-y-6">
            @can('update', $license)
            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h2>
                <div class="space-y-3">
                    @if($license->status === 'active')
                    <button @click="suspendLicense()" 
                            class="w-full bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        Suspend License
                    </button>
                    @elseif($license->status === 'suspended')
                    <button @click="unsuspendLicense()" 
                            class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        Unsuspend License
                    </button>
                    @endif

                    @if($license->activations->count() > 0)
                    <button @click="resetDevices()" 
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        Reset All Devices
                    </button>
                    @endif

                    @if($license->status !== 'expired')
                    <button @click="expireLicense()" 
                            class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        Expire License
                    </button>
                    @endif
                </div>
            </div>
            @endcan

            <!-- License Health -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">License Health</h2>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Status</span>
                        @if($license->isActive())
                        <span class="text-green-600 font-medium">Healthy</span>
                        @else
                        <span class="text-red-600 font-medium">Issues</span>
                        @endif
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Device Usage</span>
                        <span class="font-medium">{{ $license->getActiveDeviceCount() }}/{{ $license->max_devices }}</span>
                    </div>
                    
                    @if($license->expires_at)
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Days Until Expiry</span>
                        @if($license->expires_at->isPast())
                        <span class="text-red-600 font-medium">Expired</span>
                        @else
                        <span class="font-medium">{{ $license->expires_at->diffInDays() }}</span>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function licenseDetails() {
    return {
        async copyToClipboard(text) {
            try {
                await navigator.clipboard.writeText(text);
                this.showNotification('License key copied to clipboard', 'success');
            } catch (err) {
                console.error('Failed to copy: ', err);
                this.showNotification('Failed to copy license key', 'error');
            }
        },

        async suspendLicense() {
            if (!confirm('Are you sure you want to suspend this license?')) return;
            
            try {
                const response = await this.makeRequest('{{ route("admin.licenses.suspend", $license) }}', 'POST');
                if (response.success) {
                    this.showNotification(response.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    this.showNotification(response.message, 'error');
                }
            } catch (error) {
                this.showNotification('An error occurred', 'error');
            }
        },

        async unsuspendLicense() {
            if (!confirm('Are you sure you want to unsuspend this license?')) return;
            
            try {
                const response = await this.makeRequest('{{ route("admin.licenses.unsuspend", $license) }}', 'POST');
                if (response.success) {
                    this.showNotification(response.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    this.showNotification(response.message, 'error');
                }
            } catch (error) {
                this.showNotification('An error occurred', 'error');
            }
        },

        async resetDevices() {
            if (!confirm('Are you sure you want to reset all device bindings? This will unbind all devices from this license.')) return;
            
            try {
                const response = await this.makeRequest('{{ route("admin.licenses.reset-devices", $license) }}', 'POST');
                if (response.success) {
                    this.showNotification(response.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    this.showNotification(response.message, 'error');
                }
            } catch (error) {
                this.showNotification('An error occurred', 'error');
            }
        },

        async expireLicense() {
            if (!confirm('Are you sure you want to expire this license? This action cannot be undone.')) return;
            
            try {
                const response = await this.makeRequest('{{ route("admin.licenses.expire", $license) }}', 'POST');
                if (response.success) {
                    this.showNotification(response.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    this.showNotification(response.message, 'error');
                }
            } catch (error) {
                this.showNotification('An error occurred', 'error');
            }
        },

        async unbindDevice(deviceHash) {
            if (!confirm('Are you sure you want to unbind this device?')) return;
            
            try {
                const response = await this.makeRequest('{{ route("admin.licenses.unbind-device", $license) }}', 'POST', {
                    device_hash: deviceHash
                });
                if (response.success) {
                    this.showNotification(response.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    this.showNotification(response.message, 'error');
                }
            } catch (error) {
                this.showNotification('An error occurred', 'error');
            }
        },

        async makeRequest(url, method = 'GET', data = {}) {
            const options = {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            };

            if (method !== 'GET' && Object.keys(data).length > 0) {
                options.body = JSON.stringify(data);
            }

            const response = await fetch(url, options);
            return await response.json();
        },

        showNotification(message, type = 'info') {
            // Simple notification - you can enhance this with a proper notification system
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            const alertDiv = document.createElement('div');
            alertDiv.className = `fixed top-4 right-4 p-4 rounded-lg text-white z-50 ${type === 'success' ? 'bg-green-600' : 'bg-red-600'}`;
            alertDiv.textContent = message;
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 3000);
        }
    }
}
</script>
@endsection
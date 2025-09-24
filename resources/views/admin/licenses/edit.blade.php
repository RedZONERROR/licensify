@extends('layouts.app')

@section('title', 'Edit License')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Edit License</h1>
            <p class="text-gray-600 mt-1">{{ $license->license_key }}</p>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('admin.licenses.show', $license) }}" 
               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                View License
            </a>
            <a href="{{ route('admin.licenses.index') }}" 
               class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-lg font-medium transition-colors">
                Back to Licenses
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Edit Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow p-6">
                <form method="POST" action="{{ route('admin.licenses.update', $license) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <!-- Read-only Information -->
                    <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                        <h3 class="text-lg font-medium text-gray-900">License Information</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="font-medium text-gray-700">License Key:</span>
                                <code class="ml-2 bg-white px-2 py-1 rounded border">{{ $license->license_key }}</code>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Product:</span>
                                <span class="ml-2">{{ $license->product->name }}</span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Owner:</span>
                                <span class="ml-2">{{ $license->owner->name }} ({{ $license->owner->email }})</span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Status:</span>
                                @php
                                    $statusColors = [
                                        'active' => 'bg-green-100 text-green-800',
                                        'expired' => 'bg-red-100 text-red-800',
                                        'suspended' => 'bg-yellow-100 text-yellow-800',
                                        'reset' => 'bg-blue-100 text-blue-800',
                                    ];
                                @endphp
                                <span class="ml-2 inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $statusColors[$license->status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ ucfirst($license->status) }}
                                </span>
                            </div>
                        </div>
                        
                        <p class="text-xs text-gray-500 mt-2">
                            Note: License key, product, owner, and status cannot be changed through this form. 
                            Use the action buttons on the license details page to modify the status.
                        </p>
                    </div>

                    <!-- Editable Fields -->
                    <div class="space-y-6">
                        <!-- User Assignment -->
                        <div>
                            <label for="user_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Assigned User
                            </label>
                            <select id="user_id" 
                                    name="user_id"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('user_id') border-red-500 @enderror">
                                <option value="">Not assigned</option>
                                @foreach($users->where('role', 'user') as $user)
                                <option value="{{ $user->id }}" {{ (old('user_id', $license->user_id) == $user->id) ? 'selected' : '' }}>
                                    {{ $user->name }} ({{ $user->email }})
                                </option>
                                @endforeach
                            </select>
                            @error('user_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-sm text-gray-500">The end user who will use this license</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Device Type -->
                            <div>
                                <label for="device_type" class="block text-sm font-medium text-gray-700 mb-2">
                                    Device Type
                                </label>
                                <input type="text" 
                                       id="device_type" 
                                       name="device_type" 
                                       value="{{ old('device_type', $license->device_type) }}"
                                       placeholder="e.g., desktop, mobile, server"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('device_type') border-red-500 @enderror">
                                @error('device_type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-sm text-gray-500">Restrict license to specific device types</p>
                            </div>

                            <!-- Max Devices -->
                            <div>
                                <label for="max_devices" class="block text-sm font-medium text-gray-700 mb-2">
                                    Maximum Devices <span class="text-red-500">*</span>
                                </label>
                                <input type="number" 
                                       id="max_devices" 
                                       name="max_devices" 
                                       value="{{ old('max_devices', $license->max_devices) }}"
                                       min="1" 
                                       max="100"
                                       required
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('max_devices') border-red-500 @enderror">
                                @error('max_devices')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-sm text-gray-500">
                                    Currently {{ $license->getActiveDeviceCount() }} devices are bound to this license
                                </p>
                            </div>
                        </div>

                        <!-- Expiration Date -->
                        <div>
                            <label for="expires_at" class="block text-sm font-medium text-gray-700 mb-2">
                                Expiration Date
                            </label>
                            <input type="date" 
                                   id="expires_at" 
                                   name="expires_at" 
                                   value="{{ old('expires_at', $license->expires_at?->format('Y-m-d')) }}"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('expires_at') border-red-500 @enderror">
                            @error('expires_at')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-sm text-gray-500">Leave empty for perpetual license</p>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                        <a href="{{ route('admin.licenses.show', $license) }}" 
                           class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-6 py-2 rounded-lg font-medium transition-colors">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                            Update License
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Information Sidebar -->
        <div class="space-y-6">
            <!-- Current Device Usage -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Device Usage</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Active Devices</span>
                        <span class="font-medium">{{ $license->getActiveDeviceCount() }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Maximum Allowed</span>
                        <span class="font-medium">{{ $license->max_devices }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        @php
                            $percentage = $license->max_devices > 0 ? ($license->getActiveDeviceCount() / $license->max_devices) * 100 : 0;
                        @endphp
                        <div class="bg-blue-600 h-2 rounded-full" style="width: {{ min($percentage, 100) }}%"></div>
                    </div>
                    <p class="text-xs text-gray-500">
                        {{ $license->max_devices - $license->getActiveDeviceCount() }} slots available
                    </p>
                </div>
            </div>

            <!-- License Health -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">License Health</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Status</span>
                        @if($license->isActive())
                        <span class="text-green-600 font-medium">Healthy</span>
                        @else
                        <span class="text-red-600 font-medium">Issues</span>
                        @endif
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

                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Created</span>
                        <span class="text-sm">{{ $license->created_at->diffForHumans() }}</span>
                    </div>
                </div>
            </div>

            <!-- Warning Panel -->
            @if($license->getActiveDeviceCount() > 0)
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Device Limit Warning</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <p>This license has active device bindings. Reducing the maximum device limit below the current active count ({{ $license->getActiveDeviceCount() }}) may cause issues.</p>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
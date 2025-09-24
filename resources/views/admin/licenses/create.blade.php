@extends('layouts.app')

@section('title', 'Create License')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Create License</h1>
            <p class="text-gray-600 mt-1">Generate a new software license</p>
        </div>
        <a href="{{ route('admin.licenses.index') }}" 
           class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
            Back to Licenses
        </a>
    </div>

    <!-- Form -->
    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('admin.licenses.store') }}" class="space-y-6">
            @csrf

            <!-- Product Selection -->
            <div>
                <label for="product_id" class="block text-sm font-medium text-gray-700 mb-2">
                    Product <span class="text-red-500">*</span>
                </label>
                <select id="product_id" 
                        name="product_id" 
                        required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('product_id') border-red-500 @enderror">
                    <option value="">Select a product</option>
                    @foreach($products as $product)
                    <option value="{{ $product->id }}" {{ old('product_id') == $product->id ? 'selected' : '' }}>
                        {{ $product->name }} @if($product->version)(v{{ $product->version }})@endif
                    </option>
                    @endforeach
                </select>
                @error('product_id')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Owner Selection -->
            <div>
                <label for="owner_id" class="block text-sm font-medium text-gray-700 mb-2">
                    License Owner <span class="text-red-500">*</span>
                </label>
                <select id="owner_id" 
                        name="owner_id" 
                        required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('owner_id') border-red-500 @enderror">
                    <option value="">Select an owner</option>
                    @foreach($users as $user)
                    <option value="{{ $user->id }}" {{ old('owner_id') == $user->id ? 'selected' : '' }}>
                        {{ $user->name }} ({{ $user->email }}) - {{ ucfirst($user->role->value) }}
                    </option>
                    @endforeach
                </select>
                @error('owner_id')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-sm text-gray-500">The user who will own and manage this license</p>
            </div>

            <!-- User Assignment (Optional) -->
            <div>
                <label for="user_id" class="block text-sm font-medium text-gray-700 mb-2">
                    Assigned User (Optional)
                </label>
                <select id="user_id" 
                        name="user_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('user_id') border-red-500 @enderror">
                    <option value="">Not assigned</option>
                    @foreach($users->where('role', 'user') as $user)
                    <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
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
                        Device Type (Optional)
                    </label>
                    <input type="text" 
                           id="device_type" 
                           name="device_type" 
                           value="{{ old('device_type') }}"
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
                           value="{{ old('max_devices', 1) }}"
                           min="1" 
                           max="100"
                           required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('max_devices') border-red-500 @enderror">
                    @error('max_devices')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-sm text-gray-500">Number of devices that can use this license</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Expiration Date -->
                <div>
                    <label for="expires_at" class="block text-sm font-medium text-gray-700 mb-2">
                        Expiration Date (Optional)
                    </label>
                    <input type="date" 
                           id="expires_at" 
                           name="expires_at" 
                           value="{{ old('expires_at') }}"
                           min="{{ now()->format('Y-m-d') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('expires_at') border-red-500 @enderror">
                    @error('expires_at')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-sm text-gray-500">Leave empty for perpetual license</p>
                </div>

                <!-- Initial Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                        Initial Status <span class="text-red-500">*</span>
                    </label>
                    <select id="status" 
                            name="status" 
                            required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('status') border-red-500 @enderror">
                        <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="suspended" {{ old('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                        <option value="reset" {{ old('status') === 'reset' ? 'selected' : '' }}>Reset</option>
                    </select>
                    @error('status')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('admin.licenses.index') }}" 
                   class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-6 py-2 rounded-lg font-medium transition-colors">
                    Cancel
                </a>
                <button type="submit" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                    Create License
                </button>
            </div>
        </form>
    </div>

    <!-- Information Panel -->
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800">License Creation Information</h3>
                <div class="mt-2 text-sm text-blue-700">
                    <ul class="list-disc list-inside space-y-1">
                        <li>A unique license key will be automatically generated</li>
                        <li>The license owner can manage device bindings and user assignments</li>
                        <li>Device limits can be adjusted after creation if needed</li>
                        <li>Expiration dates can be modified later through the edit interface</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@extends('layouts.app')

@section('title', 'Edit User Role')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-2xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Edit User Role</h1>
            <a href="{{ route('admin.roles.index') }}" 
               class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Back to Roles
            </a>
        </div>

        <!-- User Information -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center space-x-4">
                    @if($user->avatar)
                        <img class="h-16 w-16 rounded-full" src="{{ $user->avatar }}" alt="">
                    @else
                        <div class="h-16 w-16 rounded-full bg-gray-300 flex items-center justify-center">
                            <span class="text-xl font-medium text-gray-700">{{ substr($user->name, 0, 1) }}</span>
                        </div>
                    @endif
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">{{ $user->name }}</h3>
                        <p class="text-sm text-gray-500">{{ $user->email }}</p>
                        <p class="text-sm text-gray-500">Current Role: 
                            <span class="font-medium text-{{ $user->role->value === 'admin' ? 'red' : ($user->role->value === 'developer' ? 'purple' : ($user->role->value === 'reseller' ? 'blue' : 'gray')) }}-600">
                                {{ $user->role->label() }}
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Role Information -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Current Role Details</h3>
                
                <div class="space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Description</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $user->role->description() }}</dd>
                    </div>
                    
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Permissions</dt>
                        <dd class="mt-1">
                            <div class="flex flex-wrap gap-2">
                                @foreach($user->role->permissions() as $permission)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ str_replace('_', ' ', ucfirst($permission)) }}
                                    </span>
                                @endforeach
                            </div>
                        </dd>
                    </div>
                    
                    <div>
                        <dt class="text-sm font-medium text-gray-500">2FA Requirement</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $user->role->requires2FA() ? 'Required' : 'Optional' }}
                            @if($user->requires2FA())
                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $user->hasTwoFactorEnabled() ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $user->hasTwoFactorEnabled() ? 'Enabled' : 'Not Enabled' }}
                                </span>
                            @endif
                        </dd>
                    </div>
                </div>
            </div>
        </div>

        <!-- Role Change Form -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Change Role</h3>
                
                <form method="POST" action="{{ route('admin.roles.update', $user) }}" class="space-y-6">
                    @csrf
                    @method('PUT')
                    
                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700">New Role</label>
                        <select id="role" name="role" required 
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                x-data="{ selectedRole: '{{ $user->role->value }}' }" 
                                x-model="selectedRole">
                            @foreach(\App\Enums\Role::cases() as $role)
                                @if(in_array($role, auth()->user()->role->canManageRoles()) || $role === $user->role)
                                    <option value="{{ $role->value }}" 
                                            {{ $role === $user->role ? 'selected' : '' }}>
                                        {{ $role->label() }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        @error('role')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Role Preview -->
                    <div x-show="selectedRole !== '{{ $user->role->value }}'" x-transition>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-gray-900 mb-2">New Role Preview</h4>
                            @foreach(\App\Enums\Role::cases() as $role)
                                <div x-show="selectedRole === '{{ $role->value }}'" class="space-y-2">
                                    <p class="text-sm text-gray-600">{{ $role->description() }}</p>
                                    <div>
                                        <span class="text-xs font-medium text-gray-500">Permissions:</span>
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            @foreach($role->permissions() as $permission)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">
                                                    {{ str_replace('_', ' ', ucfirst($permission)) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                    @if($role->requires2FA())
                                        <p class="text-xs text-amber-600">⚠️ This role requires two-factor authentication</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label for="reason" class="block text-sm font-medium text-gray-700">Reason for Change</label>
                        <textarea id="reason" name="reason" rows="3" required
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Explain why this role change is necessary..."></textarea>
                        @error('reason')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end space-x-3">
                        <a href="{{ route('admin.roles.index') }}" 
                           class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                                x-bind:disabled="selectedRole === '{{ $user->role->value }}'">
                            Update Role
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recent Role Changes -->
        @if($user->auditLogs()->where('description', 'like', '%Role changed%')->exists())
            <div class="bg-white shadow rounded-lg mt-6">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Role Changes</h3>
                    <div class="space-y-3">
                        @foreach($user->auditLogs()->where('description', 'like', '%Role changed%')->latest()->take(5)->get() as $log)
                            <div class="flex justify-between items-center py-2 border-b border-gray-200 last:border-b-0">
                                <div>
                                    <p class="text-sm text-gray-900">{{ $log->description }}</p>
                                    <p class="text-xs text-gray-500">
                                        by {{ $log->causer?->name ?? 'System' }} • {{ $log->created_at->diffForHumans() }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
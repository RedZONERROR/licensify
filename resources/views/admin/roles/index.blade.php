@extends('layouts.app')

@section('title', 'Role Management')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Role Management</h1>
        <div class="flex space-x-2">
            <a href="{{ route('admin.roles.permissions') }}" 
               class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                View Permissions
            </a>
            <a href="{{ route('admin.roles.export') }}" 
               class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                Export CSV
            </a>
        </div>
    </div>

    <!-- Role Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        @foreach($roleStats as $roleValue => $stats)
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-{{ $roleValue === 'admin' ? 'red' : ($roleValue === 'developer' ? 'purple' : ($roleValue === 'reseller' ? 'blue' : 'gray')) }}-500 rounded-full flex items-center justify-center">
                                <span class="text-white text-sm font-bold">{{ substr(strtoupper($stats['label']), 0, 1) }}</span>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">{{ $stats['label'] }}</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $stats['count'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Bulk Actions -->
    <div class="bg-white shadow rounded-lg mb-6" x-data="{ showBulkForm: false, selectedUsers: [] }">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Bulk Role Assignment</h3>
            
            <button @click="showBulkForm = !showBulkForm" 
                    class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded mb-4">
                Toggle Bulk Actions
            </button>

            <div x-show="showBulkForm" x-transition class="space-y-4">
                <form method="POST" action="{{ route('admin.roles.bulk-update') }}">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">New Role</label>
                            <select name="role" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                @foreach($roles as $role)
                                    @if(in_array($role, auth()->user()->role->canManageRoles()))
                                        <option value="{{ $role->value }}">{{ $role->label() }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Reason</label>
                            <input type="text" name="reason" required 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                   placeholder="Reason for role change">
                        </div>
                        <div class="flex items-end">
                            <button type="submit" 
                                    class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                Update Selected
                            </button>
                        </div>
                    </div>
                    
                    <input type="hidden" name="user_ids" x-bind:value="selectedUsers.join(',')">
                </form>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white shadow overflow-hidden sm:rounded-md">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Users and Roles</h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">Manage user roles and permissions</p>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <input type="checkbox" x-model="selectAll" @change="toggleAll">
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reseller</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">2FA Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($users as $user)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if(auth()->user()->canManageUser($user))
                                    <input type="checkbox" value="{{ $user->id }}" 
                                           x-model="selectedUsers">
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        @if($user->avatar)
                                            <img class="h-10 w-10 rounded-full" src="{{ $user->avatar }}" alt="">
                                        @else
                                            <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                                <span class="text-sm font-medium text-gray-700">{{ substr($user->name, 0, 1) }}</span>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">{{ $user->name }}</div>
                                        <div class="text-sm text-gray-500">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $user->role->value === 'admin' ? 'bg-red-100 text-red-800' : 
                                       ($user->role->value === 'developer' ? 'bg-purple-100 text-purple-800' : 
                                        ($user->role->value === 'reseller' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800')) }}">
                                    {{ $user->role->label() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $user->reseller?->name ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($user->requires2FA())
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $user->hasTwoFactorEnabled() ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $user->hasTwoFactorEnabled() ? 'Enabled' : 'Required' }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        Optional
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                @can('view', $user)
                                    <a href="{{ route('admin.roles.show', $user) }}" 
                                       class="text-indigo-600 hover:text-indigo-900 mr-3">Edit Role</a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $users->links() }}
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('roleManagement', () => ({
        selectAll: false,
        selectedUsers: [],
        
        toggleAll() {
            if (this.selectAll) {
                this.selectedUsers = Array.from(document.querySelectorAll('input[type="checkbox"][value]'))
                    .map(cb => cb.value);
            } else {
                this.selectedUsers = [];
            }
        }
    }));
});
</script>
@endsection
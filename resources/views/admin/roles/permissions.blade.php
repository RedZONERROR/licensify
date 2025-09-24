@extends('layouts.app')

@section('title', 'Role Permissions Matrix')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Role Permissions Matrix</h1>
        <a href="{{ route('admin.roles.index') }}" 
           class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
            Back to Roles
        </a>
    </div>

    <!-- Role Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        @foreach($roles as $role)
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-{{ $role->value === 'admin' ? 'red' : ($role->value === 'developer' ? 'purple' : ($role->value === 'reseller' ? 'blue' : 'gray')) }}-500 rounded-full flex items-center justify-center">
                                <span class="text-white text-sm font-bold">{{ substr(strtoupper($role->label()), 0, 1) }}</span>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">{{ $role->label() }}</dt>
                                <dd class="text-xs text-gray-600">Level {{ $role->level() }}</dd>
                                <dd class="text-xs text-gray-500">{{ count($role->permissions()) }} permissions</dd>
                            </dl>
                        </div>
                    </div>
                    <div class="mt-3">
                        <p class="text-xs text-gray-600">{{ $role->description() }}</p>
                        @if($role->requires2FA())
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 mt-2">
                                Requires 2FA
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Permissions Matrix -->
    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Permissions Matrix</h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">
                This matrix shows which permissions are granted to each role.
            </p>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50 z-10">
                            Permission
                        </th>
                        @foreach($roles as $role)
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <div class="flex flex-col items-center">
                                    <div class="w-6 h-6 bg-{{ $role->value === 'admin' ? 'red' : ($role->value === 'developer' ? 'purple' : ($role->value === 'reseller' ? 'blue' : 'gray')) }}-500 rounded-full flex items-center justify-center mb-1">
                                        <span class="text-white text-xs font-bold">{{ substr(strtoupper($role->label()), 0, 1) }}</span>
                                    </div>
                                    <span class="text-xs">{{ $role->label() }}</span>
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($allPermissions as $permission)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 sticky left-0 bg-white z-10">
                                <div>
                                    <div class="font-medium">{{ str_replace('_', ' ', ucwords($permission, '_')) }}</div>
                                    <div class="text-xs text-gray-500">{{ $permission }}</div>
                                </div>
                            </td>
                            @foreach($roles as $role)
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if($permissionMatrix[$role->value][$permission])
                                        <span class="inline-flex items-center justify-center w-6 h-6 bg-green-100 rounded-full">
                                            <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                            </svg>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center justify-center w-6 h-6 bg-gray-100 rounded-full">
                                            <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                            </svg>
                                        </span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Permission Categories -->
    <div class="mt-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @php
            $categories = [
                'User Management' => ['manage_users', 'manage_roles', 'manage_assigned_users'],
                'License Management' => ['manage_licenses', 'manage_assigned_licenses', 'view_licenses'],
                'System Administration' => ['manage_settings', 'system_administration', 'manage_api_keys'],
                'Operations' => ['manage_backups', 'database_operations', 'system_monitoring'],
                'Audit & Logs' => ['view_audit_logs', 'log_viewing'],
                'Communication' => ['chat_access'],
                'Dashboard' => ['view_dashboard'],
                'Payments' => ['manage_payments'],
            ];
        @endphp

        @foreach($categories as $categoryName => $categoryPermissions)
            @php
                $categoryPerms = array_intersect($categoryPermissions, $allPermissions->toArray());
            @endphp
            @if(!empty($categoryPerms))
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-3">{{ $categoryName }}</h3>
                        <div class="space-y-2">
                            @foreach($categoryPerms as $permission)
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">{{ str_replace('_', ' ', ucwords($permission, '_')) }}</span>
                                    <div class="flex space-x-1">
                                        @foreach($roles as $role)
                                            @if($permissionMatrix[$role->value][$permission])
                                                <div class="w-3 h-3 bg-{{ $role->value === 'admin' ? 'red' : ($role->value === 'developer' ? 'purple' : ($role->value === 'reseller' ? 'blue' : 'gray')) }}-500 rounded-full" 
                                                     title="{{ $role->label() }}"></div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</div>
@endsection
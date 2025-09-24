@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                <h1 class="text-2xl font-bold mb-4">Dashboard</h1>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- User Info Card -->
                    <div class="bg-blue-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold text-blue-800 mb-2">Welcome, {{ auth()->user()->name }}!</h3>
                        <p class="text-blue-600">Role: {{ ucfirst(auth()->user()->role) }}</p>
                        <p class="text-blue-600">Email: {{ auth()->user()->email }}</p>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-green-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold text-green-800 mb-4">Quick Actions</h3>
                        <div class="space-y-2">
                            <a href="#" class="block text-green-600 hover:text-green-800">Edit Profile</a>
                            @if(auth()->user()->isAdmin() || auth()->user()->isReseller())
                                <a href="#" class="block text-green-600 hover:text-green-800">Manage Licenses</a>
                            @endif
                            @if(auth()->user()->isAdmin())
                                <a href="#" class="block text-green-600 hover:text-green-800">System Settings</a>
                            @endif
                        </div>
                    </div>

                    <!-- Security Status -->
                    <div class="bg-yellow-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold text-yellow-800 mb-4">Security Status</h3>
                        <div class="space-y-2">
                            <p class="text-yellow-600">
                                2FA: 
                                @if(auth()->user()->{'2fa_enabled'})
                                    <span class="text-green-600 font-semibold">Enabled</span>
                                @else
                                    <span class="text-red-600 font-semibold">Disabled</span>
                                @endif
                            </p>
                            @if(auth()->user()->requires2FA() && !auth()->user()->{'2fa_enabled'})
                                <p class="text-red-600 text-sm">⚠️ 2FA is required for your role</p>
                                <a href="#" class="text-yellow-600 hover:text-yellow-800 text-sm">Set up 2FA</a>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Recent Activity (placeholder) -->
                <div class="mt-8">
                    <h2 class="text-xl font-semibold mb-4">Recent Activity</h2>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-gray-600">No recent activity to display.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
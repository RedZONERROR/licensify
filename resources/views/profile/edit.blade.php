@extends('layouts.app')

@section('title', 'Profile Settings')

@section('content')
<div class="py-12" x-data="profileManager()">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                <h2 class="text-2xl font-bold mb-6">Profile Settings</h2>

                @if (session('success'))
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('oauth_setup_password'))
                    <div class="mb-4 bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded">
                        <strong>Welcome!</strong> You can optionally set up a password for additional security beyond Gmail login.
                    </div>
                @endif

                @if (session('oauth_new_user'))
                    <div class="mb-4 bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded">
                        <strong>Welcome to our platform!</strong> Please review your profile settings and privacy policy.
                    </div>
                @endif

                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <!-- Basic Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                            <input 
                                type="text" 
                                name="name" 
                                id="name" 
                                value="{{ old('name', $user->name) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('name') border-red-500 @enderror"
                                required
                            >
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input 
                                type="email" 
                                name="email" 
                                id="email" 
                                value="{{ old('email', $user->email) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('email') border-red-500 @enderror"
                                required
                            >
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Avatar Upload -->
                    <div>
                        <label for="avatar" class="block text-sm font-medium text-gray-700">Profile Picture</label>
                        @if($user->avatar)
                            <div class="mt-2 mb-2 relative inline-block">
                                <img src="{{ asset('storage/' . $user->avatar) }}" alt="Current avatar" class="w-20 h-20 rounded-full object-cover">
                                <button 
                                    type="button"
                                    @click="deleteAvatar()"
                                    class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600"
                                    title="Delete avatar"
                                >
                                    ×
                                </button>
                            </div>
                        @endif
                        <input 
                            type="file" 
                            name="avatar" 
                            id="avatar" 
                            accept="image/*"
                            @change="previewAvatar($event)"
                            class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                        >
                        <div x-show="avatarPreview" class="mt-2">
                            <img :src="avatarPreview" alt="Avatar preview" class="w-20 h-20 rounded-full object-cover">
                        </div>
                        @error('avatar')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password Section -->
                    <div class="border-t pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            @if($user->password)
                                Change Password
                            @else
                                Set Password (Optional)
                            @endif
                        </h3>
                        
                        @if($user->isOAuthOnly())
                            <div class="mb-4 bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded">
                                <p class="text-sm">
                                    <strong>Note:</strong> You're currently using Gmail-only authentication. 
                                    Setting a password will enable hybrid authentication (Gmail + password).
                                </p>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700">
                                    {{ $user->password ? 'New Password' : 'Password' }}
                                </label>
                                <input 
                                    type="password" 
                                    name="password" 
                                    id="password" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('password') border-red-500 @enderror"
                                >
                                @error('password')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                                <input 
                                    type="password" 
                                    name="password_confirmation" 
                                    id="password_confirmation" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- OAuth Providers Section -->
                    <div class="border-t pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Connected Accounts</h3>
                        
                        <div class="space-y-4">
                            <!-- Gmail OAuth -->
                            <div class="flex items-center justify-between p-4 border rounded-lg">
                                <div class="flex items-center">
                                    <svg class="w-8 h-8 mr-3" viewBox="0 0 24 24">
                                        <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                        <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                        <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                        <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                                    </svg>
                                    <div>
                                        <p class="font-medium">Google</p>
                                        @if($user->hasOAuthProvider('google'))
                                            <p class="text-sm text-gray-500">
                                                Connected as {{ $user->getOAuthProvider('google')['email'] }}
                                            </p>
                                            <p class="text-xs text-gray-400">
                                                Linked {{ \Carbon\Carbon::parse($user->getOAuthProvider('google')['linked_at'])->diffForHumans() }}
                                            </p>
                                        @else
                                            <p class="text-sm text-gray-500">Not connected</p>
                                        @endif
                                    </div>
                                </div>
                                
                                <div>
                                    @if($user->hasOAuthProvider('google'))
                                        @if($user->canUnlinkProvider('google'))
                                            <form method="POST" action="{{ route('oauth.unlink', 'google') }}" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button 
                                                    type="submit" 
                                                    class="px-4 py-2 text-sm font-medium text-red-600 bg-red-50 border border-red-200 rounded-md hover:bg-red-100"
                                                    onclick="return confirm('Are you sure you want to unlink your Google account?')"
                                                >
                                                    Unlink
                                                </button>
                                            </form>
                                        @else
                                            <span class="px-4 py-2 text-sm text-gray-500 bg-gray-100 border border-gray-200 rounded-md">
                                                Primary Login
                                            </span>
                                        @endif
                                    @else
                                        <a 
                                            href="{{ route('oauth.link', 'google') }}" 
                                            class="px-4 py-2 text-sm font-medium text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-md hover:bg-indigo-100"
                                        >
                                            Connect
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Privacy Policy Section -->
                    @if(!$user->privacy_policy_accepted_at)
                    <div class="border-t pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Privacy Policy</h3>
                        <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded mb-4">
                            <p class="text-sm">
                                <strong>Action Required:</strong> Please review and accept our privacy policy to continue using the platform.
                            </p>
                        </div>
                        <div class="flex items-center">
                            <input 
                                id="privacy_policy_accepted_at" 
                                name="privacy_policy_accepted_at" 
                                type="checkbox" 
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded @error('privacy_policy_accepted_at') border-red-500 @enderror"
                                required
                            >
                            <label for="privacy_policy_accepted_at" class="ml-2 block text-sm text-gray-900">
                                I agree to the 
                                <a href="#" class="text-indigo-600 hover:text-indigo-500" onclick="openPrivacyModal()">
                                    Privacy Policy
                                </a>
                            </label>
                        </div>
                        @error('privacy_policy_accepted_at')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    @endif

                    <!-- Developer Notes (Developer Role Only) -->
                    @if($user->isDeveloper())
                    <div class="border-t pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Developer Notes</h3>
                        <div>
                            <label for="developer_notes" class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea 
                                name="developer_notes" 
                                id="developer_notes" 
                                rows="4"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('developer_notes') border-red-500 @enderror"
                                placeholder="Add any development notes or reminders..."
                            >{{ old('developer_notes', $user->developer_notes) }}</textarea>
                            @error('developer_notes')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    @endif

                    <!-- Account Information -->
                    <div class="border-t pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Account Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                            <div>
                                <p class="text-gray-600">Role</p>
                                <p class="font-medium capitalize">{{ $user->role }}</p>
                            </div>
                            <div>
                                <p class="text-gray-600">Member Since</p>
                                <p class="font-medium">{{ $user->created_at->format('F j, Y') }}</p>
                            </div>
                            <div>
                                <p class="text-gray-600">Authentication Type</p>
                                <p class="font-medium">
                                    @if($user->isOAuthOnly())
                                        Gmail Only
                                    @elseif($user->hasHybridAuth())
                                        Hybrid (Gmail + Password)
                                    @else
                                        Password Only
                                    @endif
                                </p>
                            </div>
                            <div>
                                <p class="text-gray-600">Two-Factor Authentication</p>
                                <p class="font-medium">
                                    @if($user->hasTwoFactorEnabled())
                                        <span class="text-green-600">Enabled</span>
                                    @else
                                        <span class="text-red-600">Disabled</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- GDPR Data Management -->
                    <div class="border-t pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Data Management (GDPR)</h3>
                        <div class="bg-gray-50 p-4 rounded-lg space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-medium text-gray-900">Export Your Data</h4>
                                    <p class="text-sm text-gray-600">Download a copy of all your personal data stored in our system.</p>
                                </div>
                                <button 
                                    type="button"
                                    @click="exportData()"
                                    :disabled="loading"
                                    class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 disabled:opacity-50"
                                >
                                    <span x-show="!loading">Export Data</span>
                                    <span x-show="loading">Exporting...</span>
                                </button>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-medium text-gray-900">Anonymize Account</h4>
                                    <p class="text-sm text-gray-600">Replace your personal information with anonymous data. This action cannot be undone.</p>
                                </div>
                                <button 
                                    type="button"
                                    @click="showAnonymizeModal = true"
                                    class="px-4 py-2 bg-yellow-600 text-white text-sm font-medium rounded-md hover:bg-yellow-700"
                                >
                                    Anonymize
                                </button>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-medium text-gray-900">Request Account Deletion</h4>
                                    <p class="text-sm text-gray-600">Request permanent deletion of your account and all associated data.</p>
                                </div>
                                <button 
                                    type="button"
                                    @click="showDeletionModal = true"
                                    class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700"
                                >
                                    Request Deletion
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end">
                        <button 
                            type="submit" 
                            :disabled="loading"
                            class="px-6 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                        >
                            <span x-show="!loading">Update Profile</span>
                            <span x-show="loading">Updating...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Privacy Policy Modal (reuse from register.blade.php) -->
<div id="privacyModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden" style="z-index: 1000;">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Privacy Policy</h3>
                <button onclick="closePrivacyModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="max-h-96 overflow-y-auto">
                <div class="text-sm text-gray-700 space-y-4">
                    <p><strong>Last updated:</strong> {{ date('F j, Y') }}</p>
                    
                    <h4 class="font-semibold">1. Information We Collect</h4>
                    <p>We collect information you provide directly to us, such as when you create an account, use our services, or contact us for support.</p>
                    
                    <h4 class="font-semibold">2. How We Use Your Information</h4>
                    <p>We use the information we collect to provide, maintain, and improve our services, process transactions, and communicate with you.</p>
                    
                    <h4 class="font-semibold">3. Information Sharing</h4>
                    <p>We do not sell, trade, or otherwise transfer your personal information to third parties without your consent, except as described in this policy.</p>
                    
                    <h4 class="font-semibold">4. Data Security</h4>
                    <p>We implement appropriate security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction.</p>
                    
                    <h4 class="font-semibold">5. Your Rights</h4>
                    <p>You have the right to access, update, or delete your personal information. You may also request a copy of your data or ask us to stop processing it.</p>
                    
                    <h4 class="font-semibold">6. Contact Us</h4>
                    <p>If you have any questions about this Privacy Policy, please contact us at privacy@example.com.</p>
                </div>
            </div>
            <div class="flex justify-end mt-6 space-x-3">
                <button 
                    onclick="closePrivacyModal()" 
                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400"
                >
                    Close
                </button>
                <button 
                    onclick="acceptPrivacyPolicy()" 
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                >
                    Accept
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Account Anonymization Modal -->
<div x-show="showAnonymizeModal" 
     x-transition:enter="ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full" 
     style="z-index: 1000;">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Anonymize Account</h3>
                <button @click="showAnonymizeModal = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="text-sm text-gray-700 space-y-4">
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded">
                    <p><strong>Warning:</strong> This action cannot be undone!</p>
                </div>
                <p>Anonymizing your account will:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>Replace your name with "Anonymous User [ID]"</li>
                    <li>Replace your email with an anonymized address</li>
                    <li>Remove your profile picture</li>
                    <li>Disable two-factor authentication</li>
                    <li>Remove OAuth provider connections</li>
                    <li>Clear developer notes</li>
                    <li>Log you out immediately</li>
                </ul>
                <p>Your licenses and chat history will remain but will be associated with your anonymized identity.</p>
                
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Type "ANONYMIZE" to confirm:
                    </label>
                    <input 
                        type="text" 
                        x-model="anonymizeConfirmation"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500"
                        placeholder="ANONYMIZE"
                    >
                </div>
            </div>
            <div class="flex justify-end mt-6 space-x-3">
                <button 
                    @click="showAnonymizeModal = false" 
                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400"
                >
                    Cancel
                </button>
                <button 
                    @click="anonymizeAccount()"
                    :disabled="anonymizeConfirmation !== 'ANONYMIZE' || loading"
                    class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 disabled:opacity-50"
                >
                    <span x-show="!loading">Anonymize Account</span>
                    <span x-show="loading">Processing...</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Account Deletion Request Modal -->
<div x-show="showDeletionModal" 
     x-transition:enter="ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full" 
     style="z-index: 1000;">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Request Account Deletion</h3>
                <button @click="showDeletionModal = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="text-sm text-gray-700 space-y-4">
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">
                    <p><strong>Warning:</strong> This will permanently delete your account and all associated data!</p>
                </div>
                <p>Requesting account deletion will:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>Submit a deletion request to our administrators</li>
                    <li>Send you an email confirmation within 24 hours</li>
                    <li>Permanently delete all your data after confirmation</li>
                    <li>Remove all licenses, chat messages, and audit logs</li>
                </ul>
                
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Reason for deletion (optional):
                    </label>
                    <textarea 
                        x-model="deletionReason"
                        rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                        placeholder="Please let us know why you're leaving..."
                    ></textarea>
                </div>
                
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Type "DELETE" to confirm:
                    </label>
                    <input 
                        type="text" 
                        x-model="deletionConfirmation"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                        placeholder="DELETE"
                    >
                </div>
            </div>
            <div class="flex justify-end mt-6 space-x-3">
                <button 
                    @click="showDeletionModal = false" 
                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400"
                >
                    Cancel
                </button>
                <button 
                    @click="requestDeletion()"
                    :disabled="deletionConfirmation !== 'DELETE' || loading"
                    class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50"
                >
                    <span x-show="!loading">Request Deletion</span>
                    <span x-show="loading">Submitting...</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function openPrivacyModal() {
    document.getElementById('privacyModal').classList.remove('hidden');
}

function closePrivacyModal() {
    document.getElementById('privacyModal').classList.add('hidden');
}

function acceptPrivacyPolicy() {
    document.getElementById('privacy_policy_accepted_at').checked = true;
    closePrivacyModal();
}

function profileManager() {
    return {
        loading: false,
        avatarPreview: null,
        showAnonymizeModal: false,
        showDeletionModal: false,
        anonymizeConfirmation: '',
        deletionConfirmation: '',
        deletionReason: '',

        previewAvatar(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.avatarPreview = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        },

        async deleteAvatar() {
            if (!confirm('Are you sure you want to delete your profile picture?')) {
                return;
            }

            this.loading = true;
            try {
                const response = await fetch('{{ route("profile.avatar.delete") }}', {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    }
                });

                if (response.ok) {
                    location.reload();
                } else {
                    alert('Failed to delete avatar. Please try again.');
                }
            } catch (error) {
                alert('An error occurred. Please try again.');
            } finally {
                this.loading = false;
            }
        },

        async exportData() {
            this.loading = true;
            try {
                const response = await fetch('{{ route("profile.export-data") }}', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                    }
                });

                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = response.headers.get('Content-Disposition').split('filename=')[1].replace(/"/g, '');
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                } else {
                    alert('Failed to export data. Please try again.');
                }
            } catch (error) {
                alert('An error occurred. Please try again.');
            } finally {
                this.loading = false;
            }
        },

        async anonymizeAccount() {
            this.loading = true;
            try {
                const response = await fetch('{{ route("profile.anonymize") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        confirmation: this.anonymizeConfirmation
                    })
                });

                const data = await response.json();

                if (response.ok) {
                    alert(data.message);
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                } else {
                    alert(data.message || 'Failed to anonymize account. Please try again.');
                }
            } catch (error) {
                alert('An error occurred. Please try again.');
            } finally {
                this.loading = false;
            }
        },

        async requestDeletion() {
            this.loading = true;
            try {
                const response = await fetch('{{ route("profile.request-deletion") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        confirmation: this.deletionConfirmation,
                        reason: this.deletionReason
                    })
                });

                const data = await response.json();

                if (response.ok) {
                    alert(data.message);
                    this.showDeletionModal = false;
                    this.deletionConfirmation = '';
                    this.deletionReason = '';
                } else {
                    alert(data.message || 'Failed to submit deletion request. Please try again.');
                }
            } catch (error) {
                alert('An error occurred. Please try again.');
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>
@endsection
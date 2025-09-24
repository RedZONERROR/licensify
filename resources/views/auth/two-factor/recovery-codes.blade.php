@extends('layouts.app')

@section('content')


    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="max-w-md mx-auto">
                        @if(session('status'))
                            <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-md">
                                {{ session('status') }}
                            </div>
                        @endif

                        @if(session('recoveryCodes'))
                            <div class="mb-6 p-4 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded-md">
                                <h4 class="font-medium mb-2">New Recovery Codes Generated</h4>
                                <p class="text-sm mb-3">
                                    Your old recovery codes have been invalidated. Store these new codes in a secure location.
                                </p>
                                <div class="grid grid-cols-2 gap-2 font-mono text-sm">
                                    @foreach(session('recoveryCodes') as $code)
                                        <div class="bg-white p-2 rounded border">{{ $code }}</div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">
                                Recovery Codes
                            </h3>
                            <p class="text-sm text-gray-600 mb-4">
                                Recovery codes can be used to access your account in the event you lose access to your two-factor authentication device. Each code can only be used once.
                            </p>

                            @if(count($recoveryCodes) > 0)
                                <div class="p-4 bg-gray-50 rounded-lg">
                                    <div class="grid grid-cols-2 gap-2 font-mono text-sm">
                                        @foreach($recoveryCodes as $code)
                                            <div class="bg-white p-2 rounded border text-center">{{ $code }}</div>
                                        @endforeach
                                    </div>
                                    <p class="mt-3 text-xs text-gray-500">
                                        {{ count($recoveryCodes) }} codes remaining
                                    </p>
                                </div>
                            @else
                                <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                                    <p class="text-sm text-yellow-800">
                                        You have no recovery codes remaining. Generate new codes to ensure you can access your account.
                                    </p>
                                </div>
                            @endif
                        </div>

                        <!-- Regenerate Codes -->
                        <div class="mb-6">
                            <form method="POST" action="{{ route('two-factor.recovery-codes.regenerate') }}" x-data="{ showForm: false }">
                                @csrf

                                <button 
                                    type="button"
                                    @click="showForm = !showForm"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                >
                                    Regenerate Recovery Codes
                                </button>

                                <div x-show="showForm" x-transition class="mt-4">
                                    <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg mb-4">
                                        <p class="text-sm text-yellow-800">
                                            <strong>Warning:</strong> Regenerating recovery codes will invalidate all existing codes. Make sure to save the new codes in a secure location.
                                        </p>
                                    </div>

                                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                        Confirm your password to regenerate codes:
                                    </label>
                                    <input 
                                        type="password" 
                                        id="password"
                                        name="password" 
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent mb-3"
                                        placeholder="Enter your password"
                                    >
                                    @error('password')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror

                                    <div class="flex space-x-3">
                                        <button 
                                            type="submit"
                                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                        >
                                            Regenerate Codes
                                        </button>
                                        <button 
                                            type="button"
                                            @click="showForm = false"
                                            class="px-4 py-2 text-gray-600 hover:text-gray-800"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Back to 2FA Management -->
                        <div class="border-t pt-6">
                            <a 
                                href="{{ route('two-factor.show') }}"
                                class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                            >
                                ← Back to 2FA Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
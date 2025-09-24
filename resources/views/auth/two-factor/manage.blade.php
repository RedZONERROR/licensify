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
                                <h4 class="font-medium mb-2">Recovery Codes Generated</h4>
                                <p class="text-sm mb-3">
                                    Store these recovery codes in a secure location. They can be used to recover access to your account if your two-factor authentication device is lost.
                                </p>
                                <div class="grid grid-cols-2 gap-2 font-mono text-sm">
                                    @foreach(session('recoveryCodes') as $code)
                                        <div class="bg-white p-2 rounded border">{{ $code }}</div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="mb-6">
                            <div class="flex items-center mb-4">
                                <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                                <h3 class="text-lg font-medium text-gray-900">
                                    Two-Factor Authentication is Enabled
                                </h3>
                            </div>
                            <p class="text-sm text-gray-600">
                                Your account is protected with two-factor authentication. You will be prompted for a verification code when performing sensitive operations.
                            </p>
                        </div>

                        <!-- Recovery Codes -->
                        <div class="mb-6">
                            <a 
                                href="{{ route('two-factor.recovery-codes') }}"
                                class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                            >
                                View Recovery Codes
                            </a>
                        </div>

                        <!-- Disable 2FA -->
                        <div class="border-t pt-6">
                            <h4 class="text-lg font-medium text-gray-900 mb-2">
                                Disable Two-Factor Authentication
                            </h4>
                            <p class="text-sm text-gray-600 mb-4">
                                Disabling two-factor authentication will make your account less secure.
                            </p>

                            <form method="POST" action="{{ route('two-factor.destroy') }}" x-data="{ showForm: false }">
                                @csrf
                                @method('DELETE')

                                <button 
                                    type="button"
                                    @click="showForm = !showForm"
                                    class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                >
                                    Disable 2FA
                                </button>

                                <div x-show="showForm" x-transition class="mt-4">
                                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                        Confirm your password to disable 2FA:
                                    </label>
                                    <input 
                                        type="password" 
                                        id="password"
                                        name="password" 
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent mb-3"
                                        placeholder="Enter your password"
                                    >
                                    @error('password')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror

                                    <div class="flex space-x-3">
                                        <button 
                                            type="submit"
                                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                        >
                                            Confirm Disable
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
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
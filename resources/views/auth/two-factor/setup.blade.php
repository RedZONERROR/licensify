@extends('layouts.app')

@section('content')


    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="max-w-md mx-auto">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">
                                Enable Two-Factor Authentication
                            </h3>
                            <p class="text-sm text-gray-600">
                                Two-factor authentication adds an additional layer of security to your account by requiring more than just a password to sign in.
                            </p>
                        </div>

                        <form method="POST" action="{{ route('two-factor.store') }}" x-data="{ showSecret: false }">
                            @csrf
                            <input type="hidden" name="secret" value="{{ $secret }}">

                            <!-- QR Code -->
                            <div class="mb-6 text-center">
                                <div class="inline-block p-4 bg-white border border-gray-300 rounded-lg">
                                    {!! $qrCode !!}
                                </div>
                                <p class="mt-2 text-sm text-gray-600">
                                    Scan this QR code with your authenticator app
                                </p>
                            </div>

                            <!-- Manual Secret Key -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Or enter this secret key manually:
                                </label>
                                <div class="flex items-center space-x-2">
                                    <input 
                                        type="text" 
                                        :type="showSecret ? 'text' : 'password'"
                                        value="{{ $secret }}" 
                                        readonly 
                                        class="flex-1 px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm font-mono"
                                    >
                                    <button 
                                        type="button"
                                        @click="showSecret = !showSecret"
                                        class="px-3 py-2 text-sm text-gray-600 hover:text-gray-800"
                                    >
                                        <span x-show="!showSecret">Show</span>
                                        <span x-show="showSecret">Hide</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Verification Code -->
                            <div class="mb-6">
                                <label for="code" class="block text-sm font-medium text-gray-700 mb-2">
                                    Enter the 6-digit code from your authenticator app:
                                </label>
                                <input 
                                    type="text" 
                                    id="code"
                                    name="code" 
                                    maxlength="6"
                                    pattern="[0-9]{6}"
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-center text-lg font-mono"
                                    placeholder="000000"
                                    autocomplete="off"
                                >
                                @error('code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Submit Button -->
                            <div class="flex justify-between">
                                <a href="{{ route('dashboard') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
                                    Cancel
                                </a>
                                <button 
                                    type="submit"
                                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                >
                                    Enable 2FA
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
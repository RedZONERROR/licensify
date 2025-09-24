@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-md mx-auto bg-white shadow-md rounded-lg p-6">
        <div class="mb-4 text-sm text-gray-600">
            {{ __('Please confirm access to your account by entering the authentication code provided by your authenticator application.') }}
        </div>

        <form method="POST" action="{{ route('two-factor.verify') }}" x-data="{ recovery: false }">
            @csrf

            <div x-show="!recovery">
                <label for="code" class="block font-medium text-sm text-gray-700">{{ __('Code') }}</label>
                <input 
                    id="code" 
                    class="block mt-1 w-full text-center border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" 
                    type="text" 
                    name="code" 
                    maxlength="6"
                    pattern="[0-9]{6}"
                    autocomplete="one-time-code" 
                    autofocus 
                    placeholder="000000"
                />
                @error('code')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div x-show="recovery">
                <label for="recovery_code" class="block font-medium text-sm text-gray-700">{{ __('Recovery Code') }}</label>
                <input 
                    id="recovery_code" 
                    class="block mt-1 w-full text-center border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" 
                    type="text" 
                    name="code" 
                    maxlength="8"
                    autocomplete="one-time-code" 
                    placeholder="XXXXXXXX"
                />
                <input type="hidden" name="recovery" x-bind:value="recovery">
                @error('code')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-between mt-4">
                <button 
                    type="button"
                    @click="recovery = !recovery"
                    class="text-sm text-gray-600 hover:text-gray-900 underline"
                >
                    <span x-show="!recovery">{{ __('Use a recovery code') }}</span>
                    <span x-show="recovery">{{ __('Use authentication code') }}</span>
                </button>

                <button type="submit" class="ml-3 inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    {{ __('Verify') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
@extends('layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center px-4">
    <div class="max-w-md w-full text-center">
        <!-- Logo -->
        <div class="mb-8">
            <div class="w-16 h-16 mx-auto rounded-lg flex items-center justify-center mb-4">
                <img
                    src="{{ asset('images/logo-64.png') }}?v={{ config('app.asset_version') }}"
                    srcset="{{ asset('images/logo-64.png') }}?v={{ config('app.asset_version') }} 1x, {{ asset('images/logo-64-2x.png') }}?v={{ config('app.asset_version') }} 2x"
                    alt="{{ config('app.name') }} Logo"
                    class="w-full h-full object-contain" />
            </div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ config('app.name') }}</h1>
        </div>

        <!-- Error Content -->
        <div class="mb-8">
            <div class="text-6xl font-bold text-destructive-500 mb-4">500</div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ __("Server Error") }}</h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6">
                {{ __("Something went wrong on our end. We're working to fix this issue. Please try again in a few moments.") }}
            </p>
        </div>

        <!-- Actions -->
        <div class="space-y-3">
            <button onclick="window.location.reload()" 
                    class="inline-flex items-center justify-center w-full px-4 py-2 bg-primary-500 text-white font-medium rounded-md hover:bg-primary-600 dark:hover:bg-primary-400 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                {{ __("Try Again") }}
            </button>
            
            <a href="{{ route('landing') }}" 
               class="inline-flex items-center justify-center w-full px-4 py-2 border border-gray-300 dark:border-gray-500 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-100 font-medium rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                {{ __("Go Home") }}
            </a>
        </div>

        <!-- Help Text -->
        <div class="mt-8 text-sm text-gray-500 dark:text-gray-400">
            <p>{{ __("If this problem persists, please contact our support team.") }}</p>
        </div>
    </div>
</div>
@endsection
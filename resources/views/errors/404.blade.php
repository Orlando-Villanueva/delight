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
            <div class="text-6xl font-bold text-primary-500 mb-4">404</div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ __("Page Not Found") }}</h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6">
                {{ __("The page you're looking for doesn't exist. It might have been moved, deleted, or you entered the wrong URL.") }}
            </p>
        </div>

        <!-- Actions -->
        <div class="space-y-3">
            <a href="{{ route('landing') }}"
                class="inline-flex items-center justify-center w-full px-4 py-2 bg-primary-500 text-white font-medium rounded-md hover:bg-primary-600 dark:hover:bg-primary-400 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                {{ __("Go Home") }}
            </a>

            <button onclick="history.back()"
                class="inline-flex items-center justify-center w-full px-4 py-2 border border-gray-300 dark:border-gray-500 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-100 font-medium rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                {{ __("Go Back") }}
            </button>
        </div>

        <!-- Help Text -->
        <div class="mt-8 text-sm text-gray-500 dark:text-gray-400">
            <p>{{ __("If you believe this is an error, please contact support.") }}</p>
        </div>
    </div>
</div>
@endsection
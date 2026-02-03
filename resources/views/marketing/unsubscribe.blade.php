@extends('layouts.app')

@section('title', 'Email Preferences - Delight')

@section('content')
    <div class="min-h-screen flex items-center justify-center px-4 py-12">
        <div class="max-w-md w-full bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-8">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Email Preferences</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Manage your marketing email settings</p>
            </div>

            @if (session('status'))
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 p-4 rounded-lg mb-6">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>{{ session('status') }}</span>
                    </div>
                </div>
                <p class="text-gray-600 dark:text-gray-300 text-center mb-6">
                    You won't receive churn recovery or other marketing emails anymore.
                </p>
                <div class="text-center">
                    <a href="{{ route('landing') }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors">
                        Return to Home
                    </a>
                </div>
            @elseif ($isOptedOut)
                <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 text-yellow-700 dark:text-yellow-300 p-4 rounded-lg mb-6">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <span>You are already unsubscribed from marketing emails.</span>
                    </div>
                </div>
                <div class="text-center">
                    <a href="{{ route('landing') }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors">
                        Return to Home
                    </a>
                </div>
            @else
                <p class="text-gray-600 dark:text-gray-300 mb-6 text-center">
                    Are you sure you want to unsubscribe from Delight marketing emails?
                </p>

                <form action="{{ request()->fullUrl() }}" method="POST" class="space-y-4">
                    @csrf

                    <div class="flex flex-col sm:flex-row gap-3">
                        <button type="submit"
                            class="flex-1 bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-medium transition-colors">
                            Yes, Unsubscribe
                        </button>
                        <a href="{{ route('landing') }}"
                            class="flex-1 text-center bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 px-6 py-3 rounded-lg font-medium transition-colors">
                            Cancel
                        </a>
                    </div>
                </form>

                <p class="text-xs text-gray-400 dark:text-gray-500 text-center mt-6">
                    This will only stop marketing emails. You'll still receive important account notifications.
                </p>
            @endif
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center px-4 bg-gray-50 dark:bg-gray-900">
    <div class="w-full max-w-md mx-auto">
        <!-- Reset Password Card -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700 p-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-200 mb-2">
                    {{ __("Set new password") }}
                </h2>
                <p class="text-gray-600 dark:text-gray-300">
                    {{ __("Enter your new password to complete the reset process") }}
                </p>
            </div>

            <!-- Reset Password Form -->
            <form method="POST" action="{{ route('password.update') }}" class="space-y-6">
                @csrf

                <!-- Hidden Token Field -->
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <!-- Display Validation Errors -->
                @if ($errors->any())
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                        <div class="flex items-center mb-2">
                            <svg class="w-4 h-4 text-red-600 dark:text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-red-800 dark:text-red-400 font-medium text-sm">{{ __("Please correct the following errors:") }}</span>
                        </div>
                        <ul class="text-sm text-red-700 dark:text-red-400 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>• {{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Email Field (readonly, pre-filled) -->
                <div>
                    <label for="email" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __("Email") }}</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ $request->email ?? old('email') }}"
                        required
                        readonly
                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all bg-gray-50 dark:bg-gray-600 text-gray-600 dark:text-gray-300 {{ $errors->has('email') ? 'border-red-300 focus:ring-red-500' : '' }}"
                    />
                    @if($errors->has('email'))
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $errors->first('email') }}</p>
                    @endif
                </div>

                <!-- New Password Field -->
                <div>
                    <label for="password" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __("New Password") }}</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 placeholder-gray-500 dark:placeholder-gray-400 {{ $errors->has('password') ? 'border-red-300 focus:ring-red-500' : '' }}"
                        placeholder=""
                    />
                    @if($errors->has('password'))
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $errors->first('password') }}</p>
                    @endif
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __("Use 8+ characters with a mix of letters, numbers & symbols") }}</p>
                </div>

                <!-- Confirm Password Field -->
                <div>
                    <label for="password_confirmation" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __("Confirm New Password") }}</label>
                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 placeholder-gray-500 dark:placeholder-gray-400 {{ $errors->has('password_confirmation') ? 'border-red-300 focus:ring-red-500' : '' }}"
                        placeholder=""
                    />
                    @if($errors->has('password_confirmation'))
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $errors->first('password_confirmation') }}</p>
                    @endif
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __("Re-enter your password to confirm") }}</p>
                </div>

                <!-- Submit Button -->
                <button
                    type="submit"
                    class="w-full bg-primary-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors"
                >
                    {{ __("Reset Password") }}
                </button>
            </form>

            <!-- Back to Login Link -->
            <div class="text-center mt-8">
                <p class="text-gray-600 dark:text-gray-300">
                    {{ __("Remember your password?") }}
                    <a href="{{ route('login') }}"
                       class="font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-500 dark:hover:text-primary-300 transition-colors">
                        {{ __("Back to sign in") }}
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

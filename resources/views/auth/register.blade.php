@extends('layouts.app')

@section('content')
    <div
        class="min-h-screen bg-gradient-to-br from-[#F5F7FA] to-white dark:from-gray-900 dark:to-gray-800 flex items-center justify-center px-4 sm:px-6 lg:px-8 py-8">
        <div class="w-full max-w-md space-y-6">
            <!-- Logo Section -->
            <div class="text-center">
                <img src="{{ asset('images/logo-64.png') }}?v={{ config('app.asset_version') }}"
                    srcset="{{ asset('images/logo-64.png') }}?v={{ config('app.asset_version') }} 1x, {{ asset('images/logo-64-2x.png') }}?v={{ config('app.asset_version') }} 2x"
                    alt="{{ config('app.name') }} Logo" class="w-20 h-20 object-contain mx-auto mb-4"
                    style="filter: drop-shadow(0 0 15px rgba(51, 102, 204, 0.2)) drop-shadow(0 0 30px rgba(51, 102, 204, 0.08));" />
                <h1 class="text-2xl font-bold text-[#4A5568] dark:text-gray-200 mb-1">{{ config('app.name') }}</h1>
            </div>

            <!-- Form Card -->
            <div
                class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-[#D1D7E0] dark:border-gray-700 p-8 sm:p-10 mt-8">
                <div class="space-y-6">
                    <!-- Header -->
                    <div class="text-center mb-8">
                        <h2 class="text-2xl font-bold text-[#4A5568] dark:text-gray-200 mb-2">
                            Create account
                        </h2>
                        <p class="text-[#4A5568] dark:text-gray-300 opacity-75">
                            Start your Bible reading journey today
                        </p>
                    </div>

                    <!-- Single Entry Social Login -->
                    <div class="space-y-4">
                        <a href="{{ route('oauth.google.redirect') }}"
                            class="flex items-center justify-center gap-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg py-3 px-4 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-all duration-200">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" aria-hidden="true">
                                <path fill="#FFC107"
                                    d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z" />
                                <path fill="#FF3D00"
                                    d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z" />
                                <path fill="#4CAF50"
                                    d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z" />
                                <path fill="#1976D2"
                                    d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z" />
                            </svg>

                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                Continue with Google
                            </span>
                        </a>
                        <div class="flex items-center gap-3">
                            <div class="h-px flex-1 bg-gray-200 dark:bg-gray-700"></div>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                or continue with email
                            </span>
                            <div class="h-px flex-1 bg-gray-200 dark:bg-gray-700"></div>
                        </div>
                    </div>

                    <!-- Registration Form -->
                    <form method="POST" action="{{ route('register') }}" class="space-y-6">
                        @csrf

                        <!-- Display Validation Errors -->
                        @if ($errors->any())
                            <div
                                class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                                <div class="flex items-center mb-2">
                                    <svg class="w-4 h-4 text-red-600 dark:text-red-400 mr-2" fill="currentColor"
                                        viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-red-800 dark:text-red-400 font-medium text-sm">Please correct the
                                        following errors:</span>
                                </div>
                                <ul class="text-sm text-red-700 dark:text-red-400 space-y-1">
                                    @foreach ($errors->all() as $error)
                                        <li>• {{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <!-- Name Field -->
                        <div>
                            <label for="name"
                                class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Name</label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}" required
                                class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 placeholder-gray-500 dark:placeholder-gray-400 {{ $errors->has('name') ? 'border-red-300 focus:ring-red-500' : '' }}"
                                placeholder="" />
                            @if ($errors->has('name'))
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $errors->first('name') }}</p>
                            @endif
                        </div>

                        <!-- Email Field -->
                        <div>
                            <label for="email"
                                class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Email</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" required
                                class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 placeholder-gray-500 dark:placeholder-gray-400 {{ $errors->has('email') ? 'border-red-300 focus:ring-red-500' : '' }}"
                                placeholder="" />
                            @if ($errors->has('email'))
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $errors->first('email') }}</p>
                            @endif
                        </div>

                        <!-- Password Field -->
                        <div>
                            <label for="register-password"
                                class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Password</label>
                            <div class="relative flex items-center" x-data="{ showPassword: false }">
                                <input :type="showPassword ? 'text' : 'password'" id="register-password" name="password"
                                    required
                                    class="w-full px-4 py-3 pr-12 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 placeholder-gray-500 dark:placeholder-gray-400 {{ $errors->has('password') ? 'border-red-300 focus:ring-red-500' : '' }}"
                                    placeholder="Minimum 8 characters" />
                                <button type="button" @click="showPassword = !showPassword"
                                    class="absolute right-4 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none"
                                    aria-label="Toggle password visibility">
                                    <svg x-show="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <svg x-show="showPassword" class="w-5 h-5" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17.94 17.94A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m2.13-2.13C7.523 5 12 5 12 5c4.478 0 8.268 2.943 9.542 7a9.97 9.97 0 01-1.67 2.882M15 12a3 3 0 11-6 0 3 3 0 016 0zM3 3l18 18" />
                                    </svg>
                                </button>
                            </div>
                            @if ($errors->has('password'))
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $errors->first('password') }}</p>
                            @endif
                        </div>
                        <!-- Password Confirmation Field -->
                        <div>
                            <label for="register-password-confirmation"
                                class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Confirm
                                Password</label>
                            <input type="password" id="register-password-confirmation" name="password_confirmation" required
                                class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 placeholder-gray-500 dark:placeholder-gray-400 {{ $errors->has('password_confirmation') ? 'border-red-300 focus:ring-red-500' : '' }}"
                                placeholder="Re-enter your password" />
                            @if ($errors->has('password_confirmation'))
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                                    {{ $errors->first('password_confirmation') }}</p>
                            @endif
                        </div>

                        <!-- Submit Button -->
                        <button type="submit"
                            class="w-full bg-primary-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-primary-600 hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-all duration-200">
                            Create Account
                        </button>
                    </form>

                    <!-- Login Link -->
                    <div class="text-center mt-8">
                        <p class="text-[#4A5568] dark:text-gray-300 opacity-75">
                            Already have an account?
                            <a href="{{ route('login') }}"
                                class="font-semibold text-primary-600 dark:text-blue-400 hover:text-primary-500 dark:hover:text-blue-300 transition-colors">
                                Sign in
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

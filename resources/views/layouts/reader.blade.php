@php
    $theme = $theme ?? 'default';

    $navClasses = match ($theme) {
        'cosmic' => 'bg-transparent border-b border-white/10',
        default => 'bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800',
    };

    $navLinkClasses = match ($theme) {
        'cosmic' => 'text-sm font-medium text-gray-400 hover:text-white transition-colors',
        default
            => 'text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors',
    };

    $logoTextClasses = match ($theme) {
        'cosmic' => 'font-bold text-xl tracking-tight text-white',
        default => 'font-bold text-xl tracking-tight text-gray-900 dark:text-white',
    };

    $separatorClasses = match ($theme) {
        'cosmic' => 'mx-3 text-gray-700 h-6 border-l border-white/10',
        default => 'mx-3 text-gray-300 dark:text-gray-700 h-6 border-l border-gray-300 dark:border-gray-700',
    };

    $updatesLinkClasses = match ($theme) {
        'cosmic' => 'text-sm font-medium text-gray-400 hover:text-white transition-colors',
        default
            => 'text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors',
    };

    $footerClasses = match ($theme) {
        'cosmic' => 'bg-transparent border-t border-white/10 py-10 mt-auto',
        default => 'bg-white dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800 py-10 mt-auto',
    };

    $footerTextClasses = match ($theme) {
        'cosmic' => 'text-sm text-gray-500',
        default => 'text-sm text-gray-500 dark:text-gray-500',
    };

    $footerLinkClasses = match ($theme) {
        'cosmic' => 'text-sm text-gray-400 hover:text-white transition-colors',
        default => 'text-sm text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors',
    };

    $primaryButtonClasses = match ($theme) {
        'cosmic'
            => 'hidden sm:inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-colors shadow-sm',
        default
            => 'hidden sm:inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors shadow-sm',
    };

    $bodyClasses = match ($theme) {
        'cosmic' => 'font-sans antialiased bg-[#0F1115] text-gray-100 min-h-screen flex flex-col',
        default
            => 'font-sans antialiased bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100 min-h-screen flex flex-col',
    };

    $showUpdatesNav = ! request()->routeIs('recap.show');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Title and Meta -->
    <title>@yield('title', config('app.name', 'Delight'))</title>
    @yield('meta')

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <!-- Styles -->
    @vite(['resources/css/app.css'])
</head>

<body class="{{ $bodyClasses }}">

    <!-- Navigation -->
    <nav class="{{ $navClasses }}">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="{{ route('landing') }}" class="flex items-center gap-2">
                        <img src="{{ asset('images/logo-64.png') }}" alt="Delight Logo" class="w-8 h-8">
                        <span class="{{ $logoTextClasses }}">Delight</span>
                    </a>

                    @if ($showUpdatesNav)
                        <span class="{{ $separatorClasses }}"></span>

                        <a href="{{ route('announcements.index') }}" class="{{ $updatesLinkClasses }}">
                            Updates
                        </a>
                    @endif
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="{{ $navLinkClasses }}">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="{{ $navLinkClasses }}">
                            Sign In
                        </a>
                        <a href="{{ route('register') }}" class="{{ $primaryButtonClasses }}">
                            Get Started
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-16">
            @yield('content')
        </div>
    </main>

    <!-- Footer -->
    <footer class="{{ $footerClasses }}">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between">
            <p class="{{ $footerTextClasses }}">
                &copy; {{ date('Y') }} Delight. All rights reserved.
            </p>
            <div class="flex gap-4">
                <a href="{{ route('landing') }}" class="{{ $footerLinkClasses }}">Home</a>
                <a href="{{ route('dashboard') }}" class="{{ $footerLinkClasses }}">App</a>
            </div>
        </div>
    </footer>
</body>

</html>

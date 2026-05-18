<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Delight') }}</title>

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('favicon-app.ico') }}?v={{ config('app.asset_version') }}" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="192x192" href="{{ asset('images/app-icon-v2-192.png') }}?v={{ config('app.asset_version') }}">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="{{ asset('site.webmanifest') }}?v={{ config('app.asset_version') }}">
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#3366CC">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Delight">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    <!-- HTMX CDN -->
    <script src="https://cdn.jsdelivr.net/npm/htmx.org@2.0.5/dist/htmx.min.js"></script>

    <!-- Alpine.js CDN -->
    <script defer src="https://unpkg.com/alpinejs@3.13.3/dist/cdn.min.js"></script>

    <!-- Styles / Scripts -->
    @vite(['resources/css/app.css'])

    <!-- Alpine.js Cloak -->
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen font-sans">
    @if(request()->routeIs('login') || request()->routeIs('register') || request()->routeIs('password.*'))
        <!-- Full-height auth layout -->
        <div class="min-h-screen">

            
            <main>
                @yield('content')
            </main>
        </div>
    @else
        <!-- Regular layout for dashboard/other pages -->
        <div class="container mx-auto px-4 py-4 min-h-screen flex flex-col">
            <header class="mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <a href="{{ url('/') }}"
                        class="flex items-center text-xl font-bold text-primary-500 dark:text-primary-500 hover:opacity-90 transition">
                        <span>{{ config('app.name') }}</span>
                    </a>

                    @if (Route::has('login'))
                        <nav class="flex items-center gap-2 mt-3 sm:mt-0">
                            @auth
                                <a href="{{ url('/dashboard') }}"
                                    class="px-3 py-1.5 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition text-sm font-medium shadow-sm">
                                    Dashboard
                                </a>
                            @else
                                <a href="{{ url('/') }}"
                                    class="px-3 py-1.5 transition text-sm font-medium rounded-lg shadow-sm {{ request()->routeIs('landing') ? 'bg-primary-500 text-white' : 'text-gray-700 dark:text-gray-200 hover:text-primary-500 dark:hover:text-primary-400 hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:border-primary-500 dark:hover:border-primary-400' }}">
                                    Welcome
                                </a>
                                <a href="{{ route('login') }}"
                                    class="px-3 py-1.5 transition text-sm font-medium rounded-lg shadow-sm {{ request()->routeIs('login') || request()->routeIs('password.*') ? 'bg-primary-500 text-white' : 'text-gray-700 dark:text-gray-200 hover:text-primary-500 dark:hover:text-primary-400 hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:border-primary-500 dark:hover:border-primary-400' }}">
                                    Log in
                                </a>

                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}"
                                        class="px-3 py-1.5 transition text-sm font-medium rounded-lg shadow-sm {{ request()->routeIs('register') ? 'bg-primary-500 text-white' : 'text-gray-700 dark:text-gray-200 hover:text-primary-500 dark:hover:text-primary-400 hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:border-primary-500 dark:hover:border-primary-400' }}">
                                        Register
                                    </a>
                                @endif
                            @endauth
                        </nav>
                    @endif

                </div>
            </header>

            <main class="flex-grow flex flex-col justify-center">
                @yield('content')
            </main>

            <footer
                class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 text-sm text-gray-600 dark:text-gray-400">
                <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            </footer>
        </div>
    @endif

    <!-- PWA Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js')
                    .then(function(registration) {
                        console.log('ServiceWorker registration successful with scope: ', registration.scope);
                    }, function(err) {
                        console.log('ServiceWorker registration failed: ', err);
                    });
            });
        }
    </script>
</body>

</html>

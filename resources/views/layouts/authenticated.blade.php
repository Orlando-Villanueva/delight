<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php($appName = config('app.name', 'Delight'))
    <title>
        @hasSection('page-title')
            @yield('page-title') - {{ $appName }}
        @else
            {{ $appName }}
        @endif
    </title>

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="192x192" href="{{ asset('images/logo-192.png') }}">

    <!-- PWA Manifest -->
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

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

    <!-- Flowbite CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>

    <!-- Styles / Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js Cloak -->
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="bg-[#F5F7FA] dark:bg-gray-900 text-gray-600 min-h-screen font-sans antialiased transition-colors">
    <div class="flex h-screen">
        <!-- Desktop: Sidebar and Navbar -->
        <div class="hidden lg:flex">
            <x-navigation.desktop-sidebar />
            <x-navigation.desktop-navbar />
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col lg:overflow-hidden lg:pt-14">
            <!-- Mobile: Navbar (scrolls with content) -->
            <x-navigation.mobile-navbar class="lg:hidden" />

            <main class="flex-1 lg:overflow-y-auto">
                <div id="page-container" class="lg:flex lg:h-full container mx-auto">
                    @yield('content')
                </div>
            </main>
        </div>

        <!-- Mobile: Bottom Bar -->
        <x-navigation.mobile-bottom-bar class="lg:hidden" />
    </div>

    <!-- HTMX History Configuration -->
    <script>
        document.body.addEventListener('htmx:historyRestore', function(evt) {
            // This event is fired when HTMX restores a page from history.
            // You can use it to re-initialize any components or scripts that might
            // have been affected by the content swap.
            console.log('HTMX history restored.');
        });
    </script>

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
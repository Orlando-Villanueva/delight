<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (auth()->user()?->reading_timezone === null)
        @include('partials.reading-timezone-detection')
    @endif
    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon-app.ico') }}?v={{ config('app.asset_version') }}" type="image/x-icon">
    <link rel="apple-touch-icon" href="{{ asset('images/app-icon-v2-192.png') }}">
    <link rel="manifest" href="{{ route('pwa.manifest', ['v' => config('app.asset_version')]) }}">
    <meta name="theme-color" content="#3366CC">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/reader.ts'])
    @inertiaHead
</head>
<body class="bg-[#F5F7FA] dark:bg-gray-900 text-gray-600 min-h-screen font-sans antialiased">
    @inertia
    <noscript><p class="p-6">Enable JavaScript to manage your settings.</p></noscript>
</body>
</html>

@php
    $versionedAsset = function (string $path): string {
        $absolutePath = public_path($path);
        $url = asset($path);

        return file_exists($absolutePath) ? $url.'?v='.filemtime($absolutePath) : $url;
    };

    $desktopScreenshot = $versionedAsset('images/screenshots/desktop-v3.png');
    $mobileScreenshot = $versionedAsset('images/screenshots/mobile-v3.png');
    $linkPreviewScreenshot = $versionedAsset('images/screenshots/link-preview.png');
@endphp

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- SEO Meta Tags -->
    <title>{{ config('app.name', 'Delight') }} - Bible Reading Tracker</title>
    <meta name="description"
        content="Track your Bible reading with streaks, progress visualization, structured reading plans, next milestone guidance, and permanent achievement rewards.">
    <meta name="keywords"
        content="bible reading plan, bible reading plan app, bible tracking app, bible reading tracker, bible habit tracker, bible reading accountability, scripture reading app, daily bible reading, bible progress tracker">
    <meta name="author" content="Delight">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <link rel="canonical" href="{{ config('app.url') }}">

    <!-- Additional SEO Meta Tags -->
    <meta name="language" content="English">
    <meta name="revisit-after" content="7 days">
    <meta name="distribution" content="web">
    <meta name="rating" content="general">
    <meta name="geo.region" content="Global">
    <meta name="geo.placename" content="Worldwide">

    <!-- Sitemap Reference -->
    <link rel="sitemap" type="application/xml" title="Sitemap" href="{{ config('app.url') }}/sitemap.xml">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="Delight - Bible Reading Tracker for Consistency">
    <meta property="og:description"
        content="Track your Bible reading with streaks, structured plans, next milestone guidance, and permanent achievement rewards. Follow a plan or log freely with gentle motivation.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ config('app.url') }}">
    <meta property="og:image" content="{{ $linkPreviewScreenshot }}">
    <meta property="og:site_name" content="Delight">

    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Delight - Bible Tracking App for Consistency">
    <meta name="twitter:description"
        content="Track Bible reading with streaks, progress visualization, structured plans, next milestone guidance, and permanent achievement rewards.">
    <meta name="twitter:image" content="{{ $linkPreviewScreenshot }}">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <!-- Styles -->
    @vite(['resources/css/app.css'])

    <!-- Preload critical hero image for faster LCP (desktop only) -->
    <link rel="preload" as="image" href="{{ $desktopScreenshot }}"
        imagesrcset="{{ $desktopScreenshot }} 1x" fetchpriority="high"
        media="(min-width: 1024px)">

    <!-- Structured Data -->
    <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "WebApplication",
            "name": "Delight",
            "description": "Track your Bible reading with streaks, progress visualization, structured reading plans, next milestone guidance, and permanent achievement rewards.",
            "url": "{{ config('app.url') }}",
            "applicationCategory": "LifestyleApplication",
            "operatingSystem": "Web Browser",
            "browserRequirements": "Requires JavaScript. Requires HTML5.",
            "softwareVersion": "1.0",
            "aggregateRating": {
                "@@type": "AggregateRating",
                "ratingValue": "5.0",
                "ratingCount": "1"
            },
            "offers": {
                "@@type": "Offer",
                "price": "0",
                "priceCurrency": "USD",
                "availability": "https://schema.org/InStock"
            },
            "author": {
                "@@type": "Organization",
                "name": "Delight"
            },
            "keywords": "bible reading plan, bible reading plan app, bible tracking app, bible reading tracker, bible habit tracker",
            "screenshot": "{{ $desktopScreenshot }}",
            "featureList": [
                "Reading Plans",
                "Daily Streak Tracking",
                "Daily Reading Log",
                "Book Completion Grid",
                "Optional Catholic 73-Book Support",
                "Reading Statistics",
                "Permanent Achievement Shelf",
                "Next Milestone Guidance"
            ]
        }
    </script>
</head>

<body class="font-sans antialiased bg-white">
    <!-- Skip to main content link for screen readers -->
    <a href="#main-content"
        class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 bg-blue-600 text-white px-4 py-2 rounded-md z-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
        Skip to main content
    </a>

    <!-- Navigation -->
    <nav class="fixed top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-sm border-b border-gray-100" role="navigation"
        aria-label="Main navigation">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Brand Logo -->
                <div class="flex-shrink-0">
                    <a href="{{ route('landing') }}"
                        class="flex items-center space-x-2 text-2xl font-bold text-gray-900 hover:text-blue-600 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded-md"
                        aria-label="Delight - Go to homepage">
                        <img src="{{ asset('images/logo-64.png') }}"
                            srcset="{{ asset('images/logo-64.png') }} 1x, {{ asset('images/logo-64-2x.png') }} 2x"
                            alt="Delight logo - Bible reading habit tracker" class="w-8 h-8" width="32"
                            height="32" loading="eager" decoding="async" />
                        <span>Delight</span>
                    </a>
                </div>

                <!-- Navigation Actions -->
                <div class="flex items-center space-x-4" role="group" aria-label="Account actions">
                    @auth
                        <a href="{{ route('dashboard') }}"
                            class="text-gray-700 hover:text-blue-600 font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded-md px-2 py-1"
                            aria-label="Go to your dashboard">
                            Dashboard
                        </a>
                    @else
                        <x-ui.button variant="ghost" href="{{ route('login') }}" aria-label="Sign in to your account">
                            Sign In
                        </x-ui.button>
                        <x-ui.button variant="accent" href="{{ route('register') }}" aria-label="Create a new account">
                            Get Started
                        </x-ui.button>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="pt-16" id="main-content" role="main">
        <!-- Hero Section -->
        <section class="relative bg-gradient-to-bl from-blue-50 to-white py-20 lg:py-32"
            aria-labelledby="hero-heading">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-2 gap-2 items-center">
                    <!-- Hero Content -->
                    <div class="text-center lg:text-left lg:pr-24">
                        <h1 id="hero-heading" class="text-4xl md:text-5xl font-bold text-gray-900 leading-tight mb-6">
                            Keep Every Bible Reading Visible, Stay on Track
                        </h1>
                        <p class="text-xl text-gray-600 mb-6 leading-relaxed">
                            Delight logs each chapter in seconds, keeps your current streak and next milestone in view,
                            and makes permanent achievements feel earned without overwhelming the habit.
                        </p>

                        <!-- Primary CTA -->
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                            <x-ui.button variant="accent" size="lg" href="{{ route('register') }}"
                                class="whitespace-normal sm:whitespace-nowrap">
                                Start Building Life-Changing Habits
                            </x-ui.button>
                            <x-ui.button variant="ghost" size="lg" href="#features-heading"
                                aria-label="Scroll to see Delight features"
                                class="whitespace-normal sm:whitespace-nowrap">
                                Explore the Dashboard
                            </x-ui.button>
                        </div>
                    </div>

                    <!-- Hero Visual -->
                    <div class="relative">
                        <!-- Desktop Screenshot - Hidden on mobile -->
                        <div class="hidden lg:block bg-white rounded-2xl shadow-2xl p-0 transform rotate-1">
                            <div class="rounded-lg overflow-hidden">
                                <img src="{{ $desktopScreenshot }}"
                                    alt="Delight desktop dashboard showing daily streak, next milestone, summary stats, calendar, and reading progress grid"
                                    class="w-full h-auto max-w-full" width="3456" height="1924" loading="eager"
                                    fetchpriority="high" decoding="async" />
                            </div>
                        </div>

                        <!-- Mobile Screenshot - Shown on mobile -->
                        <div class="lg:hidden flex justify-center mt-8">
                            <div class="bg-white rounded-2xl shadow-2xl p-0 max-w-xs w-full">
                                <div class="rounded-lg overflow-hidden">
                                    <img src="{{ $mobileScreenshot }}"
                                        alt="Delight mobile dashboard featuring daily streak, next milestone, reading stats, and bottom navigation"
                                        class="w-full h-auto" width="726" height="1570" loading="lazy"
                                        decoding="async" />
                                </div>
                            </div>
                        </div>

                        <!-- Mobile Screenshot - Floating (Desktop only) -->
                        <div
                            class="hidden lg:block absolute -bottom-6 -right-6 w-36 sm:w-40 lg:w-48 bg-white rounded-xl shadow-xl p-0 transform rotate-6">
                            <div class="rounded-lg overflow-hidden">
                                <img src="{{ $mobileScreenshot }}"
                                    alt="Delight mobile dashboard featuring daily streak, next milestone, reading stats, and bottom navigation"
                                    class="w-full h-auto max-w-full" width="726" height="1570" loading="lazy"
                                    decoding="async" fetchpriority="low" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="py-20 bg-primary-50" aria-labelledby="features-heading">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 id="features-heading" class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                        Everything You Need to Stay Consistent
                    </h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Tools designed to help you build and maintain a meaningful Bible reading habit.
                    </p>
                </div>

                <!-- Features Grid -->
                <ul class="grid md:grid-cols-2 lg:grid-cols-3 gap-8"
                    aria-label="Key features of Delight">
                    <!-- Feature 1: Daily Streak Tracking (Featured) -->
                    <li class="order-2">
                        <x-ui.card elevated class="hover:shadow-xl transition-shadow h-full">
                            <x-ui.card-content class="space-y-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <span class="shrink-0 text-orange-500" aria-hidden="true">
                                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 384 512"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path
                                                    d="M216 23.86c0-23.8-30.65-32.77-44.15-13.04C48 191.85 224 200 224 288c0 35.63-29.11 64.46-64.85 63.99-35.17-.45-63.15-29.77-63.15-64.94v-85.51c0-21.7-26.47-32.4-41.6-16.9C21.22 216.4 0 268.2 0 320c0 105.87 86.13 192 192 192s192-86.13 192-192c0-170.29-168-193.17-168-296.14z" />
                                            </svg>
                                        </span>
                                        <x-ui.card-title class="mb-0">Daily Streak Tracking</x-ui.card-title>
                                    </div>
                                    <span
                                        class="text-[11px] font-semibold text-orange-700 bg-white/70 border border-orange-100 px-2 py-1 rounded-full">Core
                                        habit</span>
                                </div>
                                <p class="text-gray-400 text-xs">Watch consistency grow</p>

                                <!-- Mini preview: mirrors streak-counter component states -->
                                <div class="rounded-xl border border-gray-100 bg-white p-3 space-y-2 shadow-sm"
                                    aria-label="Preview of daily streak widget">
                                    <div class="flex items-center justify-between text-sm text-gray-800">
                                        <span class="font-semibold">43 days</span>
                                        <span
                                            class="inline-flex items-center gap-1 text-[11px] font-semibold text-white bg-gradient-to-r from-accent-500 to-amber-400 px-2 py-1 rounded-full shadow-sm">Record</span>
                                    </div>
                                    <div class="relative h-12 overflow-hidden">
                                        <div class="absolute inset-x-0 bottom-2 h-1 bg-orange-200/70 rounded-full">
                                        </div>
                                        <svg viewBox="0 0 140 40" class="w-full h-full" aria-hidden="true">
                                            <polyline fill="none" stroke="#F97316" stroke-width="2.5"
                                                stroke-linecap="round" stroke-linejoin="round"
                                                points="0,32 12,24 24,28 36,20 48,26 60,22 72,26 84,20 96,28 108,24 120,32 132,18 140,24" />
                                        </svg>
                                    </div>
                                </div>

                                <p class="text-gray-600 leading-relaxed">
                                    Build momentum with daily reading streaks. See your current and longest streaks to
                                    stay motivated and celebrate consistency.
                                </p>
                            </x-ui.card-content>
                        </x-ui.card>
                    </li>

                    <!-- Feature 2: Reading Plans (Featured) -->
                    <li class="order-6">
                        <x-ui.card elevated class="hover:shadow-xl transition-shadow h-full">
                            <x-ui.card-content class="space-y-3">
                                <div class="flex items-center gap-3">
                                    <span class="shrink-0 text-indigo-500" aria-hidden="true">
                                        <svg class="w-8 h-8" xmlns="http://www.w3.org/2000/svg" fill="none"
                                            viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round"
                                                stroke-linejoin="round" stroke-width="2"
                                                d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 9 2 2 4-4" />
                                        </svg>
                                    </span>
                                    <x-ui.card-title class="mb-0">Reading Plans</x-ui.card-title>
                                </div>
                                <p class="text-gray-400 text-xs">Structured daily guidance</p>

                                <!-- Mini preview: reading plan progress -->
                                <div class="rounded-xl border border-gray-100 bg-white p-3 space-y-2 shadow-sm"
                                    aria-label="Preview of reading plans feature">
                                    <div class="flex items-center justify-between text-sm text-gray-800">
                                        <span class="font-semibold">Day 42</span>
                                        <span class="text-indigo-600 font-medium">11.5%</span>
                                    </div>
                                    <div class="w-full bg-indigo-100 rounded-full h-2">
                                        <div class="bg-indigo-500 h-2 rounded-full" style="width: 11.5%"></div>
                                    </div>
                                    <div class="flex items-center gap-2 text-sm text-gray-600">
                                        <span
                                            class="inline-flex items-center justify-center w-5 h-5 bg-success-100 text-success-600 rounded-full">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                        <span>Genesis 1-3</span>
                                    </div>
                                </div>

                                <p class="text-gray-600 leading-relaxed">
                                    Follow structured reading plans to guide your Bible journey. Track daily
                                    progress, mark chapters complete, and never lose your place.
                                </p>
                            </x-ui.card-content>
                        </x-ui.card>
                    </li>

                    <!-- Feature 3: Daily Reading Log -->
                    <li class="order-1">
                        <x-ui.card elevated class="hover:shadow-xl transition-shadow h-full">
                            <x-ui.card-content class="space-y-3">
                                <div class="flex items-center gap-3">
                                    <span class="shrink-0 text-gray-800" aria-hidden="true">
                                        <svg class="w-8 h-8" xmlns="http://www.w3.org/2000/svg" width="24"
                                            height="24" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round"
                                                stroke-linejoin="round" stroke-width="2"
                                                d="M12 6.03v13m0-13c-2.819-.831-4.715-1.076-8.029-1.023A.99.99 0 0 0 3 6v11c0 .563.466 1.014 1.03 1.007 3.122-.043 5.018.212 7.97 1.023m0-13c2.819-.831 4.715-1.076 8.029-1.023A.99.99 0 0 1 21 6v11c0 .563-.466 1.014-1.03 1.007-3.122-.043-5.018.212-7.97 1.023" />
                                        </svg>
                                    </span>
                                    <x-ui.card-title class="mb-0">Daily Reading Log</x-ui.card-title>
                                </div>
                                <p class="text-gray-400 text-xs">Never lose your place</p>

                                <!-- Mini preview: compact form footprint -->
                                <div class="rounded-xl border border-gray-100 bg-white p-3 space-y-2 shadow-sm"
                                    aria-label="Preview of reading log form">
                                    <div class="grid grid-cols-2 gap-2 text-sm text-gray-700">
                                        <div class="rounded-lg border border-gray-200 px-3 py-2 bg-gray-50">Book</div>
                                        <div class="rounded-lg border border-gray-200 px-3 py-2 bg-gray-50 text-right">
                                            Chapter</div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="flex-1 rounded-lg border border-gray-200 px-3 py-2 bg-white text-sm text-gray-500">
                                            Notes (optional)</div>
                                        <span
                                            class="inline-flex items-center justify-center text-white bg-accent-500 hover:bg-accent-600 focus:outline-none focus:ring-4 focus:ring-accent-300 font-medium rounded-full text-sm px-5 py-2 shadow dark:bg-accent-600 dark:hover:bg-accent-700 dark:focus:ring-accent-800">
                                            Log
                                        </span>
                                    </div>
                                </div>

                                <p class="text-gray-600 leading-relaxed">
                                    Log your daily reading in seconds with our intuitive book and chapter selector.
                                    Simple tracking that fits seamlessly into your routine.
                                </p>
                            </x-ui.card-content>
                        </x-ui.card>
                    </li>

                    <!-- Feature 4: Book Completion Grid -->
                    <li class="order-4">
                        <x-ui.card elevated class="hover:shadow-xl transition-shadow h-full">
                            <x-ui.card-content class="space-y-3">
                                <div class="flex items-center gap-3">
                                    <span class="shrink-0 text-gray-800" aria-hidden="true">
                                        <svg class="w-8 h-8" xmlns="http://www.w3.org/2000/svg" width="24"
                                            height="24" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round"
                                                stroke-linejoin="round" stroke-width="2"
                                                d="M9.143 4H4.857A.857.857 0 0 0 4 4.857v4.286c0 .473.384.857.857.857h4.286A.857.857 0 0 0 10 9.143V4.857A.857.857 0 0 0 9.143 4Zm10 0h-4.286a.857.857 0 0 0-.857.857v4.286c0 .473.384.857.857.857h4.286A.857.857 0 0 0 20 9.143V4.857A.857.857 0 0 0 19.143 4Zm-10 10H4.857a.857.857 0 0 0-.857.857v4.286c0 .473.384.857.857.857h4.286a.857.857 0 0 0 .857-.857v-4.286A.857.857 0 0 0 9.143 14Zm10 0h-4.286a.857.857 0 0 0-.857.857v4.286c0 .473.384.857.857.857h4.286a.857.857 0 0 0 .857-.857v-4.286a.857.857 0 0 0-.857-.857Z" />
                                        </svg>
                                    </span>
                                    <x-ui.card-title class="mb-0">Book Completion Grid</x-ui.card-title>
                                </div>
                                <p class="text-gray-400 text-xs">Journey comes alive visually</p>

                                <!-- Mini preview: mirrors book-progress cards -->
                                <div class="rounded-xl border border-gray-100 bg-white p-3 space-y-2 shadow-sm"
                                    aria-label="Preview of book completion grid">
                                    <div class="grid grid-cols-6 gap-1">
                                        @php
                                            $colors = [
                                                'bg-success-200 border border-success-300',
                                                'bg-primary-200 border border-primary-300',
                                                'bg-gray-100 border border-gray-200',
                                            ];
                                        @endphp
                                        @for ($i = 0; $i < 18; $i++)
                                            <span
                                                class="h-4 w-full rounded-sm {{ $colors[$i % count($colors)] }}"></span>
                                        @endfor
                                    </div>
                                    <div class="flex items-center justify-between text-[11px] text-gray-500">
                                        <span class="inline-flex items-center gap-1"><span
                                                class="w-3 h-3 bg-success-500 rounded-sm border border-success-500"></span>Completed</span>
                                        <span class="inline-flex items-center gap-1"><span
                                                class="w-3 h-3 bg-primary-500 rounded-sm border border-primary-500"></span>In
                                            progress</span>
                                        <span class="inline-flex items-center gap-1"><span
                                                class="w-3 h-3 bg-white rounded-sm border border-gray-300"></span>Not
                                            started</span>
                                    </div>
                                </div>

                                <p class="text-gray-600 leading-relaxed">
                                    Visualize your Scripture journey across the 66-book canon by default, with optional Catholic 73-book deuterocanonical support available when enabled in Settings.
                                </p>
                            </x-ui.card-content>
                        </x-ui.card>
                    </li>

                    <!-- Feature 5: Permanent Achievements -->
                    <li class="order-5">
                        <x-ui.card
                            class="bg-gradient-to-br from-amber-50 to-white border border-amber-100 shadow-lg/20 hover:shadow-xl transition-shadow h-full">
                            <x-ui.card-content class="space-y-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <span class="shrink-0 text-amber-600" aria-hidden="true">
                                            <svg class="w-8 h-8" xmlns="http://www.w3.org/2000/svg" width="24"
                                                height="24" fill="none" viewBox="0 0 24 24">
                                                <circle cx="12" cy="8" r="6" stroke="currentColor" stroke-width="2" />
                                                <path stroke="currentColor" stroke-linecap="round"
                                                    stroke-linejoin="round" stroke-width="2"
                                                    d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11" />
                                            </svg>
                                        </span>
                                        <x-ui.card-title class="mb-0">Permanent Achievements</x-ui.card-title>
                                    </div>
                                    <span
                                        class="text-[11px] font-semibold text-amber-700 bg-white/70 border border-amber-100 px-2 py-1 rounded-full">New</span>
                                </div>
                                <p class="text-amber-700 text-xs">A trophy shelf that never rewinds</p>

                                <!-- Mini preview: mirrors the durable achievement shelf categories -->
                                <div class="rounded-xl border border-gray-100 bg-white p-3 space-y-3 shadow-sm"
                                    aria-label="Preview of permanent achievements">
                                    <div class="grid grid-cols-3 gap-2">
                                        <div class="rounded-lg border border-gray-100 bg-gray-50 p-3 text-center">
                                            <img src="{{ asset('images/achievements/badge-streak.png') }}" alt=""
                                                class="mx-auto h-10 w-10" loading="lazy" decoding="async">
                                            <span class="mt-2 block text-xs font-semibold text-gray-700">Streaks</span>
                                            <span class="mt-0.5 block text-[11px] text-gray-500">7, 30, 100</span>
                                        </div>
                                        <div class="rounded-lg border border-gray-100 bg-gray-50 p-3 text-center">
                                            <img src="{{ asset('images/achievements/badge-library.png') }}" alt=""
                                                class="mx-auto h-10 w-10" loading="lazy" decoding="async">
                                            <span class="mt-2 block text-xs font-semibold text-gray-700">Books</span>
                                            <span class="mt-0.5 block text-[11px] text-gray-500">Completed</span>
                                        </div>
                                        <div class="rounded-lg border border-gray-100 bg-gray-50 p-3 text-center">
                                            <img src="{{ asset('images/achievements/badge-progress.png') }}" alt=""
                                                class="mx-auto h-10 w-10" loading="lazy" decoding="async">
                                            <span class="mt-2 block text-xs font-semibold text-gray-700">Progress</span>
                                            <span class="mt-0.5 block text-[11px] text-gray-500">25-100%</span>
                                        </div>
                                    </div>
                                </div>

                                <p class="text-gray-600 leading-relaxed">
                                    Unlock lasting milestones for streak thresholds, completed books, and Bible
                                    progress. Earned achievements stay on your shelf even as live goals change.
                                </p>
                            </x-ui.card-content>
                        </x-ui.card>
                    </li>

                    <!-- Feature 6: Next Milestone Widget -->
                    <li class="order-3">
                        <x-ui.card elevated class="hover:shadow-xl transition-shadow h-full flex flex-col">
                            <x-ui.card-content class="space-y-3">
                                <div class="flex items-center gap-3">
                                    <span class="shrink-0 text-gray-800" aria-hidden="true">
                                        <svg class="w-8 h-8" xmlns="http://www.w3.org/2000/svg" width="24"
                                            height="24" fill="currentColor" viewBox="0 0 24 24">
                                            <path fill-rule="evenodd"
                                                d="M12 2a1 1 0 0 1 .894.553l2.447 4.96 5.473.795a1 1 0 0 1 .554 1.706l-3.96 3.86.935 5.451a1 1 0 0 1-1.451 1.054L12 17.807l-4.892 2.572a1 1 0 0 1-1.451-1.054l.935-5.451-3.96-3.86a1 1 0 0 1 .554-1.706l5.473-.795 2.447-4.96A1 1 0 0 1 12 2Z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                    <x-ui.card-title class="mb-0">Next Milestone</x-ui.card-title>
                                </div>
                                <p class="text-gray-400 text-xs">Know what to aim for next</p>

                                <!-- Compact preview of the dashboard milestone card -->
                                <div class="rounded-xl border border-gray-100 bg-white p-3 space-y-2 shadow-sm"
                                    aria-label="Preview of the Next Milestone widget">
                                    <div class="flex items-start gap-3">
                                        <div
                                            class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-50 text-emerald-600">
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round"
                                                aria-hidden="true">
                                                <path d="M12 7v14" />
                                                <path
                                                    d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z" />
                                            </svg>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-gray-900">Finish Amos</p>
                                            <p class="text-xs leading-5 text-gray-500">One chapter completes the book.</p>
                                        </div>
                                    </div>
                                    <div class="space-y-1.5">
                                        <div class="flex items-center justify-between text-xs text-gray-500">
                                            <span>Progress</span>
                                            <span>8/9</span>
                                        </div>
                                        <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                                            <div class="h-full w-[89%] rounded-full bg-blue-600"></div>
                                        </div>
                                    </div>
                                </div>

                                <p class="text-gray-600 leading-relaxed">
                                    See the most useful live goal for your current season: a streak threshold, a nearly
                                    finished book, or the next Bible progress milestone.
                                </p>
                            </x-ui.card-content>
                        </x-ui.card>
                    </li>
                </ul>
            </div>
        </section>

        <!-- Steps Section -->
        <section class="py-20 bg-gray-50" aria-labelledby="steps-heading">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 id="steps-heading" class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                        How Delight Works
                    </h2>
                    <p class="text-xl text-gray-600">
                        Build a resilient Bible habit in three simple steps.
                    </p>
                </div>

                <!-- Steps Grid -->
                <ol class="grid md:grid-cols-3 gap-8" role="list" aria-label="How to use the Bible habit tracker">
                    <!-- Step 1 -->
                    <li class="text-center">
                        <div class="bg-accent-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-6"
                            aria-hidden="true">
                            <span class="text-2xl font-bold text-accent-600">1</span>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-3">Read & Log</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Simply log which chapter you read today with our intuitive Bible reading tracker interface.
                            Takes just seconds and fits seamlessly into your routine.
                        </p>
                    </li>

                    <!-- Step 2 -->
                    <li class="text-center">
                        <div class="bg-blue-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-6"
                            aria-hidden="true">
                            <span class="text-2xl font-bold text-blue-600">2</span>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-3">See Progress</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Watch your daily streaks and book completion grid grow. Beautiful visuals show your
                            Scripture journey.
                        </p>
                    </li>

                    <!-- Step 3 -->
                    <li class="text-center">
                        <div class="bg-green-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-6"
                            aria-hidden="true">
                            <span class="text-2xl font-bold text-green-600">3</span>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-3">Stay Motivated</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Gentle accountability and progress celebration keep you coming back. This
                            Bible habit tracker helps build the consistency that transforms lives.
                        </p>
                    </li>
                </ol>
            </div>
        </section>

        <!-- Final CTA Section -->
        <section class="py-20 bg-gradient-to-br from-[#3366CC] to-[#2952A3]" aria-labelledby="final-cta-heading">
            <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                <h2 id="final-cta-heading" class="text-3xl md:text-4xl font-bold text-white mb-6">
                    Ready to Experience Consistent Bible Reading?
                </h2>
                <p class="text-xl text-white mb-8">
                    Join readers discovering how gentle tracking makes Scripture engagement feel sustainable. Experience
                    the transformation that comes from steady, grace-filled momentum.
                </p>
                <x-ui.button variant="accent" size="lg" href="{{ route('register') }}"
                    aria-label="Start building life-changing Bible reading habits">
                    Start Building Life-Changing Habits
                </x-ui.button>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12" role="contentinfo" aria-label="Site footer">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8">
                <!-- Brand -->
                <div class="md:col-span-2">
                    <h3 class="text-2xl font-bold mb-4">Delight</h3>
                    <p class="text-gray-300 leading-relaxed">
                        Your Bible reading tracker for building consistent Scripture habits through simple logging,
                        streak tracking, and motivating progress visualization.
                    </p>
                </div>

                <!-- Quick Links -->
                <nav class="md:col-span-1" aria-labelledby="quick-links-heading">
                    <h4 id="quick-links-heading" class="font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="{{ route('register') }}"
                                class="text-gray-300 hover:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-900 rounded-sm">Get
                                Started</a></li>
                        <li><a href="{{ route('login') }}"
                                class="text-gray-300 hover:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-900 rounded-sm">Sign
                                In</a></li>
                        <li><a href="{{ route('announcements.index') }}"
                                class="text-gray-300 hover:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-900 rounded-sm">Updates</a>
                        </li>
                    </ul>
                </nav>

                <!-- Legal -->
                <nav class="md:col-span-1" aria-labelledby="legal-links-heading">
                    <h4 id="legal-links-heading" class="font-semibold mb-4">Legal</h4>
                    <ul class="space-y-2">
                        <li><a href="{{ route('privacy-policy') }}"
                                class="text-gray-300 hover:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-900 rounded-sm">Privacy
                                Policy</a></li>
                        <li><a href="{{ route('terms-of-service') }}"
                                class="text-gray-300 hover:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-900 rounded-sm">Terms
                                of Service</a></li>
                    </ul>
                </nav>
            </div>

            <!-- Copyright -->
            <div class="border-t border-gray-800 mt-8 pt-8 text-center">
                <p class="text-gray-300">
                    © {{ date('Y') }} Delight. All rights reserved.
                </p>
            </div>
        </div>
    </footer>
</body>

</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- SEO Meta Tags -->
    <title>{{ config('app.name', 'Delight') }} - Bible Reading Tracker</title>
    <meta name="description" content="Make Bible reading consistency achievable with gentle tracking and motivation. Build lasting habits through streaks, reading logs, and progress visualization.">
    <meta name="keywords" content="bible tracking app, bible reading tracker, bible habit tracker, research-based bible habits, bible reading accountability, overcome bible reading struggles, bible engagement study, scripture reading app, daily bible reading, bible progress tracker">
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
    <meta property="og:description" content="Bible reading tracker that makes consistency achievable. Gentle motivation helps you stay engaged with Scripture for lasting transformation.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ config('app.url') }}">
    <meta property="og:image" content="{{ asset('images/screenshots/preview-social.png') }}">
    <meta property="og:site_name" content="Delight">

    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Delight - Bible Tracking App for Consistency">
    <meta name="twitter:description" content="Overcome Bible reading struggles with gentle accountability, streak tracking, and joyful progress visualization.">
    <meta name="twitter:image" content="{{ asset('images/screenshots/preview-social.png') }}">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <!-- Styles -->
    @vite(['resources/css/app.css'])

    <!-- Structured Data -->
    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "WebApplication",
            "name": "Delight",
            "description": "Overcome Bible reading struggles with gentle accountability, streak tracking, and joyful progress visualization.",
            "url": "{{ config('app.url') }}",
            "applicationCategory": "LifestyleApplication",
            "operatingSystem": "Web Browser",
            "browserRequirements": "Requires JavaScript. Requires HTML5.",
            "softwareVersion": "1.0",
            "aggregateRating": {
                "@type": "AggregateRating",
                "ratingValue": "5.0",
                "ratingCount": "1"
            },
            "offers": {
                "@type": "Offer",
                "price": "0",
                "priceCurrency": "USD",
                "availability": "https://schema.org/InStock"
            },
            "author": {
                "@type": "Organization",
                "name": "Delight"
            },
            "keywords": "bible tracking app, bible reading tracker, research-based bible habits, bible reading accountability",
            "screenshot": "{{ asset('images/screenshots/desktop_101.png') }}",
            "featureList": [
                "Daily Streak Tracking",
                "Daily Reading Log",
                "Recent Activity Timeline",
                "Book Completion Grid",
                "Reading Statistics"
            ]
        }
    </script>
</head>

<body class="font-sans antialiased bg-white">
    <!-- Skip to main content link for screen readers -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 bg-blue-600 text-white px-4 py-2 rounded-md z-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
        Skip to main content
    </a>

    <!-- Navigation -->
    <nav class="fixed top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-sm border-b border-gray-100" role="navigation" aria-label="Main navigation">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Brand Logo -->
                <div class="flex-shrink-0">
                    <a href="{{ route('landing') }}" class="flex items-center space-x-2 text-2xl font-bold text-gray-900 hover:text-blue-600 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded-md" aria-label="Delight - Go to homepage">
                        <img
                            src="{{ asset('images/logo-64.png') }}"
                            srcset="{{ asset('images/logo-64.png') }} 1x, {{ asset('images/logo-64-2x.png') }} 2x"
                            alt="Delight logo - Bible reading habit tracker"
                            class="w-8 h-8"
                            width="32"
                            height="32"
                            loading="eager" />
                        <span>Delight</span>
                    </a>
                </div>

                <!-- Navigation Actions -->
                <div class="flex items-center space-x-4" role="group" aria-label="Account actions">
                    @auth
                    <a href="{{ route('dashboard') }}" class="text-gray-700 hover:text-blue-600 font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded-md px-2 py-1" aria-label="Go to your dashboard">
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
        <section class="relative bg-gradient-to-bl from-blue-50 to-white py-20 lg:py-32" aria-labelledby="hero-heading">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-2 gap-2 items-center">
                    <!-- Hero Content -->
                    <div class="text-center lg:text-left lg:pr-24">
                        <h1 id="hero-heading" class="text-4xl md:text-5xl font-bold text-gray-900 leading-tight mb-6">
                            Track Every Reading and See Your Momentum
                        </h1>
                        <p class="text-xl text-gray-600 mb-6 leading-relaxed">
                            Delight records what you read, keeps your streaks visible, and shows how far you’ve come. Log a passage in seconds, review recent activity, and stay aware of the habit you’re building day by day.
                        </p>

                        <!-- Primary CTA -->
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                            <x-ui.button variant="accent" size="lg" href="{{ route('register') }}" class="whitespace-normal sm:whitespace-nowrap">
                                Start Building Life-Changing Habits
                            </x-ui.button>
                            <x-ui.button variant="ghost" size="lg" href="#features-heading" aria-label="Scroll to see Delight features" class="whitespace-normal sm:whitespace-nowrap">
                                Explore the Dashboard
                            </x-ui.button>
                        </div>
                    </div>

                    <!-- Hero Visual -->
                    <div class="relative" role="img" aria-label="Screenshots of Delight Bible reading tracker application">
                        <!-- Desktop Screenshot - Hidden on mobile -->
                        <div class="hidden lg:block bg-white rounded-2xl shadow-2xl p-0 transform rotate-1">
                            <div class="rounded-lg overflow-hidden">
                                <img
                                    src="{{ asset('images/screenshots/desktop_101.png') }}"
                                    alt="Delight Bible reading tracker showing daily streaks, book completion grid, and reading log interface"
                                    class="w-full h-auto max-w-full"
                                    width="800"
                                    height="600"
                                    loading="lazy" />
                            </div>
                        </div>

                        <!-- Mobile Screenshots - Shown on mobile, side by side -->
                        <div class="lg:hidden grid grid-cols-2 gap-4 mt-8">
                            <div class="bg-white rounded-2xl shadow-2xl p-0">
                                <div class="rounded-lg overflow-hidden">
                                    <img
                                        src="{{ asset('images/screenshots/mobile_101.png') }}"
                                        alt="Delight mobile Bible tracker showing progress target and daily streak widgets with a touch-friendly interface"
                                        class="w-full h-auto"
                                        width="128"
                                        height="256"
                                        loading="lazy" />
                                </div>
                            </div>
                            <div class="bg-white rounded-2xl shadow-2xl p-0">
                                <div class="rounded-lg overflow-hidden">
                                    <img
                                        src="{{ asset('images/screenshots/mobile_102.png') }}"
                                        alt="Delight mobile Bible reading tracker showing daily streak and enhanced summary stats including days read, total chapters, and Bible progress percentage"
                                        class="w-full h-auto"
                                        width="128"
                                        height="256"
                                        loading="lazy" />
                                </div>
                            </div>
                        </div>

                        <!-- Mobile Screenshot - Floating (Desktop only) -->
                        <div class="hidden lg:block absolute -bottom-6 -right-6 w-36 sm:w-40 lg:w-48 bg-white rounded-xl shadow-xl p-0 transform rotate-6">
                            <div class="rounded-lg overflow-hidden">
                                <img
                                    src="{{ asset('images/screenshots/mobile_101.png') }}"
                                    alt="Delight mobile Bible tracker showing progress target and daily streak widgets with a touch-friendly interface"
                                    class="w-full h-auto max-w-full"
                                    width="192"
                                    height="384"
                                    loading="lazy" />
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
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8" role="list" aria-label="Key features of Delight">
                    <!-- Feature 1: Daily Streak Tracking (Featured) -->
                    <div role="listitem">
                        <x-ui.card class="bg-gradient-to-br from-accent-500 to-accent-600 text-white shadow-lg hover:shadow-xl transition-shadow h-full">
                            <x-ui.card-content>
                                <div class="text-4xl mb-4" role="img" aria-label="Fire icon representing daily streaks">🔥</div>
                                <x-ui.card-title class="text-white">Daily Streak Tracking</x-ui.card-title>
                                <p class="text-orange-200 text-xs mb-2">Watch consistency grow</p>
                                <p class="text-orange-100 leading-relaxed mt-3">
                                    Build momentum with daily reading streaks. See your current and longest streaks to stay motivated and celebrate consistency.
                                </p>
                            </x-ui.card-content>
                        </x-ui.card>
                    </div>

                    <!-- Feature 2: Daily Reading Log -->
                    <div role="listitem">
                        <x-ui.card elevated class="hover:shadow-xl transition-shadow h-full">
                            <x-ui.card-content>
                                <div class="text-4xl mb-4" role="img" aria-label="Book icon">📖</div>
                                <x-ui.card-title>Daily Reading Log</x-ui.card-title>
                                <p class="text-gray-400 text-xs mb-2">Never lose your place</p>
                                <p class="text-gray-600 leading-relaxed mt-3">
                                    Log your daily reading in 30 seconds with our intuitive book and chapter selector. Simple tracking keeps you focused on reading, not recording.
                                </p>
                            </x-ui.card-content>
                        </x-ui.card>
                    </div>

                    <!-- Feature 3: Recent Activity Timeline -->
                    <div role="listitem">
                        <x-ui.card elevated class="hover:shadow-xl transition-shadow h-full">
                            <x-ui.card-content>
                                <div class="text-4xl mb-4" role="img" aria-label="Clock icon representing recent activity">🕒</div>
                                <x-ui.card-title>Recent Activity Timeline</x-ui.card-title>
                                <p class="text-gray-400 text-xs mb-2">Remember what you read</p>
                                <p class="text-gray-600 leading-relaxed mt-3">
                                    Review your latest passages and notes at a glance. Delight keeps a clear history of your readings so you can reflect and pick up right where you left off.
                                </p>
                            </x-ui.card-content>
                        </x-ui.card>
                    </div>

                    <!-- Feature 4: Book Completion Grid -->
                    <div role="listitem">
                        <x-ui.card elevated class="hover:shadow-xl transition-shadow h-full">
                            <x-ui.card-content>
                                <div class="text-4xl mb-4" role="img" aria-label="Grid icon representing book progress">📊</div>
                                <x-ui.card-title>Book Completion Grid</x-ui.card-title>
                                <p class="text-gray-400 text-xs mb-2">Journey comes alive visually</p>
                                <p class="text-gray-600 leading-relaxed mt-3">
                                    Visualize your Scripture journey with a beautiful grid of all 66 Bible books. Watch your progress unfold as you complete each book.
                                </p>
                            </x-ui.card-content>
                        </x-ui.card>
                    </div>

                    <!-- Feature 5: Reading Statistics -->
                    <div role="listitem">
                        <x-ui.card elevated class="hover:shadow-xl transition-shadow h-full">
                            <x-ui.card-content>
                                <div class="text-4xl mb-4" role="img" aria-label="Graph icon representing statistics">📈</div>
                                <x-ui.card-title>Reading Statistics</x-ui.card-title>
                                <p class="text-gray-400 text-xs mb-2">Celebrate every milestone</p>
                                <p class="text-gray-600 leading-relaxed mt-3">
                                    See your reading journey in numbers: total days read, chapters completed, overall Bible progress, and reading velocity. Track your transformation.
                                </p>
                            </x-ui.card-content>
                        </x-ui.card>
                    </div>

                    <!-- Feature 6: Research-Based Weekly Goal -->
                    <div role="listitem">
                        <x-ui.card elevated class="hover:shadow-xl transition-shadow h-full">
                            <x-ui.card-content>
                                <div class="text-4xl mb-4" role="img" aria-label="Target icon representing weekly goal">🎯</div>
                                <x-ui.card-title>Research-Based Weekly Goal</x-ui.card-title>
                                <p class="text-gray-400 text-xs mb-2">Aim for 4 reading days each week</p>
                                <p class="text-gray-600 leading-relaxed mt-3">
                                    Delight sets a single weekly target: read at least four days—the research-backed 4-day threshold for life change. Gentle accountability helps you reach it without pressure.
                                </p>
                            </x-ui.card-content>
                        </x-ui.card>
                    </div>
                </div>
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
                        <div class="bg-accent-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-6" aria-hidden="true">
                            <span class="text-2xl font-bold text-accent-600">1</span>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-3">Read & Log</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Simply log which chapter you read today with our intuitive Bible reading tracker interface. Takes 30 seconds and keeps you focused on reading, not recording.
                        </p>
                    </li>

                    <!-- Step 2 -->
                    <li class="text-center">
                        <div class="bg-blue-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-6" aria-hidden="true">
                            <span class="text-2xl font-bold text-blue-600">2</span>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-3">See Progress</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Watch your daily streaks and book completion grid grow. Beautiful visuals show your Scripture journey.
                        </p>
                    </li>

                    <!-- Step 3 -->
                    <li class="text-center">
                        <div class="bg-green-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-6" aria-hidden="true">
                            <span class="text-2xl font-bold text-green-600">3</span>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-3">Stay Motivated</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Research-based gentle accountability and progress celebration keep you coming back. This Bible habit tracker helps build the consistency that transforms lives.
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
                    Join readers discovering how gentle tracking makes Scripture engagement feel sustainable. Experience the transformation that comes from steady, grace-filled momentum.
                </p>
                <x-ui.button variant="accent" size="lg" href="{{ route('register') }}" aria-label="Start building life-changing Bible reading habits">
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
                        Your Bible reading tracker for building consistent Scripture habits through simple logging, streak tracking, and motivating progress visualization.
                    </p>
                </div>

                <!-- Quick Links -->
                <nav class="md:col-span-1" aria-labelledby="quick-links-heading">
                    <h4 id="quick-links-heading" class="font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="{{ route('register') }}" class="text-gray-300 hover:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-900 rounded-sm">Get Started</a></li>
                        <li><a href="{{ route('login') }}" class="text-gray-300 hover:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-900 rounded-sm">Sign In</a></li>
                    </ul>
                </nav>

                <!-- Legal -->
                <nav class="md:col-span-1" aria-labelledby="legal-links-heading">
                    <h4 id="legal-links-heading" class="font-semibold mb-4">Legal</h4>
                    <ul class="space-y-2">
                        <li><a href="{{ route('privacy-policy') }}" class="text-gray-300 hover:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-900 rounded-sm">Privacy Policy</a></li>
                        <li><a href="{{ route('terms-of-service') }}" class="text-gray-300 hover:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-900 rounded-sm">Terms of Service</a></li>
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

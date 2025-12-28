@extends('layouts.reader')

@section('title', "Your $year in Word - Delight")

@section('meta')
    <meta name="description" content="Your {{ $year }} Year in Review">
@endsection

@section('content')
    <!-- Fixed Background Layer -->
    <div class="fixed inset-0 z-[-1] pointer-events-none">
        <!-- Base Color -->
        <div class="absolute inset-0 bg-[#0F1115]"></div>
        <!-- Gradients -->
        <div
            class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,_var(--tw-gradient-stops))] from-blue-900/20 via-[#0F1115] to-[#0F1115]">
        </div>
        <div
            class="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_right,_var(--tw-gradient-stops))] from-blue-900/20 via-transparent to-transparent">
        </div>
    </div>

    <!-- Content -->
    <div class="relative z-10 text-white font-sans selection:bg-blue-500 selection:text-white">
        <div class="w-full">
            <!-- Header -->
            <header class="mb-12 text-center">
                <h1
                    class="text-4xl md:text-5xl font-bold tracking-tight mb-4 bg-clip-text text-transparent bg-gradient-to-r from-blue-400 via-cyan-400 to-blue-500">
                    Your {{ $year }} in Word
                </h1>
                <p class="text-lg text-gray-400">
                    Your first year with Delight — a look back at your journey through Scripture.
                </p>
            </header>

            @if (empty($stats))
                <div class="bg-gray-800/50 backdrop-blur-xl border border-gray-700/50 rounded-3xl p-12 text-center">
                    <div class="text-6xl mb-6">📖</div>
                    <h2 class="text-2xl font-bold mb-4">No Data Found for {{ $year }}</h2>
                    <p class="text-gray-400 mb-8 max-w-lg mx-auto">
                        It looks like you didn't have any reading activity recorded in {{ $year }}.
                        Start reading today to see your stats here next year!
                    </p>
                    <a href="{{ route('dashboard') }}"
                        class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-full text-white bg-blue-600 hover:bg-blue-700 transition-all shadow-lg hover:shadow-blue-500/25">
                        Go to Dashboard
                    </a>
                </div>
            @else
                <!-- Bento Grid -->
                <!-- Bento Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-6 gap-6 mb-12">

                    <!-- Reader Personality (3/6 width) -->
                    <div
                        class="col-span-1 lg:col-span-3 bg-blue-900/50 border border-blue-700/50 rounded-3xl p-8 relative overflow-hidden group">
                        <!-- Background Glow -->
                        <div
                            class="absolute top-0 right-0 -mt-8 -mr-8 w-32 h-32 bg-blue-500/20 rounded-full blur-3xl group-hover:bg-blue-500/30 transition-all duration-700">
                        </div>

                        <div class="relative z-10 flex flex-col gap-4">
                            <div class="flex justify-between items-start">
                                <p class="text-blue-300 font-medium uppercase tracking-wider text-sm">Reader Style</p>
                                @if (isset($stats['reader_personality']['stats']))
                                    <div
                                        class="inline-flex items-center px-3 py-1 rounded-full bg-blue-500/20 border border-blue-400/30 text-blue-200 text-sm font-medium backdrop-blur-sm whitespace-nowrap ml-2 -mt-1">
                                        {{ $stats['reader_personality']['stats'] }}
                                    </div>
                                @endif
                            </div>
                            <div class="text-2xl lg:text-3xl font-bold text-white leading-tight">
                                {{ $stats['reader_personality']['name'] }}
                            </div>
                            <p class="text-blue-200 text-lg leading-relaxed max-w-md">
                                {{ $stats['reader_personality']['description'] }}
                            </p>
                        </div>
                    </div>

                <!-- Top Books (3/6 width) -->
                <div class="col-span-1 lg:col-span-3 bg-gray-800/80 border border-gray-700/50 rounded-3xl p-8">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-gray-400 font-medium uppercase tracking-wider text-sm">Top Books</p>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-yellow-500" fill="currentColor"
                            viewBox="0 0 16 16" aria-hidden="true">
                            <path
                                d="M2.5.5A.5.5 0 0 1 3 0h10a.5.5 0 0 1 .5.5q0 .807-.034 1.536a3 3 0 1 1-1.133 5.89c-.79 1.865-1.878 2.777-2.833 3.011v2.173l1.425.356c.194.048.377.135.537.255L13.3 15.1a.5.5 0 0 1-.3.9H3a.5.5 0 0 1-.3-.9l1.838-1.379c.16-.12.343-.207.537-.255L6.5 13.11v-2.173c-.955-.234-2.043-1.146-2.833-3.012a3 3 0 1 1-1.132-5.89A33 33 0 0 1 2.5.5m.099 2.54a2 2 0 0 0 .72 3.935c-.333-1.05-.588-2.346-.72-3.935m10.083 3.935a2 2 0 0 0 .72-3.935c-.133 1.59-.388 2.885-.72 3.935" />
                        </svg>
                    </div>

                    @if ($stats['top_books']->isNotEmpty())
                        <div class="space-y-2">
                            @foreach ($stats['top_books'] as $index => $book)
                                @php
                                    $isFirst = $index === 0;
                                    $isLong = true; // strlen($book['name']) > 12;
                                    $rankColor = match ($index) {
                                        0 => 'bg-yellow-500/10 text-yellow-500',
                                        1 => 'bg-gray-700/50 text-gray-400',
                                        2 => 'bg-orange-900/20 text-orange-700',
                                        default => 'bg-gray-800 text-gray-600',
                                    };

                                    // Dynamic font size for top spot
                                    if ($isFirst) {
                                        $sizeClass = $isLong ? 'text-xl' : 'text-2xl';
                                        $textColor = "text-white $sizeClass font-bold";
                                    } else {
                                        $textColor = 'text-gray-400 text-lg font-medium';
                                    }

                                    $countColor = $isFirst ? 'text-white text-xl' : 'text-gray-500 text-base';
                                @endphp
                                <div class="flex items-center justify-between group">
                                    <div class="flex items-center gap-4 min-w-0">
                                        <span
                                            class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full font-bold text-sm {{ $rankColor }}">
                                            {{ $index + 1 }}
                                        </span>
                                        <h3 class="{{ $textColor }} truncate transition-colors">
                                            {{ $book['name'] }}
                                        </h3>
                                    </div>
                                    <div class="text-right flex-shrink-0 ml-4">
                                        <span class="{{ $countColor }} font-bold">{{ $book['count'] }}</span>
                                        <span class="text-gray-600 text-xs block">chaps</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <p class="text-gray-500">Go read some books!</p>
                        </div>
                    @endif
                </div>

                <!-- Total Chapters (2/6 width) -->
                <div
                    class="col-span-1 lg:col-span-2 bg-gray-800/80 border border-gray-700/50 rounded-3xl p-8 relative group">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-gray-400 font-medium uppercase tracking-wider text-sm">Total Chapters</p>
                        <span class="text-blue-500">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path fill-rule="evenodd"
                                    d="M11 4.717c-2.286-.58-4.16-.756-7.045-.71A1.99 1.99 0 0 0 2 6v11c0 1.133.934 2.022 2.044 2.007 2.759-.038 4.5.16 6.956.791V4.717Zm2 15.081c2.456-.631 4.198-.829 6.956-.791A2.013 2.013 0 0 0 22 16.999V6a1.99 1.99 0 0 0-1.955-1.993c-2.885-.046-4.76.13-7.045.71v15.081Z"
                                    clip-rule="evenodd" />
                            </svg>
                        </span>
                    </div>
                    <div class="text-5xl font-bold text-white mb-2">
                        {{ number_format($stats['total_chapters_read']) }}
                    </div>
                    <p class="text-sm text-gray-500">Across {{ number_format($stats['active_days_count']) }} active
                        days
                    </p>
                </div>

                <!-- Longest Streak (2/6 width) -->
                <div class="col-span-1 lg:col-span-2 bg-gray-800/80 border border-gray-700/50 rounded-3xl p-8">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-gray-400 font-medium uppercase tracking-wider text-sm">Longest Streak</p>
                        <!-- Fire Icon -->
                        <span class="text-orange-500">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 384 512">
                                <path
                                    d="M216 23.86c0-23.8-30.65-32.77-44.15-13.04C48 191.85 224 200 224 288c0 35.63-29.11 64.46-64.85 63.99-35.17-.45-63.15-29.77-63.15-64.94v-85.51c0-21.7-26.47-32.4-41.6-16.9C21.22 216.4 0 268.2 0 320c0 105.87 86.13 192 192 192s192-86.13 192-192c0-170.29-168-193.17-168-296.14z" />
                            </svg>
                        </span>
                    </div>
                    <div class="text-5xl font-bold text-white mb-2">
                        {{ $stats['yearly_streak']['count'] }} <span class="text-2xl text-gray-500 font-normal">days</span>
                    </div>
                    <p class="text-xs text-gray-500 truncate"
                        title="{{ $stats['yearly_streak']['start'] }} - {{ $stats['yearly_streak']['end'] }}">
                        {{ $stats['yearly_streak']['start'] }} - {{ $stats['yearly_streak']['end'] }}
                    </p>
                </div>
                <!-- Books Completed (2/6 width) -->
                <div class="col-span-1 lg:col-span-2 bg-gray-800/80 border border-gray-700/50 rounded-3xl p-8">
                    @if ($stats['books_completed_count'] > 0)
                        <div class="flex items-center justify-between mb-4">
                            <p class="text-gray-400 font-medium uppercase tracking-wider text-sm">Books Completed</p>
                            <span class="text-green-500">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </span>
                        </div>
                        <div class="text-5xl font-bold text-white mb-2">
                            {{ $stats['books_completed_count'] }}
                        </div>
                        <p class="text-sm text-gray-500">Full books finished</p>
                    @endif
                </div>

                <!-- Heatmap (Full Width: 6/6) -->
                <div class="col-span-1 lg:col-span-6 bg-gray-800/80 border border-gray-700/50 rounded-3xl p-8">
                    <p class="text-gray-400 font-medium uppercase tracking-wider text-sm mb-6">Daily Activity</p>

                    <div class="flex flex-wrap gap-1 justify-center md:justify-start">
                        @php
                            // Start heatmap from launch date for 2025
                            $startDate = \Carbon\Carbon::create($year, 8, 1);
                            $endDate = \Carbon\Carbon::create($year, 12, 31);
                            $current = $startDate->copy()->startOfWeek(\Carbon\Carbon::SUNDAY);
                            $end = $endDate->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
                        @endphp

                        @for ($date = $current->copy(); $date->lte($end); $date->addDay())
                            @if ($date->lt($startDate) || $date->gt($endDate))
                                <div class="w-3 h-3 m-[1px]"></div>
                            @else
                                @php
                                    $dateStr = $date->format('Y-m-d');
                                    $count = $stats['heatmap_data'][$dateStr] ?? 0;
                                    $colorClass = 'bg-gray-800';
                                    if ($count > 0) {
                                        $colorClass = 'bg-blue-900';
                                    }
                                    if ($count > 2) {
                                        $colorClass = 'bg-blue-700';
                                    }
                                    if ($count > 5) {
                                        $colorClass = 'bg-blue-500';
                                    }
                                    if ($count > 8) {
                                        $colorClass = 'bg-blue-300';
                                    }
                                @endphp
                                <div title="{{ $date->format('M d, Y') }}: {{ $count }} chapters"
                                    class="w-3 h-3 m-[1px] rounded-sm {{ $colorClass }} hover:opacity-80 transition-opacity">
                                </div>
                            @endif
                        @endfor
                    </div>
                </div>
        </div>

        <!-- Share / Footnote -->
        <div class="text-center">
            <p class="text-gray-500 text-sm mb-6">Generated on {{ now()->format('F j, Y') }}</p>
            <div class="flex gap-4 justify-center">
                <button onclick="openShareModal()"
                    class="px-6 py-2 bg-blue-600 hover:bg-blue-500 rounded-full text-sm font-medium transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                    </svg>
                    Share Story
                </button>
            </div>
        </div>

        <!-- Share Modal -->
        <div id="shareModal"
            class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="bg-gray-900 rounded-2xl max-w-lg w-full max-h-[95vh] overflow-auto">
                <div class="p-5 sm:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-white">Share Your Year</h3>
                        <button onclick="closeShareModal()" class="text-gray-400 hover:text-white">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <!-- Preview (scaled down) -->
                    <div class="mb-4 flex justify-center">
                        <div class="overflow-hidden rounded-xl border border-white/30 [--share-scale:0.3] sm:[--share-scale:0.26]"
                            style="width: calc(1080px * var(--share-scale)); height: calc(1920px * var(--share-scale));">
                            <div class="w-[1080px] h-[1920px] origin-top-left"
                                style="transform: scale(var(--share-scale));">
                                <div id="shareCard"
                                    class="w-[1080px] h-[1920px] bg-[#0F1115] relative overflow-hidden flex flex-col text-white">
                                    <!-- Background -->
                                    <div
                                        class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,_var(--tw-gradient-stops))] from-blue-900/30 via-[#0F1115] to-[#0F1115]">
                                    </div>
                                    <div
                                        class="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_right,_var(--tw-gradient-stops))] from-blue-900/25 via-transparent to-transparent">
                                    </div>

                                    <!-- Content - Single Column Layout -->
                                    <div class="relative z-10 flex flex-col h-full px-12 py-20">
                                        <!-- Header -->
                                        <div class="text-center mb-12">
                                            <h1
                                                class="text-8xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 via-cyan-400 to-blue-500 mb-5">
                                                Your {{ $year }} in Word
                                            </h1>
                                            <p class="text-3xl text-gray-400">A look back at your journey through
                                                Scripture</p>
                                        </div>

                                        <!-- Reader Personality - Full Width -->
                                        <div
                                            class="bg-blue-900/50 border border-blue-700/50 rounded-[2.5rem] p-12 mb-8 relative overflow-hidden">
                                            <div
                                                class="absolute top-0 right-0 -mt-10 -mr-10 w-40 h-40 bg-blue-500/20 rounded-full blur-3xl">
                                            </div>
                                            <div class="relative z-10">
                                                <div class="flex justify-between items-center mb-5">
                                                    <p
                                                        class="text-blue-300 font-semibold uppercase tracking-widest text-2xl">
                                                        Reader Style</p>
                                                    @if (isset($stats['reader_personality']['stats']))
                                                        <div
                                                            class="px-5 py-2.5 rounded-full bg-blue-500/20 border border-blue-400/30 text-blue-200 text-xl font-medium">
                                                            {{ $stats['reader_personality']['stats'] }}
                                                        </div>
                                                    @endif
                                                </div>
                                                <h2 class="text-6xl font-bold text-white mb-5">
                                                    {{ $stats['reader_personality']['name'] }}</h2>
                                                <p class="text-blue-200 text-3xl leading-relaxed">
                                                    {{ $stats['reader_personality']['description'] }}</p>
                                            </div>
                                        </div>

                                        <!-- Top Books - 2 Column Layout -->
                                        <div class="bg-gray-800/80 border border-gray-700/50 rounded-[2.5rem] p-12 mb-8">
                                            <div class="flex items-center justify-between mb-8">
                                                <p class="text-gray-400 font-semibold uppercase tracking-widest text-2xl">
                                                    Top Books</p>
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-yellow-500"
                                                    fill="currentColor" viewBox="0 0 16 16">
                                                    <path
                                                        d="M2.5.5A.5.5 0 0 1 3 0h10a.5.5 0 0 1 .5.5q0 .807-.034 1.536a3 3 0 1 1-1.133 5.89c-.79 1.865-1.878 2.777-2.833 3.011v2.173l1.425.356c.194.048.377.135.537.255L13.3 15.1a.5.5 0 0 1-.3.9H3a.5.5 0 0 1-.3-.9l1.838-1.379c.16-.12.343-.207.537-.255L6.5 13.11v-2.173c-.955-.234-2.043-1.146-2.833-3.012a3 3 0 1 1-1.132-5.89A33 33 0 0 1 2.5.5m.099 2.54a2 2 0 0 0 .72 3.935c-.333-1.05-.588-2.346-.72-3.935m10.083 3.935a2 2 0 0 0 .72-3.935c-.133 1.59-.388 2.885-.72 3.935" />
                                                </svg>
                                            </div>
                                            @if ($stats['top_books']->isNotEmpty())
                                                <div class="grid grid-cols-2 gap-8">
                                                    <!-- #1 Book - Left Column (Emphasized) -->
                                                    @php $firstBook = $stats['top_books']->first(); @endphp
                                                    <div class="flex items-center gap-5">
                                                        <span
                                                            class="w-16 h-16 flex items-center justify-center rounded-full font-bold text-3xl bg-yellow-500/20 text-yellow-500 shrink-0">1</span>
                                                        <div>
                                                            <div class="text-5xl font-bold text-white">
                                                                {{ $firstBook['name'] }}</div>
                                                            <div class="text-2xl text-gray-400 mt-1">
                                                                {{ $firstBook['count'] }} chapters</div>
                                                        </div>
                                                    </div>

                                                    <!-- #2 & #3 - Right Column -->
                                                    <div class="flex flex-col justify-center space-y-5">
                                                        @foreach ($stats['top_books']->slice(1, 2) as $book)
                                                            @php
                                                                $rank = $loop->iteration + 1; // 2 or 3
                                                                $rankColor =
                                                                    $rank === 2
                                                                        ? 'bg-gray-500/30 text-gray-300'
                                                                        : 'bg-orange-900/30 text-orange-600';
                                                            @endphp
                                                            <div class="flex items-center justify-between">
                                                                <div class="flex items-center gap-4">
                                                                    <span
                                                                        class="w-12 h-12 flex items-center justify-center rounded-full font-bold text-xl {{ $rankColor }}">{{ $rank }}</span>
                                                                    <span
                                                                        class="text-gray-300 text-3xl font-medium">{{ $book['name'] }}</span>
                                                                </div>
                                                                <div class="text-right">
                                                                    <span
                                                                        class="text-gray-400 text-2xl font-bold">{{ $book['count'] }}</span>
                                                                    <span class="text-gray-600 text-xl ml-2">chaps</span>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        <!-- Stats Grid - 2x2 -->
                                        <div class="grid grid-cols-2 gap-6 mb-8">
                                            <!-- Total Chapters -->
                                            <div class="bg-gray-800/80 border border-gray-700/50 rounded-[2rem] p-10">
                                                <div class="flex items-center justify-between mb-5">
                                                    <p
                                                        class="text-gray-400 font-semibold uppercase tracking-wider text-2xl">
                                                        Total Chapters</p>
                                                    <svg class="w-10 h-10 text-blue-400" fill="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path fill-rule="evenodd"
                                                            d="M11 4.717c-2.286-.58-4.16-.756-7.045-.71A1.99 1.99 0 0 0 2 6v11c0 1.133.934 2.022 2.044 2.007 2.759-.038 4.5.16 6.956.791V4.717Zm2 15.081c2.456-.631 4.198-.829 6.956-.791A2.013 2.013 0 0 0 22 16.999V6a1.99 1.99 0 0 0-1.955-1.993c-2.885-.046-4.76.13-7.045.71v15.081Z"
                                                            clip-rule="evenodd" />
                                                    </svg>
                                                </div>
                                                <div class="text-8xl font-bold text-white">
                                                    {{ number_format($stats['total_chapters_read']) }}</div>
                                            </div>

                                            <!-- Longest Streak -->
                                            <div class="bg-gray-800/80 border border-gray-700/50 rounded-[2rem] p-10">
                                                <div class="flex items-center justify-between mb-5">
                                                    <p
                                                        class="text-gray-400 font-semibold uppercase tracking-wider text-2xl">
                                                        Longest Streak</p>
                                                    <svg class="w-10 h-10 text-orange-500" fill="currentColor"
                                                        viewBox="0 0 384 512">
                                                        <path
                                                            d="M216 23.86c0-23.8-30.65-32.77-44.15-13.04C48 191.85 224 200 224 288c0 35.63-29.11 64.46-64.85 63.99-35.17-.45-63.15-29.77-63.15-64.94v-85.51c0-21.7-26.47-32.4-41.6-16.9C21.22 216.4 0 268.2 0 320c0 105.87 86.13 192 192 192s192-86.13 192-192c0-170.29-168-193.17-168-296.14z" />
                                                    </svg>
                                                </div>
                                                <div class="text-8xl font-bold text-white">
                                                    {{ $stats['yearly_streak']['count'] }} <span
                                                        class="text-4xl text-gray-500 font-normal">days</span></div>
                                            </div>

                                            <!-- Books Completed -->
                                            <div class="bg-gray-800/80 border border-gray-700/50 rounded-[2rem] p-10">
                                                <div class="flex items-center justify-between mb-5">
                                                    <p
                                                        class="text-gray-400 font-semibold uppercase tracking-wider text-2xl">
                                                        Books Completed</p>
                                                    <svg class="w-10 h-10 text-green-500" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                </div>
                                                <div class="text-8xl font-bold text-white">
                                                    {{ $stats['books_completed_count'] }}</div>
                                            </div>

                                            <!-- Active Days -->
                                            <div class="bg-gray-800/80 border border-gray-700/50 rounded-[2rem] p-10">
                                                <div class="flex items-center justify-between mb-5">
                                                    <p
                                                        class="text-gray-400 font-semibold uppercase tracking-wider text-2xl">
                                                        Active Days</p>
                                                    <svg class="w-10 h-10 text-blue-400" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                                        </path>
                                                    </svg>
                                                </div>
                                                <div class="text-8xl font-bold text-white">
                                                    {{ number_format($stats['active_days_count']) }}</div>
                                            </div>
                                        </div>

                                        <!-- Heatmap - Fixed size, no flex-1 -->
                                        <div class="bg-gray-800/80 border border-gray-700/50 rounded-[2rem] p-8">
                                            <p class="text-gray-400 font-semibold uppercase tracking-widest text-2xl mb-6">
                                                Daily Activity</p>
                                            <div class="flex flex-wrap gap-[6px] justify-center">
                                                @php
                                                    $startDate = \Carbon\Carbon::create($year, 8, 1);
                                                    $endDate = \Carbon\Carbon::create($year, 12, 31);
                                                    $current = $startDate->copy()->startOfWeek(\Carbon\Carbon::SUNDAY);
                                                    $end = $endDate->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
                                                @endphp
                                                @for ($date = $current->copy(); $date->lte($end); $date->addDay())
                                                    @if ($date->lt($startDate) || $date->gt($endDate))
                                                        <div class="w-5 h-5"></div>
                                                    @else
                                                        @php
                                                            $dateStr = $date->format('Y-m-d');
                                                            $count = $stats['heatmap_data'][$dateStr] ?? 0;
                                                            $colorClass = 'bg-gray-700';
                                                            if ($count > 0) {
                                                                $colorClass = 'bg-blue-900';
                                                            }
                                                            if ($count > 2) {
                                                                $colorClass = 'bg-blue-700';
                                                            }
                                                            if ($count > 5) {
                                                                $colorClass = 'bg-blue-500';
                                                            }
                                                            if ($count > 8) {
                                                                $colorClass = 'bg-blue-300';
                                                            }
                                                        @endphp
                                                        <div class="w-5 h-5 rounded {{ $colorClass }}"></div>
                                                    @endif
                                                @endfor
                                            </div>
                                        </div>

                                        <!-- Footer -->
                                        <div class="text-center mt-auto pt-8 flex items-center justify-center gap-5">
                                            <img src="{{ asset('images/logo-512.png') }}" class="w-14 h-14"
                                                alt="Delight">
                                            <p class="text-gray-400 text-3xl font-semibold tracking-wide">mydelight.app
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button onclick="downloadShareCard()"
                        class="w-full px-6 py-3 bg-blue-600 hover:bg-blue-500 rounded-xl text-white font-medium transition-colors flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        Download Image
                    </button>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/html-to-image@1.11.11/dist/html-to-image.min.js"></script>
        <script>
            function openShareModal() {
                document.getElementById('shareModal').classList.remove('hidden');
                document.getElementById('shareModal').classList.add('flex');
                document.body.style.overflow = 'hidden';
            }

            function closeShareModal() {
                document.getElementById('shareModal').classList.add('hidden');
                document.getElementById('shareModal').classList.remove('flex');
                document.body.style.overflow = '';
            }

            async function downloadShareCard() {
                const element = document.getElementById('shareCard');
                try {
                    const dataUrl = await htmlToImage.toPng(element, {
                        width: 1080,
                        height: 1920,
                        pixelRatio: 1,
                        backgroundColor: '#0F1115'
                    });

                    const link = document.createElement('a');
                    link.download = 'my-{{ $year }}-in-word.png';
                    link.href = dataUrl;
                    link.click();
                } catch (error) {
                    console.error('Error generating image:', error);
                    alert('Error generating image. Please try again.');
                }
            }

            // Close modal on escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeShareModal();
            });

            // Close modal on backdrop click
            document.getElementById('shareModal')?.addEventListener('click', (e) => {
                if (e.target.id === 'shareModal') closeShareModal();
            });
        </script>
        @endif
    </div>
    </div>
@endsection

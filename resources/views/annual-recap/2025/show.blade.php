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
            <div class="flex gap-4 justify-center">
                <button onclick="openShareModal()"
                    class="px-8 py-3 text-base bg-blue-600 hover:bg-blue-500 rounded-full font-medium transition-colors flex items-center gap-2 sm:px-6 sm:py-2 sm:text-sm">
                    <svg class="w-5 h-5 sm:w-4 sm:h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24"
                        height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-width="2"
                            d="M7.926 10.898 15 7.727m-7.074 5.39L15 16.29M8 12a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Zm12 5.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Zm0-11a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/>
                    </svg>
                    Share Recap
                </button>
            </div>
        </div>

        @include('annual-recap.2025.partials.share-modal')
        @include('annual-recap.2025.partials.share-modal-script')
        @endif
    </div>
    </div>
@endsection

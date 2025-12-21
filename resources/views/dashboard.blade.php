@extends('layouts.authenticated')

@section('page-title', 'Dashboard')
@section('page-subtitle', 'Track your Bible reading progress')

@section('content')
    @fragment('dashboard-content')
    <!-- Main Dashboard Content Area (Full Width) -->
    <div class="w-full p-4 lg:p-6 pb-20 lg:pb-6">
        <div id="main-content" class="h-full">
            <div class="space-y-6 lg:space-y-4 xl:space-y-6 lg:pb-0" id="dashboard-main-content-wrapper"
                hx-trigger="readingLogAdded from:body" hx-get="{{ route('dashboard') }}" hx-target="#main-content"
                hx-swap="outerHTML" hx-select="#main-content" hx-disinherit="hx-select">
                @php
                    $journeyPayload = $weeklyJourney ?? ($weeklyGoal['journey'] ?? []);
                    $weeklyJourneyCard = array_merge(
                        [
                            'currentProgress' => 0,
                            'days' => [],
                            'weekRangeText' => '',
                            'weeklyTarget' => 7,
                            'ctaEnabled' => true,
                            'ctaVisible' => false,
                            'status' => null,
                            'journeyAltText' => null,
                        ],
                        is_array($journeyPayload) ? $journeyPayload : [],
                    );
                @endphp

                <!-- Main Dashboard Layout (responsive grid) -->
                <div class="grid grid-cols-1 xl:grid-cols-4 gap-6 lg:gap-4 xl:gap-6">

                    <!-- Left Column - Main Content (responsive width) -->
                    <div class="xl:col-span-3 space-y-4 xl:space-y-6">

                        <!-- Cards Grid: 2x2 on tablet (shares row with calendar), 2-up until ultra-wide, 3-up on 2xl -->
                        <div
                            class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-2 2xl:grid-cols-3 gap-4 lg:gap-4 xl:gap-6">
                            <!-- Weekly Journey - Primary Focus -->
                            <div class="sm:col-span-1 lg:col-span-1 xl:col-span-1">
                                <x-ui.weekly-journey-card :currentProgress="$weeklyJourneyCard['currentProgress']"
                                    :days="$weeklyJourneyCard['days']" :weekRangeText="$weeklyJourneyCard['weekRangeText']"
                                    :weeklyTarget="$weeklyJourneyCard['weeklyTarget']"
                                    :ctaEnabled="$weeklyJourneyCard['ctaEnabled']"
                                    :ctaVisible="$weeklyJourneyCard['ctaVisible']" :status="$weeklyJourneyCard['status']"
                                    :journeyAltText="$weeklyJourneyCard['journeyAltText']" class="h-full" />
                            </div>

                            <!-- Daily Streak - Secondary Achievement -->
                            <div class="sm:col-span-1 md:col-span-1 lg:col-span-1 xl:col-span-1">
                                <x-ui.streak-counter :currentStreak="$stats['streaks']['current_streak']"
                                    :longestStreak="$stats['streaks']['longest_streak']"
                                    :streakSeries="$stats['streaks']['current_streak_series'] ?? []"
                                    :stateClasses="$streakStateClasses"
                                        :message="$streakMessage" :messageTone="$streakMessageTone" :recordStatus="data_get($stats, 'streaks.record_status', 'none')" :recordJustBroken="data_get($stats, 'streaks.record_just_broken', false)" class="h-full" />
                            </div>

                            <!-- Summary Stats - shares row with calendar on tablet, full-width at xl, third column on 2xl -->
                            <div class="col-span-1 sm:col-span-1 md:col-span-1 lg:col-span-1 xl:col-span-2 2xl:col-span-1">
                                <x-ui.summary-stats :daysRead="$stats['reading_summary']['total_reading_days']"
                                    :totalChapters="$stats['reading_summary']['total_readings']"
                                    :bibleProgress="$stats['book_progress']['overall_progress_percent']"
                                    :averageChaptersPerDay="$stats['reading_summary']['average_chapters_per_day']"
                                    class="h-full" />
                            </div>

                            <!-- Mobile/Tablet Calendar - pairs with quick stats from sm breakpoint -->
                            <div class="col-span-1 sm:col-span-1 md:col-span-1 lg:col-span-1 xl:hidden">
                                <x-bible.calendar-heatmap :calendar="$calendarData['calendar']"
                                    :monthName="$calendarData['monthName']"
                                    :thisMonthReadings="$calendarData['thisMonthReadings']"
                                    :thisMonthChapters="$calendarData['thisMonthChapters']"
                                    :successRate="$calendarData['successRate']" :showLegend="false"
                                    class="h-full text-sm" />
                            </div>
                        </div>

                        <!-- Book Progress Visualization -->
                        <x-bible.book-completion-grid testament="Old" />
                    </div>

                    <!-- Right Column - Desktop Sidebar (responsive width) -->
                    <div class="hidden xl:block xl:col-span-1 space-y-6 xl:space-y-6" id="dashboard-sidebar"
                        hx-trigger="readingLogAdded from:body" hx-get="{{ route('dashboard') }}" hx-target="this"
                        hx-swap="outerHTML" hx-select="#dashboard-sidebar">

                        <!-- Reading Calendar - Compact for sidebar -->
                        <div class="xl:max-w-none">
                            <x-bible.calendar-heatmap :calendar="$calendarData['calendar']"
                                :monthName="$calendarData['monthName']"
                                :thisMonthReadings="$calendarData['thisMonthReadings']"
                                :thisMonthChapters="$calendarData['thisMonthChapters']"
                                :successRate="$calendarData['successRate']" :showLegend="false" class="text-sm" />
                        </div>


                        <!-- Recent Readings -->
                        @if (!empty($stats['recent_activity']))
                            <x-ui.card
                                class="bg-white dark:bg-gray-800 border border-[#D1D7E0] dark:border-gray-700 transition-colors shadow-lg">
                                <div class="p-4 lg:p-3 xl:p-4">
                                    <h3 class="font-semibold text-[#4A5568] dark:text-gray-200 mb-3">Recent Readings</h3>
                                    <div class="space-y-2">
                                        @foreach (array_slice($stats['recent_activity'], 0, 5) as $reading)
                                            <div class="text-sm">
                                                <div class="font-medium text-[#4A5568] dark:text-gray-200">
                                                    {{ $reading['passage_text'] }}
                                                </div>
                                                <div class="text-gray-500 dark:text-gray-400 text-xs">
                                                    {{ $reading['time_ago'] }}
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </x-ui.card>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>
    @endfragment
    </div>
@endsection
{{-- Dashboard Content Partial --}}
{{-- This partial follows the ui-prototype layout with 4-column grid structure --}}
{{-- $stats variable is passed from the controller/route --}}

<div class="space-y-6 lg:space-y-4 xl:space-y-6 pb-16 lg:pb-0"
    id="dashboard-main-content-wrapper"
    hx-trigger="readingLogAdded from:body"
    hx-get="{{ route('dashboard') }}"
    hx-target="#main-content"
    hx-swap="outerHTML"
    hx-select="#main-content">

    <!-- Main Dashboard Layout (responsive grid) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 xl:grid-cols-4 gap-6 lg:gap-4 xl:gap-6">

        <!-- Left Column - Main Content (responsive width) -->
        <div class="lg:col-span-2 xl:col-span-3 space-y-4 xl:space-y-6">

            <!-- Top Row - Goal-Oriented Widgets -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-2 xl:grid-cols-3 gap-4 lg:gap-6 mb-6">
                <!-- Weekly Goal - Primary Focus -->
                <div class="sm:col-span-1 md:col-span-2 lg:col-span-1 xl:col-span-1">
                    <x-ui.weekly-goal-card
                        :currentProgress="$weeklyGoal['current_progress']"
                        :weeklyTarget="$weeklyGoal['weekly_target']"
                        :motivationalMessage="$weeklyGoal['message'] ?? ''"
                        :showResearchInfo="true"
                        class="h-full" />
                </div>

                <!-- Daily Streak - Secondary Achievement -->
                <div class="sm:col-span-1 md:col-span-2 lg:col-span-1 xl:col-span-1">
                    <x-ui.streak-counter
                        :currentStreak="$stats['streaks']['current_streak']"
                        :longestStreak="$stats['streaks']['longest_streak']"
                        :stateClasses="$streakStateClasses"
                        :message="$streakMessage"
                        class="h-full" />
                </div>

                <!-- Summary Stats - Supporting Metrics (2x2 grid) - Only on portrait/mobile/desktop -->
                <div class="sm:col-span-1 md:col-span-2 lg:hidden xl:block xl:col-span-1">
                    <x-ui.summary-stats
                        :daysRead="$stats['reading_summary']['total_reading_days']"
                        :totalChapters="$stats['reading_summary']['total_readings']"
                        :bibleProgress="$stats['book_progress']['overall_progress_percent']"
                        :averageChaptersPerDay="$stats['reading_summary']['average_chapters_per_day']"
                        class="h-full" />
                </div>

                <!-- Mobile/Tablet Calendar - Shows only on mobile and portrait tablets -->
                <div class="sm:col-span-1 md:col-span-2 lg:hidden">
                    <x-bible.calendar-heatmap
                        :user="auth()->user()"
                        :months="1"
                        :showLegend="false"
                        class="text-sm" />
                </div>
            </div>

            <!-- Summary Stats Row - Full width horizontal layout on iPad Pro landscape only (1x4) -->
            <div class="hidden lg:block xl:hidden lg:mb-4 lg:-mt-2">
                <x-ui.summary-stats
                    :daysRead="$stats['reading_summary']['total_reading_days']"
                    :totalChapters="$stats['reading_summary']['total_readings']"
                    :bibleProgress="$stats['book_progress']['overall_progress_percent']"
                    :averageChaptersPerDay="$stats['reading_summary']['average_chapters_per_day']"
                    class="h-full" />
            </div>

            <!-- Book Progress Visualization -->
            <x-bible.book-completion-grid
                testament="Old" />
        </div>

        <!-- Right Column - Desktop Sidebar (responsive width) -->
        <div class="hidden lg:block lg:col-span-1 xl:col-span-1 space-y-6 lg:space-y-4 xl:space-y-6"
            id="dashboard-sidebar"
            hx-trigger="readingLogAdded from:body"
            hx-get="{{ route('dashboard') }}"
            hx-target="this"
            hx-swap="outerHTML"
            hx-select="#dashboard-sidebar">

            <!-- Reading Calendar - Compact for sidebar -->
            <div class="xl:max-w-none">
                <x-bible.calendar-heatmap
                    :user="auth()->user()"
                    :months="1"
                    :showLegend="false"
                    class="text-sm" />
            </div>


            <!-- Recent Readings -->
            @if(!empty($stats['recent_activity']))
            <x-ui.card class="bg-white dark:bg-gray-800 border border-[#D1D7E0] dark:border-gray-700 transition-colors shadow-lg">
                <div class="p-4 lg:p-3 xl:p-4">
                    <h3 class="font-semibold text-[#4A5568] dark:text-gray-200 mb-3">Recent Readings</h3>
                    <div class="space-y-2">
                        @foreach(array_slice($stats['recent_activity'], 0, 5) as $reading)
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
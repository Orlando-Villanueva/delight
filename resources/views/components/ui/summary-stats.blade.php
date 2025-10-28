@props([
'totalChapters' => 0,
'bibleProgress' => 0,
'daysRead' => 0,
'averageChaptersPerDay' => 0.0
])

<div {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 border border-[#D1D7E0] dark:border-gray-700 h-full transition-colors rounded-lg shadow-lg']) }}>
    <div class="p-6 lg:px-3 lg:py-4 xl:p-6 h-full flex items-center">
        <!-- 2x2 by default, horizontal row on xl, back to 2x2 on 2xl+ -->
        <div class="grid grid-cols-2 xl:grid-cols-4 2xl:grid-cols-2 gap-x-6 gap-y-6 sm:gap-y-16 lg:gap-x-4 lg:gap-y-16 xl:gap-y-0 2xl:gap-y-12 w-full">
            <!-- Days Read -->
            <div class="flex flex-col items-center text-center">
                <div class="p-3 rounded-lg bg-green-50 dark:bg-green-900/20 mb-3">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2-2v16a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div class="text-xl font-semibold text-gray-600 dark:text-gray-300 mb-1 leading-[1.5]">
                    {{ $daysRead }}
                </div>
                <div class="text-sm font-normal text-gray-500 dark:text-gray-500 leading-[1.5]">Days Read</div>
            </div>

            <!-- Total Chapters -->
            <div class="flex flex-col items-center text-center">
                <div class="p-3 rounded-lg bg-purple-50 dark:bg-purple-900/20 mb-3">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                </div>
                <div class="text-xl font-semibold text-gray-600 dark:text-gray-300 mb-1 leading-[1.5]">
                    {{ $totalChapters }}
                </div>
                <div class="text-sm font-normal text-gray-500 dark:text-gray-500 leading-[1.5]">Total Chapters</div>
            </div>

            <!-- Bible Progress -->
            <div class="flex flex-col items-center text-center">
                <div class="p-3 rounded-lg bg-orange-50 dark:bg-orange-900/20 mb-3">
                    <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <div class="text-xl font-semibold text-gray-600 dark:text-gray-300 mb-1 leading-[1.5]">
                    {{ number_format($bibleProgress, 1) }}%
                </div>
                <div class="text-sm font-normal text-gray-500 dark:text-gray-500 leading-[1.5]">Bible Progress</div>
            </div>

            <!-- Average Chapters per Day -->
            <div class="flex flex-col items-center text-center">
                <div class="p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 mb-3">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div class="text-xl font-semibold text-gray-600 dark:text-gray-300 mb-1 leading-[1.5]">
                    {{ $averageChaptersPerDay > 0 ? number_format($averageChaptersPerDay, 2) : '--' }}
                </div>
                <div class="text-sm font-normal text-gray-500 dark:text-gray-500 leading-[1.5]">Avg/Day</div>
            </div>
        </div>
    </div>
</div>

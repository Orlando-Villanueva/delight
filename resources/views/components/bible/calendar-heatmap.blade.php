@props([
    'calendar' => [],
    'monthName' => '',
    'thisMonthReadings' => 0,
    'thisMonthChapters' => 0,
    'successRate' => 0,
    'showLegend' => false
])

<x-ui.card {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 border border-[#D1D7E0] dark:border-gray-700 h-fit transition-colors shadow-lg']) }}>
    <div class="p-4 lg:p-3 xl:p-4">
        <!-- Header -->
        <div class="pb-3 border-b border-[#D1D7E0] dark:border-gray-600 mb-4 lg:mb-3">
            <h4 class="text-lg lg:text-base xl:text-lg font-semibold text-gray-900 dark:text-gray-100 leading-[1.5]">Reading Calendar</h4>
            <p class="text-sm lg:text-xs xl:text-sm text-gray-600 dark:text-gray-300 leading-[1.5]">{{ $monthName }}</p>
        </div>
        
        <!-- Calendar Grid -->
        <div class="space-y-4 lg:space-y-3">
            <div>
                <!-- Day headers -->
                <div class="grid grid-cols-7 gap-1 sm:gap-2 lg:gap-1 mb-2">
                    @foreach(['S', 'M', 'T', 'W', 'T', 'F', 'S'] as $dayLabel)
                        <div class="text-center text-sm lg:text-xs xl:text-sm font-medium text-gray-500 dark:text-gray-400 py-1 leading-[1.5]">{{ $dayLabel }}</div>
                    @endforeach
                </div>
                
                <!-- Calendar days -->
                <div class="grid grid-cols-7 gap-1 sm:gap-1.5 lg:gap-1">
                    @foreach($calendar as $day)
                        @if($day === null)
                            <div class="aspect-square"></div>
                        @else
                            @php
                                // Intensity based on reading count (chapters read)
                                $count = $day['readingCount'];
                                if ($count === 0) {
                                    $intensityClass = 'bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 border border-gray-200 dark:border-gray-600';
                                } elseif ($count === 1) {
                                    $intensityClass = 'bg-success-50 dark:bg-success-900 text-gray-800 dark:text-gray-200 hover:bg-success-100 dark:hover:bg-success-800';
                                } elseif ($count === 2) {
                                    $intensityClass = 'bg-success-100 dark:bg-success-800 text-gray-800 dark:text-white hover:bg-success-200 dark:hover:bg-success-700';
                                } elseif ($count === 3) {
                                    $intensityClass = 'bg-success-200 dark:bg-success-700 text-gray-900 dark:text-white hover:bg-success-300 dark:hover:bg-success-600';
                                } elseif ($count === 4) {
                                    $intensityClass = 'bg-success-300 dark:bg-success-600 text-gray-900 dark:text-white hover:bg-success-400 dark:hover:bg-success-500';
                                } elseif ($count === 5) {
                                    $intensityClass = 'bg-success-400 dark:bg-success-500 text-white dark:text-gray-900 hover:bg-success-500 dark:hover:bg-success-400';
                                } elseif ($count === 6) {
                                    $intensityClass = 'bg-success-500 dark:bg-success-400 text-white dark:text-gray-900 hover:bg-success-600 dark:hover:bg-success-300';
                                } else { // 7+
                                    $intensityClass = 'bg-success-600 dark:bg-success-300 text-white dark:text-gray-900 hover:bg-success-700 dark:hover:bg-success-200';
                                }
                                
                                // Today indicator
                                if ($day['isToday']) {
                                    $intensityClass .= ' ring-2 ring-primary-500 dark:ring-primary-400 ring-offset-1 dark:ring-offset-gray-800';
                                }
                            @endphp
                            
                            <div class="aspect-square flex items-center justify-center text-sm sm:text-sm lg:text-xs xl:text-sm rounded-full transition-all duration-200 cursor-pointer leading-[1.5] {{ $intensityClass }}"
                                 title="{{ $day['date']->format('F j, Y') }}{{ $day['hasReading'] ? ' - ' . $day['readingCount'] . ' chapter' . ($day['readingCount'] !== 1 ? 's' : '') . ' read' : ' - No reading' }}">
                                {{ $day['day'] }}
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
            
            <!-- Monthly Stats -->
            <div class="pt-3 lg:pt-2 border-t border-[#D1D7E0] dark:border-gray-600">
                <div class="grid grid-cols-3 gap-2 lg:gap-1 xl:gap-2 text-center">
                    <div>
                        <div class="text-lg lg:text-base xl:text-lg font-bold text-[#66CC99] dark:text-[#66CC99] leading-[1.5]">{{ $thisMonthReadings }}</div>
                        <div class="text-sm lg:text-xs xl:text-sm text-gray-600 dark:text-gray-400 leading-[1.5]">Days Read</div>
                    </div>
                    <div>
                        <div class="text-lg lg:text-base xl:text-lg font-bold text-purple-600 dark:text-purple-400 leading-[1.5]">{{ $thisMonthChapters }}</div>
                        <div class="text-sm lg:text-xs xl:text-sm text-gray-600 dark:text-gray-400 leading-[1.5]">Chapters</div>
                    </div>
                    <div>
                        <div class="text-lg lg:text-base xl:text-lg font-bold text-[#3366CC] dark:text-blue-400 leading-[1.5]">{{ $successRate }}%</div>
                        <div class="text-sm lg:text-xs xl:text-sm text-gray-600 dark:text-gray-400 leading-[1.5]">Success Rate</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-ui.card>

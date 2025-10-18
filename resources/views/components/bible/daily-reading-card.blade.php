@props([
'logsForDay', // Collection of logs for this day
'showNotes' => true,
'compact' => false,
'contributedToStreak' => false
])

@php
$firstLog = $logsForDay->first();
$date = $firstLog->date_read;
$cardClasses = 'bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-md';
if ($compact) {
$cardClasses .= ' p-3';
} else {
$cardClasses .= ' p-4';
}
@endphp

<div {{ $attributes->merge(['class' => $cardClasses]) }}>
    {{-- Subtle Date Header --}}
    <div class="flex items-center justify-between mb-3">
        <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
            @php
            // Use logged_time_ago from the most recent log for header time
            // Since logs are sorted by created_at desc, first() gives us the most recent logging activity
            // The controller should always provide logged_time_ago, so this fallback should rarely be needed
            $mostRecentLog = $logsForDay->first();
            $timeAgo = $mostRecentLog->logged_time_ago ?? 'recently';
            @endphp
            {{ $date->format('M j, Y') }} • {{ $timeAgo }}
        </div>
        <div class="text-xs text-gray-400 dark:text-gray-500">
            {{ $logsForDay->count() }} {{ Str::plural('reading', $logsForDay->count()) }}
        </div>
    </div>

    {{-- Reading logs for this day --}}
    <div class="space-y-3">
        @foreach ($logsForDay as $index => $log)
        @php
            // Determine if this is a multi-chapter entry
            $allLogs = $log->all_logs ?? collect([$log]);
            $isMultiChapter = $allLogs->count() > 1;
            $modalId = $isMultiChapter ? "delete-chapters-{$log->id}" : "delete-confirmation-{$log->id}";
        @endphp

        {{-- Subtle separator between readings (not before first) --}}
        @if ($index > 0)
        <div class="border-t border-gray-50 dark:border-gray-700 pt-3"></div>
        @endif

        <div class="reading-entry">
            {{-- Bible Reading Content - Make it prominent like single cards --}}
            <div class="mb-3">
                <div class="flex items-start justify-between mb-1 gap-2">
                    <div class="flex items-center space-x-2 flex-1 min-w-0">
                        <svg class="w-5 h-5 text-primary-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 leading-tight">
                            {{ $log->passage_text }}
                        </h3>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        {{-- Individual timestamp for each reading --}}
                        <span class="text-xs text-gray-400 dark:text-gray-500 font-medium">
                            {{ $log->created_at->format('g:i A') }}
                        </span>
                        {{-- Delete Button --}}
                        <button type="button"
                            data-modal-target="{{ $modalId }}"
                            data-modal-toggle="{{ $modalId }}"
                            class="text-gray-400 hover:text-red-600 dark:text-gray-500 dark:hover:text-red-400 transition-colors cursor-pointer z-10 relative"
                            title="Delete reading">
                            <svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Notes Section --}}
            @if($showNotes && $log->notes_text)
            <div class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                <div class="whitespace-pre-wrap leading-relaxed">{{ $log->notes_text }}</div>
            </div>
            @endif
        </div>
        @endforeach
    </div>


</div>
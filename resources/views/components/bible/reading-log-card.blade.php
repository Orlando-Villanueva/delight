@props([
'log',
'showDate' => true,
'showNotes' => true,
'compact' => false,
'contributedToStreak' => false
])

@php
$cardClasses = 'bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-md';
if ($compact) {
$cardClasses .= ' p-3';
} else {
$cardClasses .= ' p-4';
}

// Determine if this is a multi-chapter entry
$allLogs = $log->all_logs ?? collect([$log]);
$isMultiChapter = $allLogs->count() > 1;
$modalId = $isMultiChapter ? "delete-chapters-{$log->id}" : "delete-confirmation-{$log->id}";
@endphp

<div {{ $attributes->merge(['class' => $cardClasses]) }}>
    <div class="flex items-start justify-between">
        <div class="flex-1 min-w-0">
            {{-- Primary: Bible Reading Content --}}
            <div class="mb-3">
                <div class="flex items-center space-x-2 mb-1">
                    <svg class="w-5 h-5 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 leading-tight">
                        {{ $log->passage_text }}
                    </h3>
                </div>

                {{-- Secondary: Date Information --}}
                @if($showDate)
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2 text-xs text-gray-500 dark:text-gray-400">
                        <span>{{ $log->date_read->format('M j, Y') }}</span>
                        @if(!$compact)
                        <span>•</span>
                        <span>{{ $log->time_ago ?? $log->date_read->diffForHumans() }}</span>
                        @endif
                    </div>
                    {{-- Time when reading was logged --}}
                    <span class="text-xs text-gray-400 dark:text-gray-500 font-medium">
                        {{ $log->created_at->format('g:i A') }}
                    </span>
                </div>
                @endif
            </div>

            {{-- Notes Section --}}
            @if($showNotes && $log->notes_text)
            <div class="border-t border-gray-100 dark:border-gray-700 pt-3 mt-3">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <div class="flex items-center space-x-2 mb-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                        </svg>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Notes</span>
                    </div>
                    <div class="whitespace-pre-wrap leading-relaxed">{{ $log->notes_text }}</div>
                </div>
            </div>
            @endif
        </div>

        {{-- Right Side Indicators --}}
        <div class="ml-4 flex flex-col items-end space-y-2">
            {{-- Delete Button --}}
            <button type="button"
                data-modal-target="{{ $modalId }}"
                data-modal-toggle="{{ $modalId }}"
                class="text-gray-400 hover:text-red-600 dark:text-gray-500 dark:hover:text-red-400 transition-colors cursor-pointer z-10 relative"
                title="Delete reading">
                <svg class="w-5 h-5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </button>

            {{-- Streak Contribution Indicator --}}
            @if($contributedToStreak)
            <div class="flex items-center space-x-1 text-xs text-success-600 bg-success-50 px-2 py-1 rounded-full">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd"></path>
                </svg>
                <span>Streak</span>
            </div>
            @endif

            {{-- Has Notes Indicator --}}
            @if($log->notes_text && !$showNotes)
            <div class="flex items-center space-x-1 text-xs text-gray-500 dark:text-gray-400">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                </svg>
                <span>Notes</span>
            </div>
            @endif
        </div>
    </div>
</div>
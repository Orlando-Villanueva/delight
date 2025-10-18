{{-- Reading Log List Partial --}}
{{-- This partial is loaded via HTMX for seamless filtering --}}

@if ($logs->count() > 0)
    {{-- Reading Log Entries Container - Simplified architecture for consistent spacing --}}
    <div id="log-list" class="relative"
        hx-trigger="readingLogAdded from:body"
        hx-get="{{ route('logs.index') }}?refresh=1"
        hx-target="this"
        hx-swap="outerHTML">
        {{-- Content Container - All cards render here with consistent spacing --}}
        <div id="log-list-content" class="space-y-4">
            @foreach ($logs as $logsForDay)
                @if ($logsForDay->count() === 1)
                    {{-- Single reading: use individual card --}}
                    <x-bible.reading-log-card :log="$logsForDay->first()" />
                @else
                    {{-- Multiple readings: use daily grouped card --}}
                    <x-bible.daily-reading-card :logsForDay="$logsForDay" />
                @endif
            @endforeach
        </div>
        
        {{-- Intersection Observer Sentinel for Infinite Scroll --}}
        @if ($logs->hasMorePages())
            @include('partials.infinite-scroll-sentinel', compact('logs'))
        @endif
    </div>

    {{-- Render all delete modals at the end to avoid z-index issues --}}
    @foreach ($logs as $logsForDay)
        @foreach ($logsForDay as $log)
            @php
                $allLogs = $log->all_logs ?? collect([$log]);
                $isMultiChapter = $allLogs->count() > 1;
            @endphp
            @if($isMultiChapter)
                <x-modals.delete-chapter-selection :log="$log" />
            @else
                <x-modals.delete-reading-confirmation :log="$log" />
            @endif
        @endforeach
    @endforeach
@else
    {{-- Empty State --}}
    <div class="text-center py-12 pb-20 lg:pb-12">
        <div class="w-16 h-16 mx-auto mb-4 text-gray-400">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253">
                </path>
            </svg>
        </div>

        <h3 class="text-lg font-medium text-gray-900 mb-2">No reading logs found</h3>

        <p class="text-gray-600 mb-6">You haven't logged any Bible readings yet. Start building your reading habit!</p>
        <x-ui.button 
            variant="primary"
            size="default"
            hx-get="{{ route('logs.create') }}" 
            hx-target="#reading-log-modal-content"
            hx-swap="innerHTML" 
            hx-indicator="#modal-loading" 
            @click="modalOpen = true"
        >
            📖 Log Your First Reading
        </x-ui.button>
    </div>
@endif

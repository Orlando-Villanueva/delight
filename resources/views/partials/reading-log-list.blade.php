{{-- Reading Log List Partial --}}
{{-- This partial is loaded via HTMX for seamless filtering --}}

@php
    // Check if today has any readings logged
    $today = today()->format('Y-m-d');
    $hasReadingToday = $logs->count() > 0 && $logs->keys()->contains($today);
@endphp

@if ($logs->count() > 0 || !$hasReadingToday)
    {{-- Reading Log Timeline with Flowbite --}}
    <ol id="log-list"
        class="relative border-s border-gray-200 dark:border-gray-700 ps-0"
        hx-trigger="readingLogAdded from:body"
        hx-get="{{ route('logs.index') }}?refresh=1"
        hx-target="this"
        hx-swap="outerHTML">

        {{-- Empty State for Today if No Readings --}}
        @if (!$hasReadingToday)
            <li class="mb-10 ms-6">
                {{-- Timeline Dot Indicator (gray for empty state) --}}
                <div class="absolute w-3 h-3 bg-gray-300 dark:bg-gray-600 rounded-full mt-1.5 -start-1.5 border-2 border-white dark:border-gray-900"></div>

                {{-- Date Header --}}
                <div class="flex items-center gap-2 mb-4">
                    <time class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ today()->format('M j, Y') }}
                    </time>
                    <span class="bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400 text-xs font-medium px-2.5 py-0.5 rounded-full">
                        Today
                    </span>
                </div>

                {{-- Empty State Card --}}
                <div class="p-6 bg-gray-50 border border-gray-200 border-dashed rounded-lg dark:bg-gray-800/50 dark:border-gray-700 text-center">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        No readings logged today
                    </p>
                    <x-ui.button
                        variant="primary"
                        size="sm"
                        hx-get="{{ route('logs.create') }}"
                        hx-target="#reading-log-modal-content"
                        hx-swap="innerHTML"
                        hx-indicator="#modal-loading"
                        @click="modalOpen = true"
                    >
                        Log Your First Reading Today
                    </x-ui.button>
                </div>
            </li>
        @endif

        @foreach ($logs as $date => $logsForDay)
            <li class="mb-10 ms-6">
                {{-- Timeline Dot Indicator --}}
                <div class="absolute w-3 h-3 bg-primary-500 rounded-full mt-1.5 -start-1.5 border-2 border-white dark:border-gray-900"></div>

                {{-- Date Header with Reading Count Badge --}}
                <div class="flex items-center gap-2 mb-4">
                    <time class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ \Carbon\Carbon::parse($date)->format('M j, Y') }}
                    </time>
                    @if ($logsForDay->count() > 1)
                        <span class="bg-primary-100 text-primary-800 dark:bg-primary-800 dark:text-primary-200 text-xs font-medium px-2.5 py-0.5 rounded-full">
                            {{ $logsForDay->count() }} reading{{ $logsForDay->count() > 1 ? 's' : '' }}
                        </span>
                    @endif
                </div>

                {{-- Individual Reading Cards for This Day --}}
                <div class="space-y-3">
                    @foreach ($logsForDay as $log)
                        <x-bible.reading-log-card :log="$log" />
                    @endforeach
                </div>
            </li>
        @endforeach

        {{-- Intersection Observer Sentinel for Infinite Scroll --}}
        @if ($logs->hasMorePages())
            @include('partials.infinite-scroll-sentinel', compact('logs'))
        @endif
    </ol>

    {{-- Render all delete modals at document level to avoid z-index issues --}}
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

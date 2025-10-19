{{-- Reading Log List Partial --}}
{{-- This partial is loaded via HTMX for seamless filtering --}}

@php
    // Extract useful state about the logs and whether the user has any history yet
    $today = today()->format('Y-m-d');
    $logItems = $logs instanceof \Illuminate\Pagination\AbstractPaginator ? collect($logs->items()) : collect($logs);

    $totalLoggedDays = method_exists($logs, 'total') ? $logs->total() : $logItems->count();

    $hasAnyLogs = $totalLoggedDays > 0;
    $hasReadingToday = $hasAnyLogs && $logItems->keys()->contains($today);
@endphp

@if ($hasAnyLogs)
    {{-- Reading Log Timeline with Flowbite --}}
    <ol id="log-list" class="relative list-none border-s border-gray-200 dark:border-gray-700 ps-0"
        hx-trigger="readingLogAdded from:body, readingLogDeleted from:body" hx-get="{{ route('logs.index') }}?refresh=1" hx-target="this"
        hx-swap="outerHTML">
        @include('partials.reading-log-items', [
            'logs' => $logs,
            'includeEmptyToday' => $hasAnyLogs && !$hasReadingToday,
        ])
    </ol>

    {{-- Render all delete modals at document level to avoid z-index issues --}}
    @include('partials.reading-log-modals', [
        'logs' => $logs,
        'modalsOutOfBand' => request()->header('HX-Request') !== null,
        'swapMethod' => request()->get('page', 1) > 1 ? 'beforeend' : 'outerHTML',
    ])
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
        <button type="button" hx-get="{{ route('logs.create') }}" hx-target="#page-container" hx-swap="innerHTML"
            hx-push-url="true"
            class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-accent-500 px-5 py-2.5 text-sm font-medium text-white transition-colors duration-150 hover:bg-accent-600 focus:outline-none focus:ring-4 focus:ring-accent-300 dark:bg-accent-600 dark:hover:bg-accent-700 dark:focus:ring-accent-800">
            📖 Log Your First Reading
        </button>
    </div>
@endif

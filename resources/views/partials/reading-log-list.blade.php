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
        hx-trigger="readingLogAdded from:body" hx-get="{{ route('logs.index') }}?refresh=1" hx-target="this"
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
    @include('partials.reading-log-empty-state')
@endif

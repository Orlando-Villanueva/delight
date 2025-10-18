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
        class="relative list-none border-s border-gray-200 dark:border-gray-700 ps-0"
        hx-trigger="readingLogAdded from:body"
        hx-get="{{ route('logs.index') }}?refresh=1"
        hx-target="this"
        hx-swap="outerHTML">
        @include('partials.reading-log-items', [
            'logs' => $logs,
            'includeEmptyToday' => ! $hasReadingToday,
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

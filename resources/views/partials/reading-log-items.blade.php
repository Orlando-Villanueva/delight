@props(['logs', 'includeEmptyToday' => false])

@php
    $today = today()->format('Y-m-d');
@endphp

@if ($includeEmptyToday && !$logs->keys()->contains($today))
    <li class="mb-10 ms-6">
        {{-- Timeline Dot Indicator (gray for empty state) --}}
        <div
            class="absolute w-3 h-3 bg-gray-300 dark:bg-gray-600 rounded-full mt-1.5 -start-1.5 border-2 border-white dark:border-gray-900">
        </div>

        {{-- Date Header --}}
        <div class="flex items-center gap-2 mb-4">
            <time class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ today()->format('M j, Y') }}
            </time>
            <span
                class="bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400 text-xs font-medium px-2.5 py-0.5 rounded-full">
                0 readings
            </span>
        </div>

        {{-- Empty State Card --}}
        <div
            class="p-6 bg-gray-50 border border-gray-200 border-dashed rounded-lg dark:bg-gray-800/50 dark:border-gray-700 text-center">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                No readings logged yet
            </p>
            <button type="button" hx-get="{{ route('logs.create') }}" hx-target="#page-container" hx-swap="innerHTML"
                hx-push-url="true"
                class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-accent-500 px-4 py-2 text-sm font-medium text-white transition-colors duration-150 hover:bg-accent-600 focus:outline-none focus:ring-4 focus:ring-accent-300 dark:bg-accent-600 dark:hover:bg-accent-700 dark:focus:ring-accent-800">
                Log a Reading
            </button>
        </div>
    </li>
@endif

@foreach ($logs as $date => $logsForDay)
    @include('partials.reading-log-day', compact('date', 'logsForDay'))
@endforeach

@includeWhen($logs->hasMorePages(), 'partials.infinite-scroll-sentinel')

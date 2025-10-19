@props([
    'date',
    'logsForDay',
    'swapMethod' => null,
])

@php
    $dayChapterCount = $logsForDay->sum(fn ($log) => $log->chapters_count ?? ($log->all_logs?->count() ?? 1));
@endphp

<li id="reading-day-{{ $date }}" class="mb-10 ms-6" @if($swapMethod) hx-swap-oob="{{ $swapMethod }}" @endif>
    {{-- Timeline Dot Indicator --}}
    <div
        class="absolute w-3 h-3 bg-primary-500 rounded-full mt-1.5 -start-1.5 border-2 border-white dark:border-gray-900">
    </div>

    {{-- Date Header with Reading Count Badge --}}
    <div class="flex items-center gap-2 mb-4">
        <time class="text-sm font-semibold text-gray-900 dark:text-white">
            {{ \Carbon\Carbon::parse($date)->format('M j, Y') }}
        </time>
        @if ($dayChapterCount > 0)
            <span
                class="bg-primary-100 text-primary-800 dark:bg-primary-800 dark:text-primary-200 text-xs font-medium px-2.5 py-0.5 rounded-full">
                {{ $dayChapterCount }} chapter{{ $dayChapterCount > 1 ? 's' : '' }}
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

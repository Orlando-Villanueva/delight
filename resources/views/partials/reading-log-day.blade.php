@props(['date', 'logsForDay', 'swapMethod' => null])

@php
    $dayChapterCount = $logsForDay->sum(fn($log) => $log->chapters_count ?? ($log->all_logs?->count() ?? 1));
    $showDayChapterCount = $logsForDay->count() > 1 && $dayChapterCount > 1;
@endphp

<li id="reading-day-{{ $date }}" class="ms-6"
    @if ($swapMethod) hx-swap-oob="{{ $swapMethod }}" @endif>
    {{-- Timeline Dot Indicator --}}
    <div
        class="absolute w-3 h-3 bg-primary-500 rounded-full mt-1.5 -start-1.5 border-2 border-white dark:border-gray-900">
    </div>

    {{-- Date Header with day total only when it adds information --}}
    <div class="flex items-center gap-2 mb-4">
        <time class="text-sm font-semibold text-gray-900 dark:text-white">
            {{ \Carbon\Carbon::parse($date)->format('M j, Y') }}
        </time>
        @if ($showDayChapterCount)
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                {{ $dayChapterCount }} chapters
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

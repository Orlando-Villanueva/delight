@props([
    'achievementId',
    'achievementName',
    'completionDates',
])

@php
    $completionCount = count($completionDates);
    $dialogId = "achievement-history-{$achievementId}";
    $titleId = "{$dialogId}-title";
@endphp

<button type="button"
    data-achievement-history-open="{{ $dialogId }}"
    aria-controls="{{ $dialogId }}"
    aria-haspopup="dialog"
    aria-expanded="false"
    aria-label="View all {{ $completionCount }} completions for {{ $achievementName }}"
    class="inline-flex min-h-6 items-center rounded px-1 font-semibold text-blue-700 underline decoration-transparent underline-offset-2 transition hover:decoration-current focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:text-blue-300 dark:focus-visible:outline-blue-400">
    History
</button>

<div id="{{ $dialogId }}-backdrop"
    data-achievement-history-backdrop
    aria-hidden="true"
    class="fixed inset-0 z-stack-backdrop hidden bg-gray-950/60 md:hidden"></div>

<div id="{{ $dialogId }}"
    data-achievement-history-dialog
    role="dialog"
    aria-modal="false"
    aria-hidden="true"
    aria-labelledby="{{ $titleId }}"
    class="fixed inset-0 z-stack-modal hidden flex w-full items-center justify-center p-4 text-gray-900 md:inset-auto md:left-0 md:top-0 md:block md:hidden md:w-80 md:max-w-[calc(100vw-2rem)] md:p-0 dark:text-white">
    <section class="max-h-[85dvh] w-full max-w-md overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800 md:max-h-[75dvh] md:max-w-none">
        <header class="flex items-start justify-between gap-3 border-b border-gray-200 p-4 dark:border-gray-700">
            <div>
                <h4 id="{{ $titleId }}" class="text-base font-semibold">
                    {{ $achievementName }} history
                </h4>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $completionCount }} completions</p>
            </div>
            <button type="button" data-achievement-history-close
                aria-label="Close {{ $achievementName }} history"
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-transparent text-sm text-gray-400 hover:bg-gray-200 hover:text-gray-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:hover:bg-gray-600 dark:hover:text-white dark:focus-visible:outline-blue-400">
                <svg class="h-3 w-3" fill="none" viewBox="0 0 14 14" aria-hidden="true">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                </svg>
            </button>
        </header>
        <ol class="max-h-[60dvh] divide-y divide-gray-100 overflow-y-auto overscroll-contain px-4 pb-2 text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
            @foreach ($completionDates as $completion)
                <li class="py-5 first:pt-6">
                    @if ($completion['completed_on'] !== null)
                        Completed on {{ $completion['completed_on']->format('F j, Y') }}
                    @else
                        Earned {{ $completion['earned_at']->format('F j, Y') }}
                    @endif
                </li>
            @endforeach
        </ol>
    </section>
</div>

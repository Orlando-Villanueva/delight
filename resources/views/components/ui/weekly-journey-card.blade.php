<?php use App\Enums\WeeklyJourneyDayState; ?>

@props([
    'currentProgress' => 0,
    'days' => [],
    'weekRangeText' => '',
    'weeklyTarget' => 7,
    'ctaEnabled' => true,
    'ctaVisible' => false,
    'status' => null,
    'journeyAltText' => null,
])

@php
    $journeyDays = collect($days ?? [])
        ->take(7)
        ->all();

    if (count($journeyDays) < 7) {
        $missing = 7 - count($journeyDays);

        for ($i = 0; $i < $missing; $i++) {
            $journeyDays[] = [
                'date' => null,
                'dow' => null,
                'isToday' => false,
                'read' => false,
                'title' => 'No reading logged',
                'ariaLabel' => 'No reading logged yet',
            ];
        }
    }

    $dayLabels = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];

    $statusFallback = [
        'label' => 'Get started',
        'microcopy' => 'Let\'s start your week',
        'chipClasses' =>
            'bg-gray-100 text-gray-700 border border-gray-200 dark:bg-gray-800/70 dark:text-gray-100 dark:border-gray-700',
        'microcopyClasses' => 'text-gray-600 dark:text-gray-300',
        'showCrown' => false,
    ];

    $status = array_merge($statusFallback, $status ?? []);
    $journeyAltText =
        $journeyAltText ?? sprintf('%d of %d days logged this week.', (int) $currentProgress, (int) $weeklyTarget);
    $ctaIsVisible = (bool) ($ctaVisible ?? false);
@endphp

<div {{ $attributes->class(['card h-full flex flex-col dark:bg-gray-900 dark:border-gray-800 shadow-lg']) }}>
    <div class="card-header pb-4">
        <div class="flex flex-col gap-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="card-title text-gray-900 dark:text-gray-100">Weekly Journey</p>
                    <p class="card-description text-gray-600 dark:text-gray-300">{{ $weekRangeText }}</p>
                </div>
                <output
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold {{ $status['chipClasses'] }}"
                    aria-live="polite" aria-atomic="true">
                    @if ($status['showCrown'])
                        <svg class="w-4 h-4 text-amber-500" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path
                                d="M4 18h16l1.3-7.8a.75.75 0 0 0-1.18-.73L17 12.5l-4.11-6.17a.75.75 0 0 0-1.28 0L7.5 12.5 3.88 9.47a.75.75 0 0 0-1.18.73L4 18zm-1 2.25A.75.75 0 0 0 3.75 21h16.5a.75.75 0 0 0 .75-.75V19H3v1.25z" />
                        </svg>
                    @endif
                    <span>{{ $status['label'] }}</span>
                </output>
            </div>
        </div>
    </div>

    <div class="card-content flex-1 flex flex-col gap-6 pt-4">
        <div>
            <p class="text-3xl font-semibold text-gray-900 dark:text-gray-100 leading-tight">
                {{ $currentProgress ?? 0 }}
                <span class="ml-2 text-base font-normal text-gray-500 dark:text-gray-300">
                    {{ \Illuminate\Support\Str::plural('day', $currentProgress ?? 0) }} this week
                </span>
            </p>
        </div>

        <div class="flex flex-col gap-2">
            <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAQAIBRAA7"
                alt="{{ $journeyAltText }}" class="sr-only">
            <div class="grid grid-cols-7 gap-1">
                @foreach ($journeyDays as $index => $day)
                    @php
                        $state = $day['state'] ??
                            (($day['read'] ?? false)
                                ? WeeklyJourneyDayState::COMPLETE->value
                                : WeeklyJourneyDayState::UPCOMING->value);
                        $stateClasses = [
                            WeeklyJourneyDayState::COMPLETE->value => 'bg-success-500 dark:bg-success-600 border-transparent',
                            WeeklyJourneyDayState::MISSED->value => 'bg-destructive-100 dark:bg-destructive-900/40 border-destructive-200 dark:border-destructive-700',
                            WeeklyJourneyDayState::TODAY->value => 'bg-gray-200 dark:bg-gray-700 border-transparent',
                            WeeklyJourneyDayState::UPCOMING->value => 'bg-gray-200 dark:bg-gray-700 border-transparent',
                        ];
                        $segmentStateClass = $stateClasses[$state] ?? $stateClasses[WeeklyJourneyDayState::UPCOMING->value];
                        $segmentClasses = implode(
                            ' ',
                            array_filter([
                                'h-4 rounded-sm border cursor-default transition-colors duration-300',
                                $segmentStateClass,
                                $day['isToday'] ?? false
                                    ? 'ring-2 ring-primary-400 dark:ring-primary-500 ring-offset-1 ring-offset-white dark:ring-offset-gray-900'
                                    : '',
                            ]),
                        );
                        $segmentLabel = $day['ariaLabel'] ?? ($day['title'] ?? sprintf('Day %d', $index + 1));
                    @endphp
                    <span class="{{ $segmentClasses }}" title="{{ $segmentLabel }}" aria-label="{{ $segmentLabel }}"
                        @if ($day['isToday'] ?? false) aria-current="date" @endif></span>
                @endforeach
            </div>

            <div class="grid grid-cols-7 text-xs font-medium text-gray-500 dark:text-gray-400 tracking-wide">
                @foreach ($journeyDays as $index => $day)
                    <span
                        class="text-center {{ $day['isToday'] ?? false ? 'text-gray-900 dark:text-gray-100 font-semibold' : '' }}">
                        {{ $dayLabels[$index] ?? '' }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>

    <div @class([
        'card-footer border-t border-gray-100 dark:border-gray-800 pt-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-6',
        'sm:justify-between' => $ctaIsVisible,
        'justify-start' => ! $ctaIsVisible,
    ])>
        <p class="text-sm leading-relaxed flex items-center gap-2 flex-1 min-w-0 {{ $status['microcopyClasses'] }}">
            @if ($status['showCrown'])
                <svg class="w-4 h-4 text-amber-500" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path
                        d="M4 18h16l1.3-7.8a.75.75 0 0 0-1.18-.73L17 12.5l-4.11-6.17a.75.75 0 0 0-1.28 0L7.5 12.5 3.88 9.47a.75.75 0 0 0-1.18.73L4 18zm-1 2.25A.75.75 0 0 0 3.75 21h16.5a.75.75 0 0 0 .75-.75V19H3v1.25z" />
                </svg>
            @endif
            <span>{{ $status['microcopy'] }}</span>
        </p>

        @if ($ctaIsVisible)
            <button type="button" hx-get="{{ route('logs.create') }}" hx-target="#page-container" hx-swap="innerHTML"
                hx-push-url="true"
                class="inline-flex w-full sm:w-auto shrink-0 items-center justify-center rounded-full bg-accent-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-accent-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-accent-500">
                Log reading
            </button>
        @endif
    </div>
</div>

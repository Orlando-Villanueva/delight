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
        'state' => null,
        'label' => null,
        'microcopy' => 'Kick off your week',
        'chipClasses' => '',
        'microcopyClasses' => 'text-gray-600 dark:text-gray-300',
        'showCrown' => false,
    ];

    $status = array_merge($statusFallback, $status ?? []);
    $journeyAltText =
        $journeyAltText ?? sprintf('%d of %d days logged this week.', (int) $currentProgress, (int) $weeklyTarget);
    $ctaIsVisible = (bool) ($ctaVisible ?? false);
    $isPerfectWeek = ($status['state'] ?? null) === 'perfect';
@endphp

<div
    {{ $attributes->class(['card h-full flex flex-col border border-[#D1D7E0] dark:border-gray-700 dark:bg-gray-800 shadow-lg transition-colors']) }}>
    <div class="card-header pb-4">
        <div class="flex flex-col gap-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="card-title text-gray-900 dark:text-gray-100">Weekly Journey</p>
                    <p class="card-description text-gray-600 dark:text-gray-300">{{ $weekRangeText }}</p>
                </div>
                @if (($status['state'] ?? null) === 'perfect')
                    <output
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold {{ $status['chipClasses'] }}"
                        aria-live="polite" aria-atomic="true">
                        @if ($status['showCrown'])
                            <svg class="w-5 h-5 text-amber-500" viewBox="0 0 640 640" fill="currentColor" aria-hidden="true">
                                <path
                                    d="M345 151.2C354.2 143.9 360 132.6 360 120C360 97.9 342.1 80 320 80C297.9 80 280 97.9 280 120C280 132.6 285.9 143.9 295 151.2L226.6 258.8C216.6 274.5 195.3 278.4 180.4 267.2L120.9 222.7C125.4 216.3 128 208.4 128 200C128 177.9 110.1 160 88 160C65.9 160 48 177.9 48 200C48 221.8 65.5 239.6 87.2 240L119.8 457.5C124.5 488.8 151.4 512 183.1 512H456.9C488.6 512 515.5 488.8 520.2 457.5L552.8 240C574.5 239.6 592 221.8 592 200C592 177.9 574.1 160 552 160C529.9 160 512 177.9 512 200C512 208.4 514.6 216.3 519.1 222.7L459.7 267.3C444.8 278.5 423.5 274.6 413.5 258.9L345 151.2z" />
                            </svg>
                        @endif
                        <span>{{ $status['label'] }}</span>
                    </output>
                @endif
            </div>
        </div>
    </div>

    <div class="card-content flex-1 flex flex-col gap-6 pt-4">
        <div class="flex items-center gap-3">
            <p class="text-3xl font-semibold leading-tight text-gray-900 dark:text-gray-100">
                {{ $currentProgress ?? 0 }}
            </p>
            <p class="text-base font-normal leading-tight text-gray-500 dark:text-gray-300">
                {{ \Illuminate\Support\Str::plural('day', $currentProgress ?? 0) }} this week
            </p>
        </div>

        <div class="flex flex-col gap-2">
            <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAQAIBRAA7"
                alt="{{ $journeyAltText }}" class="sr-only">
            <div class="grid grid-cols-7 gap-1">
                @foreach ($journeyDays as $index => $day)
                    @php
                        $perfectCompleteState = 'bg-amber-400/90 dark:bg-amber-400 border-transparent';
                        $state = $day['state'] ??
                            (($day['read'] ?? false)
                                ? WeeklyJourneyDayState::COMPLETE->value
                                : WeeklyJourneyDayState::UPCOMING->value);
                        $stateClasses = [
                            WeeklyJourneyDayState::COMPLETE->value => $isPerfectWeek
                                ? $perfectCompleteState
                                : 'bg-success-500 dark:bg-success-600 border-transparent',
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
        'card-footer border-t border-gray-100 dark:border-gray-700 pt-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-6',
        'sm:justify-between' => $ctaIsVisible,
        'justify-start' => ! $ctaIsVisible,
    ])>
        <p class="text-sm leading-relaxed flex items-center gap-2 flex-1 min-w-0 {{ $status['microcopyClasses'] }}">
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

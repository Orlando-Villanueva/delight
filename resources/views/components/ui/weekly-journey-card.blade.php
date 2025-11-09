@props([
    'currentProgress' => 0,
    'days' => [],
    'weekRangeText' => '',
    'weeklyTarget' => 7,
    'ctaEnabled' => true,
])

@php
    $currentProgress = max(0, (int) ($currentProgress ?? 0));
    $weeklyTarget = max(1, (int) ($weeklyTarget ?? 7));
    $ctaEnabled = (bool) ($ctaEnabled ?? true);
    $daysAsArray = is_iterable($days ?? null) ? collect($days)->toArray() : [];

    $journeyDays = [];

    if (count($daysAsArray) === 7) {
        foreach ($daysAsArray as $day) {
            $journeyDays[] = [
                'date' => $day['date'] ?? null,
                'dow' => isset($day['dow']) ? (int) $day['dow'] : null,
                'isToday' => (bool) ($day['isToday'] ?? false),
                'read' => (bool) ($day['read'] ?? false),
            ];
        }
    } else {
        $today = now();
        $weekStart = $today->copy()->startOfWeek(\Illuminate\Support\Carbon::SUNDAY);

        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $journeyDays[] = [
                'date' => $date->toDateString(),
                'dow' => $date->dayOfWeek,
                'isToday' => $date->isSameDay($today),
                'read' => false,
            ];
        }
    }

    $todaySlot = collect($journeyDays)->firstWhere('isToday', true);
    $ctaVisible = $ctaEnabled && ! ($todaySlot['read'] ?? false) && $currentProgress < $weeklyTarget;

    $statusState = 'not-started';

    if ($currentProgress >= $weeklyTarget) {
        $statusState = 'perfect';
    } elseif ($currentProgress >= 5) {
        $statusState = 'on-a-roll';
    } elseif ($currentProgress >= 4) {
        $statusState = 'solid';
    } elseif ($currentProgress >= 1) {
        $statusState = 'momentum';
    }

    $statusTokens = [
        'not-started' => [
            'label' => 'Get started',
            'microcopy' => 'Let\'s start your week',
            'chipClasses' => 'bg-gray-100 text-gray-700 border border-gray-200 dark:bg-gray-800/70 dark:text-gray-100 dark:border-gray-700',
            'microcopyClasses' => 'text-gray-600 dark:text-gray-300',
            'showCrown' => false,
        ],
        'momentum' => [
            'label' => 'Nice momentum',
            'microcopy' => 'Nice start—keep going',
            'chipClasses' => 'bg-teal-100 text-teal-800 border border-teal-200 dark:bg-teal-900/40 dark:text-teal-100 dark:border-teal-800',
            'microcopyClasses' => 'text-teal-700 dark:text-teal-200',
            'showCrown' => false,
        ],
        'solid' => [
            'label' => 'Solid week—keep going',
            'microcopy' => 'Solid week—keep reaching for 7',
            'chipClasses' => 'bg-success-100 text-success-800 border border-success-200 dark:bg-success-900/40 dark:text-success-100 dark:border-success-800',
            'microcopyClasses' => 'text-success-700 dark:text-success-200',
            'showCrown' => false,
        ],
        'on-a-roll' => [
            'label' => 'On a roll',
            'microcopy' => 'So close to perfect',
            'chipClasses' => 'bg-success-500 text-white border border-success-500 dark:bg-success-600 dark:border-success-600',
            'microcopyClasses' => 'text-success-700 dark:text-success-200',
            'showCrown' => false,
        ],
        'perfect' => [
            'label' => 'Perfect week',
            'microcopy' => 'Perfect week!',
            'chipClasses' => 'bg-amber-100 text-amber-800 border border-amber-200 dark:bg-amber-900/40 dark:text-amber-100 dark:border-amber-800',
            'microcopyClasses' => 'text-amber-600 dark:text-amber-300 font-semibold',
            'showCrown' => true,
        ],
    ];

    $status = $statusTokens[$statusState] ?? $statusTokens['not-started'];

    $rangeText = (string) ($weekRangeText ?? '');
    if ($rangeText === '' && ! empty($journeyDays)) {
        $firstDay = $journeyDays[0] ?? null;
        $lastDay = $journeyDays[count($journeyDays) - 1] ?? null;

        $startDate = ! empty($firstDay['date'])
            ? \Illuminate\Support\Carbon::parse($firstDay['date'])
            : now()->copy()->startOfWeek(\Illuminate\Support\Carbon::SUNDAY);

        $endDate = ! empty($lastDay['date'])
            ? \Illuminate\Support\Carbon::parse($lastDay['date'])
            : $startDate->copy()->endOfWeek(\Illuminate\Support\Carbon::SATURDAY);

        if ($startDate->isSameMonth($endDate)) {
            $rangeText = sprintf('%s–%s', $startDate->format('M j'), $endDate->format('j'));
        } else {
            $rangeText = sprintf('%s–%s', $startDate->format('M j'), $endDate->format('M j'));
        }
    }

    $dayLabels = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
    $journeyAltText = sprintf('%d of %d days logged this week.', $currentProgress, $weeklyTarget);
@endphp

<div {{ $attributes->class(['card h-full flex flex-col dark:bg-gray-900 dark:border-gray-800 shadow-lg']) }}>
    <div class="card-header pb-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="card-title text-gray-900 dark:text-gray-100">Weekly Journey</p>
                <p class="card-description text-gray-600 dark:text-gray-300">{{ $rangeText }}</p>
            </div>
            <div class="flex items-center justify-end">
                <output class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium {{ $status['chipClasses'] }}"
                    aria-live="polite"
                    aria-atomic="true">
                    @if($status['showCrown'])
                        <svg class="w-4 h-4 text-amber-500" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M4 18h16l1.3-7.8a.75.75 0 0 0-1.18-.73L17 12.5l-4.11-6.17a.75.75 0 0 0-1.28 0L7.5 12.5 3.88 9.47a.75.75 0 0 0-1.18.73L4 18zm-1 2.25A.75.75 0 0 0 3.75 21h16.5a.75.75 0 0 0 .75-.75V19H3v1.25z" />
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
                {{ $currentProgress }}
                <span class="ml-2 text-base font-normal text-gray-500 dark:text-gray-300">
                    {{ \Illuminate\Support\Str::plural('day', $currentProgress) }} this week
                </span>
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Target: {{ $weeklyTarget }} {{ \Illuminate\Support\Str::plural('day', $weeklyTarget) }}
            </p>
        </div>

        <div class="flex flex-col gap-2">
            <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAQAIBRAA7"
                alt="{{ $journeyAltText }}"
                class="sr-only">
            <div class="grid grid-cols-7 gap-0.5 sm:gap-1">
                @foreach($journeyDays as $index => $day)
                    @php
                        $dateInstance = ! empty($day['date']) ? \Illuminate\Support\Carbon::parse($day['date']) : null;
                        $formattedDate = $dateInstance ? $dateInstance->format('D M j') : 'Day ' . ($index + 1);
                        $readText = $day['read'] ? 'read' : 'not yet';
                        $segmentLabel = sprintf('%s — %s', $formattedDate, $readText);
                        $segmentClasses = implode(' ', array_filter([
                            'h-3 sm:h-4 rounded-sm border border-transparent cursor-default transition-colors duration-300',
                            $day['read'] ? 'bg-success-500 dark:bg-success-600' : 'bg-gray-200 dark:bg-gray-700',
                            $day['isToday'] ? 'ring-2 ring-primary-400 dark:ring-primary-500 ring-offset-1 ring-offset-white dark:ring-offset-gray-900' : '',
                        ]));
                    @endphp
                    <span class="{{ $segmentClasses }}"
                        title="{{ $segmentLabel }}"
                        aria-label="{{ $segmentLabel }}"
                        @if($day['isToday']) aria-current="date" @endif></span>
                @endforeach
            </div>

            <div class="hidden sm:grid grid-cols-7 text-xs font-medium text-gray-500 dark:text-gray-400 tracking-wide">
                @foreach($journeyDays as $index => $day)
                    <span class="text-center {{ $day['isToday'] ? 'text-gray-900 dark:text-gray-100 font-semibold' : '' }}">
                        {{ $dayLabels[$index] ?? '' }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card-footer border-t border-gray-100 dark:border-gray-800 pt-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm leading-relaxed flex items-center gap-2 {{ $status['microcopyClasses'] }}">
            @if($status['showCrown'])
                <svg class="w-4 h-4 text-amber-500" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M4 18h16l1.3-7.8a.75.75 0 0 0-1.18-.73L17 12.5l-4.11-6.17a.75.75 0 0 0-1.28 0L7.5 12.5 3.88 9.47a.75.75 0 0 0-1.18.73L4 18zm-1 2.25A.75.75 0 0 0 3.75 21h16.5a.75.75 0 0 0 .75-.75V19H3v1.25z" />
                </svg>
            @endif
            <span>{{ $status['microcopy'] }}</span>
        </p>

        @if($ctaVisible)
            <button type="button"
                hx-get="{{ route('logs.create') }}"
                hx-target="#page-container"
                hx-swap="innerHTML"
                hx-push-url="true"
                class="inline-flex w-full sm:w-auto items-center justify-center rounded-full bg-accent-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-accent-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-accent-500">
                Log today's reading
            </button>
        @endif
    </div>
</div>

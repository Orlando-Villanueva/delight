@props([
    'currentStreak' => 0,
    'longestStreak' => 0,
    'stateClasses' => [],
    'message' => '',
    'size' => 'default',
    'streakSeries' => [],
])

@php
    $contentPaddingClasses = [
        'small' => 'px-4 pb-4 lg:px-3 lg:pb-3 xl:px-4 xl:pb-4',
        'default' => 'px-6 pb-6 lg:px-4 lg:pb-4 xl:px-6 xl:pb-6',
        'large' => 'px-8 pb-8 lg:px-6 lg:pb-6 xl:px-8 xl:pb-8',
    ];

    $footerPaddingClasses = [
        'small' => 'px-4 lg:px-3 xl:px-4',
        'default' => 'px-6 lg:px-4 xl:px-6',
        'large' => 'px-8 lg:px-6 xl:px-8',
    ];

    $numberSizes = [
        'small' => 'text-2xl lg:text-3xl',
        'default' => 'text-3xl lg:text-4xl',
        'large' => 'text-4xl lg:text-5xl',
    ];

    $iconSizes = [
        'small' => 'w-5 h-5',
        'default' => 'w-6 h-6',
        'large' => 'w-8 h-8',
    ];

    $series = collect($streakSeries ?? [])
        ->map(function ($entry) {
            return [
                'date' => $entry['date'] ?? null,
                'count' => max(0, (int) ($entry['count'] ?? 0)),
            ];
        })
        ->filter(fn($entry) => !empty($entry['date']))
        ->values();

    $seriesPointCount = $series->count();
    $seriesRawMax = $series->max('count') ?? 0;
    $seriesRawMin = $series->min('count') ?? 0;
    $seriesMaxValue = max(1, $seriesRawMax);
    $sparklineWidth = 150;
    $sparklineHeight = 42;
    $sparklineGradientId = 'streakSparklineFill_' . uniqid();

    $plotOffsetX = 18;

    $pointCoordinates = $series->map(function ($entry, $index) use (
        $seriesPointCount,
        $sparklineWidth,
        $sparklineHeight,
        $seriesMaxValue,
        $plotOffsetX,
    ) {
        if ($seriesPointCount === 1) {
            $x = $plotOffsetX + $sparklineWidth;
        } else {
            $x = $plotOffsetX + ($index / max(1, $seriesPointCount - 1)) * $sparklineWidth;
        }

        $normalized = $seriesMaxValue > 0 ? $entry['count'] / $seriesMaxValue : 0;
        $y = $sparklineHeight - $normalized * $sparklineHeight;

        return [
            'x' => $x,
            'y' => $y,
            'value' => $entry['count'],
        ];
    });

    $sparklinePoints = $pointCoordinates
        ->map(fn($point) => number_format($point['x'], 2, '.', '') . ',' . number_format($point['y'], 2, '.', ''))
        ->implode(' ');

    $seriesHasTrend = $seriesPointCount >= 2 && !empty($sparklinePoints);
    $seriesStart = $series->first()['date'] ?? null;
    $seriesEnd = $series->last()['date'] ?? null;

    $axisTicks = collect([
        ['label' => 0, 'value' => 0],
        ['label' => (int) ceil($seriesMaxValue / 2), 'value' => $seriesMaxValue / 2],
        ['label' => $seriesMaxValue, 'value' => $seriesMaxValue],
    ])->unique('label');
@endphp

@php
    // Determine streak state so icon + copy can react to backend cues.
    $state = 'active';
    if (!($stateClasses['showIcon'] ?? false)) {
        $state = 'inactive';
    } elseif (str_contains($stateClasses['background'] ?? '', 'orange')) {
        $state = 'warning';
    }

    $baseClass =
        'card h-full flex flex-col border border-[#D1D7E0] dark:border-gray-700 dark:bg-gray-800 shadow-lg transition-colors';

    $numberColorClass = 'text-gray-900 dark:text-gray-100';
    $textColorClass = 'text-gray-600 dark:text-gray-300';
    $iconStateClasses = [
        'warning' => 'text-accent-400 animate-pulse dark:text-accent-300',
        'active' => 'text-accent-500 dark:text-accent-400',
        'inactive' => 'text-accent-400/70 dark:text-accent-300/70',
    ];
@endphp

<div {{ $attributes->merge(['class' => $baseClass]) }}>
    <div class="card-header pb-4">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="card-title text-gray-900 dark:text-gray-100">Daily Streak</p>
                <p class="card-description text-gray-600 dark:text-gray-300">Stay consistent day after day</p>
            </div>
            @if ($stateClasses['showIcon'] ?? false)
                <span
                    class="inline-flex shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700 p-2">
                    <svg class="{{ $iconSizes[$size] ?? $iconSizes['default'] }} {{ $iconStateClasses[$state] ?? $iconStateClasses['active'] }}"
                        fill="currentColor" viewBox="0 0 384 512">
                        <path
                            d="M216 23.86c0-23.8-30.65-32.77-44.15-13.04C48 191.85 224 200 224 288c0 35.63-29.11 64.46-64.85 63.99-35.17-.45-63.15-29.77-63.15-64.94v-85.51c0-21.7-26.47-32.4-41.6-16.9C21.22 216.4 0 268.2 0 320c0 105.87 86.13 192 192 192s192-86.13 192-192c0-170.29-168-193.17-168-296.14z" />
                    </svg>
                </span>
            @endif
        </div>
    </div>

    <div
        class="card-content flex-1 flex flex-col gap-6 pt-4 {{ $contentPaddingClasses[$size] ?? $contentPaddingClasses['default'] }}">
        <div class="flex flex-col gap-3">
            <div class="flex items-center gap-3">
                <p class="{{ $numberSizes[$size] ?? $numberSizes['default'] }} font-semibold {{ $numberColorClass }}">
                    {{ $currentStreak }}
                </p>
                <p class="text-base font-normal {{ $textColorClass }}">
                    {{ $currentStreak === 1 ? 'day' : 'days' }} in a row
                </p>
            </div>

            @if ($seriesHasTrend)
                <figure class="mt-2">
                    <svg viewBox="0 0 {{ $sparklineWidth + $plotOffsetX + 2 }} {{ $sparklineHeight }}" role="img"
                        aria-label="Daily readings since streak began. Starts {{ $seriesStart }}, most recent {{ $seriesEnd }}."
                        class="w-full h-12" style="overflow: visible;">
                        <defs>
                            <linearGradient id="{{ $sparklineGradientId }}" x1="0%" y1="0%"
                                x2="0%" y2="100%">
                                <stop offset="0%" stop-color="#3B82F6" stop-opacity="0.25" />
                                <stop offset="100%" stop-color="#3B82F6" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <line x1="{{ $plotOffsetX }}" y1="0" x2="{{ $plotOffsetX }}"
                            y2="{{ $sparklineHeight }}" stroke="rgba(148, 163, 184, 0.35)" stroke-width="1"
                            class="dark:stroke-gray-600" />
                        @foreach ($axisTicks as $tick)
                            @php
                                $tickNormalized = $seriesMaxValue > 0 ? $tick['value'] / $seriesMaxValue : 0;
                                $tickY = $sparklineHeight - $tickNormalized * $sparklineHeight;
                            @endphp
                            <line x1="{{ $plotOffsetX - 4 }}" y1="{{ $tickY }}" x2="{{ $plotOffsetX }}"
                                y2="{{ $tickY }}" stroke="rgba(148, 163, 184, 0.6)" stroke-width="1" />
                            <text x="{{ $plotOffsetX - 6 }}" y="{{ $tickY + 3 }}" font-size="10" text-anchor="end"
                                fill="#94a3b8" class="dark:fill-gray-400">
                                {{ $tick['label'] }}
                            </text>
                        @endforeach
                        <polyline
                            points="{{ $sparklinePoints }} {{ $plotOffsetX + $sparklineWidth }},{{ $sparklineHeight }} {{ $plotOffsetX }},{{ $sparklineHeight }}"
                            fill="url(#{{ $sparklineGradientId }})" stroke="none" />
                        <polyline points="{{ $sparklinePoints }}" fill="none"
                            stroke="var(--color-primary-500, #2563eb)" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" class="dark:stroke-primary-400" />
                    </svg>
                    <figcaption class="sr-only">
                        Reading counts per day along the current streak
                    </figcaption>
                </figure>
            @endif

            @if ($longestStreak > $currentStreak)
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                    Record: {{ $longestStreak }} {{ Str::plural('day', $longestStreak) }}
                </p>
            @endif
        </div>
    </div>

    @if ($message)
        <div
            class="card-footer border-t border-gray-100 dark:border-gray-700 pt-4 pb-4 {{ $footerPaddingClasses[$size] ?? $footerPaddingClasses['default'] }}">
            <p class="text-sm leading-relaxed text-gray-700 dark:text-gray-200">
                {{ $message }}
            </p>
        </div>
    @endif
</div>

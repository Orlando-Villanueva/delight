@props([
    'currentStreak' => 0,
    'longestStreak' => 0,
    'stateClasses' => [],
    'message' => '',
    'size' => 'default',
    'streakSeries' => [],
    'messageTone' => 'default',
    'recordStatus' => 'none',
    'recordJustBroken' => false,
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
        'small' => 'text-2xl',
        'default' => 'text-3xl',
        'large' => 'text-4xl',
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
        ->filter(fn ($entry) => ! empty($entry['date']))
        ->values();

    $minSeriesLength = 15;
    if ($series->count() > 0 && $series->count() < $minSeriesLength) {
        $placeholderCount = $minSeriesLength - $series->count();
        $placeholders = collect()->times($placeholderCount, fn () => [
            'date' => null,
            'count' => 0,
        ]);
        $series = $series->merge($placeholders)->values();
    }

    $seriesPointCount = $series->count();
    $seriesRawMax = $series->max('count') ?? 0;
    $seriesRawMin = $series->min('count') ?? 0;
    $seriesMaxValue = max(3, $seriesRawMax);
    $sparklineHeight = 42;
    $sparklineGradientId = 'streakSparklineFill_' . uniqid();

    $baseWidth = 160;
    $maxWidth = 260;
    $dynamicWidth = $seriesPointCount > 0 ? $seriesPointCount * 18 : $baseWidth;
    $sparklineWidth = min($maxWidth, max($baseWidth, $dynamicWidth));
    $plotOffsetX = 18;
    $viewBoxWidth = $sparklineWidth + $plotOffsetX + 2;
    $plotWidth = $sparklineWidth;

    $pointCoordinates = $series->map(function ($entry, $index) use (
        $seriesPointCount,
        $plotWidth,
        $sparklineHeight,
        $seriesMaxValue,
        $plotOffsetX,
    ) {
        if ($seriesPointCount === 1) {
            $x = $plotOffsetX + $plotWidth;
        } else {
            $x = $plotOffsetX + ($index / max(1, $seriesPointCount - 1)) * $plotWidth;
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

    $normalizedRecordStatus = strtolower((string) $recordStatus);
    $isRecordRun = $normalizedRecordStatus === 'record';
    $sparklineStrokeColor = $isRecordRun
        ? 'var(--color-accent-500, #f97316)'
        : 'var(--color-primary-500, #2563eb)';
    $sparklineGradientColor = $isRecordRun ? '#fb923c' : '#3B82F6';
    $sparklineStrokeDarkClass = $isRecordRun ? 'dark:stroke-accent-300' : 'dark:stroke-primary-400';
    $messageToneClasses = [
        'default' => 'text-gray-700 dark:text-gray-200',
        'accent' => 'text-accent-600 dark:text-accent-400',
    ];
@endphp

<div {{ $attributes->merge(['class' => $baseClass]) }}>
    <div class="card-header pb-4">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="card-title text-gray-900 dark:text-gray-100">Daily Streak</p>
                <p class="card-description text-gray-600 dark:text-gray-300">Stay consistent day after day</p>
            </div>
            <div class="flex items-center gap-2">
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
    </div>

    <div
        class="card-content flex-1 flex flex-col gap-6 pt-4 {{ $contentPaddingClasses[$size] ?? $contentPaddingClasses['default'] }}">
        <div class="flex flex-col gap-3">
            <div class="flex items-center gap-3 flex-wrap">
                <div class="flex items-center gap-3">
                    <p class="{{ $numberSizes[$size] ?? $numberSizes['default'] }} font-semibold {{ $numberColorClass }}">
                        {{ $currentStreak }}
                    </p>
                    <p class="text-base font-normal {{ $textColorClass }}">
                        {{ $currentStreak === 1 ? 'day' : 'days' }} in a row
                    </p>
                </div>

                @if ($isRecordRun)
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-gradient-to-r from-accent-500 to-amber-400 px-3 py-1 text-xs font-semibold text-white shadow-sm {{ $recordJustBroken ? 'animate-pulse' : '' }}"
                        title="You're on your longest streak ever">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-white" fill="currentColor"
                            viewBox="0 0 16 16" aria-hidden="true">
                            <path
                                d="M2.5.5A.5.5 0 0 1 3 0h10a.5.5 0 0 1 .5.5q0 .807-.034 1.536a3 3 0 1 1-1.133 5.89c-.79 1.865-1.878 2.777-2.833 3.011v2.173l1.425.356c.194.048.377.135.537.255L13.3 15.1a.5.5 0 0 1-.3.9H3a.5.5 0 0 1-.3-.9l1.838-1.379c.16-.12.343-.207.537-.255L6.5 13.11v-2.173c-.955-.234-2.043-1.146-2.833-3.012a3 3 0 1 1-1.132-5.89A33 33 0 0 1 2.5.5m.099 2.54a2 2 0 0 0 .72 3.935c-.333-1.05-.588-2.346-.72-3.935m10.083 3.935a2 2 0 0 0 .72-3.935c-.133 1.59-.388 2.885-.72 3.935" />
                        </svg>
                        <span class="uppercase tracking-wide text-[11px]">Record</span>
                    </span>
                    @if ($recordJustBroken)
                        <span class="sr-only">New personal record streak declared today</span>
                    @endif
                @elseif ($longestStreak > $currentStreak)
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-gray-100 dark:bg-gray-700 px-3 py-1 text-xs font-semibold text-gray-700 dark:text-gray-200"
                        title="Best streak: {{ $longestStreak }} {{ Str::plural('day', $longestStreak) }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-amber-500" fill="currentColor"
                            viewBox="0 0 16 16" aria-hidden="true">
                            <path
                                d="M2.5.5A.5.5 0 0 1 3 0h10a.5.5 0 0 1 .5.5q0 .807-.034 1.536a3 3 0 1 1-1.133 5.89c-.79 1.865-1.878 2.777-2.833 3.011v2.173l1.425.356c.194.048.377.135.537.255L13.3 15.1a.5.5 0 0 1-.3.9H3a.5.5 0 0 1-.3-.9l1.838-1.379c.16-.12.343-.207.537-.255L6.5 13.11v-2.173c-.955-.234-2.043-1.146-2.833-3.012a3 3 0 1 1-1.132-5.89A33 33 0 0 1 2.5.5m.099 2.54a2 2 0 0 0 .72 3.935c-.333-1.05-.588-2.346-.72-3.935m10.083 3.935a2 2 0 0 0 .72-3.935c-.133 1.59-.388 2.885-.72 3.935" />
                        </svg>
                        {{ $longestStreak }}
                    </span>
                @endif
            </div>

            @if ($seriesHasTrend)
                <figure class="mt-2">
                    <svg viewBox="0 0 {{ $viewBoxWidth }} {{ $sparklineHeight }}" role="img"
                        aria-label="Daily readings since streak began. Starts {{ $seriesStart }}, most recent {{ $seriesEnd }}."
                        class="w-full h-12" style="overflow: visible;">
                        <defs>
                            <linearGradient id="{{ $sparklineGradientId }}" x1="0%" y1="0%"
                                x2="0%" y2="100%">
                                <stop offset="0%" stop-color="{{ $sparklineGradientColor }}" stop-opacity="0.25" />
                                <stop offset="100%" stop-color="{{ $sparklineGradientColor }}" stop-opacity="0" />
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
                            points="{{ $sparklinePoints }} {{ $plotOffsetX + $plotWidth }},{{ $sparklineHeight }} {{ $plotOffsetX }},{{ $sparklineHeight }}"
                            fill="url(#{{ $sparklineGradientId }})" stroke="none" />
                        <polyline points="{{ $sparklinePoints }}" fill="none"
                            stroke="{{ $sparklineStrokeColor }}" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" class="{{ $sparklineStrokeDarkClass }}" />
                    </svg>
                    <figcaption class="sr-only">
                        Reading counts per day along the current streak
                    </figcaption>
                </figure>
            @endif

        </div>
    </div>

    @if ($message)
        <div
            class="card-footer border-t border-gray-100 dark:border-gray-700 pt-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-6 {{ $footerPaddingClasses[$size] ?? $footerPaddingClasses['default'] }}">
            <p class="text-sm leading-relaxed flex items-center gap-2 flex-1 min-w-0 {{ $messageToneClasses[$messageTone] ?? $messageToneClasses['default'] }}">
                {{ $message }}
            </p>
        </div>
    @endif
</div>

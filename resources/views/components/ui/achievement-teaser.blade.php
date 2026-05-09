@props([
    'teaser' => ['latest' => null, 'next' => null],
])

@php
    $latest = $teaser['latest'] ?? null;
    $next = $teaser['next'] ?? null;
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-[#D1D7E0] bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800']) }}>
    <div class="p-4">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-400">Achievements</p>
                <h2 class="mt-1 text-base font-semibold text-gray-900 dark:text-white">Latest trophy</h2>
            </div>
            <a href="{{ route('achievements.index') }}"
                class="shrink-0 rounded-full bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                View all
            </a>
        </div>

        @if ($latest)
            <div class="mt-4 rounded-lg bg-green-50 p-3 dark:bg-green-900/20">
                <p class="font-semibold text-gray-900 dark:text-white">{{ $latest->display_name }}</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $latest->description }}</p>
                <p class="mt-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                    Earned {{ $latest->earned_at->format('M j, Y') }}
                </p>
            </div>
        @else
            <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                Log your first reading to start filling your trophy shelf.
            </p>
        @endif

        @if ($next)
            <div class="mt-4">
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="font-medium text-gray-700 dark:text-gray-200">Next: {{ $next['display_name'] }}</span>
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $next['current'] }}/{{ $next['target'] }}</span>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                    <div class="h-full rounded-full bg-blue-600 dark:bg-blue-400" style="width: {{ $next['progress_percent'] }}%"></div>
                </div>
            </div>
        @endif
    </div>
</div>

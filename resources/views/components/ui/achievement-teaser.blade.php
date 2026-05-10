@props([
    'teaser' => ['latest' => null, 'next' => null],
])

@php
    $latest = $teaser['latest'] ?? null;
    $next = $teaser['next'] ?? null;
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-[#D1D7E0] bg-white shadow-lg transition-colors dark:border-gray-700 dark:bg-gray-800']) }}>
    <div class="flex h-full flex-col gap-4 p-4 xl:flex-row xl:items-center xl:justify-between xl:gap-5">
        <div class="flex items-start justify-between gap-3 xl:w-48 xl:shrink-0">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-400">Achievements</p>
                <h2 class="mt-1 text-base font-semibold text-gray-900 dark:text-white">Next milestone</h2>
            </div>
            <a href="{{ route('achievements.index') }}"
                class="shrink-0 rounded-full bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 xl:hidden">
                View shelf
            </a>
        </div>

        <div class="flex min-w-0 flex-1 flex-col gap-4 xl:flex-row xl:items-center xl:justify-between xl:gap-5">
            @if ($next)
                <div class="flex min-w-0 gap-3 xl:flex-1">
                    <x-achievements.badge :icon="$next['icon'] ?? 'trophy'" :label="$next['display_name']" size="md" state="muted" />
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $next['display_name'] }}</p>
                        <p class="mt-1 text-sm leading-5 text-gray-600 dark:text-gray-300">{{ $next['description'] }}</p>
                    </div>
                </div>

                <div class="xl:w-72 xl:shrink-0">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="font-medium text-gray-700 dark:text-gray-200">Progress</span>
                        <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $next['current'] }}/{{ $next['target'] }}</span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                        <div class="h-full rounded-full bg-blue-600 dark:bg-blue-400" style="width: {{ $next['progress_percent'] }}%"></div>
                    </div>
                </div>

                @if ($latest)
                    <p class="border-t border-gray-100 pt-3 text-xs font-medium text-gray-500 dark:border-gray-700 dark:text-gray-400 xl:w-48 xl:shrink-0 xl:border-t-0 xl:pt-0">
                        Latest trophy: <span class="text-gray-700 dark:text-gray-200">{{ $latest->display_name }}</span>
                    </p>
                @endif
            @elseif ($latest)
                <div class="flex min-w-0 gap-3 xl:flex-1">
                    <x-achievements.badge :icon="$latest->icon" :label="$latest->display_name" size="md" />
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900 dark:text-white">Latest trophy: {{ $latest->display_name }}</p>
                        <p class="mt-1 text-sm leading-5 text-gray-600 dark:text-gray-300">{{ $latest->description }}</p>
                        <p class="mt-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                            Earned {{ $latest->earned_at->format('M j, Y') }}
                        </p>
                    </div>
                </div>
            @else
                <div class="flex min-w-0 gap-3 xl:flex-1">
                    <x-achievements.badge icon="sparkles" label="First reading" size="md" state="muted" />
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900 dark:text-white">First milestone</p>
                        <p class="mt-1 text-sm leading-5 text-gray-600 dark:text-gray-300">
                            Log your first reading to start filling your trophy shelf.
                        </p>
                    </div>
                </div>

                <div class="xl:w-72 xl:shrink-0">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="font-medium text-gray-700 dark:text-gray-200">First reading</span>
                        <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">0/1</span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                        <div class="h-full rounded-full bg-blue-600 dark:bg-blue-400" style="width: 0%"></div>
                    </div>
                </div>
            @endif
        </div>

        <a href="{{ route('achievements.index') }}"
            class="hidden shrink-0 rounded-full bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 xl:inline-flex">
            View shelf
        </a>
    </div>
</div>

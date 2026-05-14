@props([
    'milestoneData' => ['latest' => null, 'milestone' => null],
])

@php
    $latest = $milestoneData['latest'] ?? null;
    $milestone = $milestoneData['milestone'] ?? null;
    $displayName = $milestone['display_name'] ?? ($latest ? 'Latest trophy' : 'First reading');
    $description = $milestone['description'] ?? ($latest?->description ?? 'Log your first Bible reading.');
    $current = (int) ($milestone['current'] ?? 0);
    $target = (int) ($milestone['target'] ?? 1);
    $remaining = max(0, $target - $current);
    $progressPercent = (int) ($milestone['progress_percent'] ?? 0);
    $statusLabel = $remaining > 0 ? "{$remaining} to go" : 'Ready';
    $badgeIcon = $milestone['icon'] ?? ($latest?->icon ?? 'sparkles');
    $badgeState = $milestone ? 'muted' : ($latest ? 'earned' : 'muted');
@endphp

<div {{ $attributes->merge(['class' => 'card h-full flex flex-col border border-[#D1D7E0] dark:border-gray-700 dark:bg-gray-800 shadow-lg transition-colors']) }}>
    <div class="card-header pb-4">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="card-title text-gray-900 dark:text-gray-100">Next Milestone</p>
                <p class="card-description text-gray-600 dark:text-gray-300">{{ $displayName }}</p>
            </div>
            <x-achievements.badge :icon="$badgeIcon" :label="$displayName" size="md" :state="$badgeState" />
        </div>
    </div>

    <div class="card-content flex-1 flex flex-col gap-6 px-6 pb-6 pt-4 lg:px-4 lg:pb-4 xl:px-6 xl:pb-6">
        @if ($milestone)
            <div class="flex flex-col gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-3">
                        <p class="text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ $current }}/{{ $target }}</p>
                        <p class="text-base font-normal text-gray-600 dark:text-gray-300">progress</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:ring-gray-600">
                        {{ $statusLabel }}
                    </span>
                </div>

                <div class="mt-2">
                    <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                        <div class="h-full rounded-full bg-blue-600 dark:bg-blue-400" style="width: {{ $progressPercent }}%"></div>
                    </div>
                </div>

                <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">{{ $description }}</p>
            </div>
        @elseif ($latest)
            <div class="flex flex-col gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    <p class="text-3xl font-semibold text-gray-900 dark:text-gray-100">Done</p>
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:ring-gray-600">
                        Shelf caught up
                    </span>
                </div>

                <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">{{ $description }}</p>
            </div>
        @else
            <div class="flex flex-col gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-3">
                        <p class="text-3xl font-semibold text-gray-900 dark:text-gray-100">0/1</p>
                        <p class="text-base font-normal text-gray-600 dark:text-gray-300">progress</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:ring-gray-600">
                        1 to go
                    </span>
                </div>

                <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                    <div class="h-full rounded-full bg-blue-600 dark:bg-blue-400" style="width: 0%"></div>
                </div>

                <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">{{ $description }}</p>
            </div>
        @endif
    </div>

    <div class="card-footer border-t border-gray-100 pt-4 dark:border-gray-700 px-6 lg:px-4 xl:px-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="min-w-0 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                @if ($latest)
                    Latest trophy: <span class="font-medium text-gray-700 dark:text-gray-200">{{ $latest->display_name }}</span>
                @else
                    Build the shelf one reading at a time.
                @endif
            </p>
            <a href="{{ route('achievements.index') }}"
                class="inline-flex shrink-0 items-center text-sm font-medium text-blue-600 transition hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
                View shelf
            </a>
        </div>
    </div>
</div>

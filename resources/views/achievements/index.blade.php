@extends('layouts.authenticated')

@section('page-title', 'Achievements')
@section('page-subtitle', 'Your permanent trophy shelf')

@section('content')
    @fragment('content')
        <div class="space-y-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-400">Achievements</p>
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Trophy Shelf</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        Lasting milestones from your Bible reading journey.
                    </p>
                </div>
            </div>

            @if ($shelf['recent']->isNotEmpty())
                <section class="space-y-3">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Latest wins</h2>
                    <div class="flex gap-2 overflow-x-auto pb-1 sm:grid sm:grid-cols-3 sm:overflow-visible sm:pb-0">
                        @foreach ($shelf['recent'] as $achievement)
                            <article class="min-w-56 rounded-lg border border-[#D1D7E0] bg-white px-3 py-2.5 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:min-w-0">
                                <div class="flex items-center gap-2.5">
                                    <x-achievements.badge :icon="$achievement->icon" :label="$achievement->display_name" size="xs" />
                                    <div class="min-w-0">
                                        <h3 class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $achievement->display_name }}</h3>
                                        <p class="mt-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                                            Earned {{ $achievement->earned_at->format('F j, Y') }}
                                        </p>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="space-y-4">
                @forelse ($shelf['earned'] as $category => $achievements)
                    <div class="space-y-3">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ $categoryLabels[$category] ?? Str::headline($category) }}
                        </h2>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($achievements as $achievement)
                                <article class="rounded-xl border border-[#D1D7E0] bg-white p-4 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                                    <div class="flex items-start gap-3">
                                        <x-achievements.badge :icon="$achievement->icon" :label="$achievement->display_name" size="md" />
                                        <div class="min-w-0">
                                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ $achievement->display_name }}</h3>
                                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $achievement->description }}</p>
                                            <p class="mt-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                                                Earned {{ $achievement->earned_at->format('F j, Y') }}
                                            </p>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-[#D1D7E0] bg-white p-6 text-center shadow-lg dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">No trophies yet</h2>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                            Your first achievement appears here after you log a reading.
                        </p>
                    </div>
                @endforelse
            </section>

            @if ($shelf['locked']->isNotEmpty())
                <section class="space-y-3">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Locked and in progress</h2>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($shelf['locked'] as $achievement)
                            <article class="rounded-xl border border-[#D1D7E0] bg-white p-4 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                                <div class="flex items-start gap-3">
                                    <x-achievements.badge :icon="$achievement['icon']" :label="$achievement['display_name']" size="md" state="locked" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Locked</p>
                                        <h3 class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $achievement['display_name'] }}</h3>
                                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $achievement['description'] }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                        {{ $achievement['current'] }}/{{ $achievement['target'] }}
                                    </span>
                                </div>
                                <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700" aria-label="Reading streak progress">
                                    <div class="h-full rounded-full bg-blue-600 dark:bg-blue-400" style="width: {{ $achievement['progress_percent'] }}%"></div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    @endfragment
@endsection

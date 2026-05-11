@extends('layouts.authenticated')

@section('page-title', 'Achievements')
@section('page-subtitle', 'Permanent milestones from your Bible reading journey.')

@section('content')
    @fragment('content')
        <x-ui.page-shell width="wide">
            <x-ui.page-header
                title="Achievements"
                subtitle="Permanent milestones from your Bible reading journey."
            />

            @php
                $nextGoals = $shelf['next_goals'];
            @endphp

            @if ($nextGoals['books']->isNotEmpty() || $nextGoals['progress']->isNotEmpty())
                <section class="max-w-7xl space-y-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Next goals</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">The closest milestones in your reading journey.</p>
                    </div>

                    @if ($nextGoals['books']->isNotEmpty())
                        <div class="space-y-3">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Almost finished</h3>
                            <div class="grid grid-cols-1 gap-3 2xl:grid-cols-2">
                                @foreach ($nextGoals['books'] as $goal)
                                    <article class="rounded-xl border border-[#D1D7E0] bg-white p-4 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                                        <div class="flex items-start gap-3">
                                            <x-achievements.badge :icon="$goal['icon']" :label="$goal['book_name']" size="md" state="muted" />
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-start justify-between gap-3">
                                                    <h4 class="font-semibold text-gray-900 dark:text-white">{{ $goal['book_name'] }}</h4>
                                                    <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                        {{ $goal['chapters_read'] }}/{{ $goal['total_chapters'] }}
                                                    </span>
                                                </div>
                                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                                    {{ $goal['chapters_read'] }}/{{ $goal['total_chapters'] }} chapters · {{ $goal['chapters_remaining'] }} left
                                                </p>
                                                @if (! empty($goal['missing_chapters']))
                                                    <p class="mt-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                                                        Missing {{ implode(', ', $goal['missing_chapters']) }}
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700" aria-label="{{ $goal['book_name'] }} completion progress">
                                            <div class="h-full rounded-full bg-blue-600 dark:bg-blue-400" style="width: {{ $goal['progress_percent'] }}%"></div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($nextGoals['progress']->isNotEmpty())
                        <div class="space-y-3">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">In progress</h3>
                            <div class="grid grid-cols-1 gap-3 2xl:grid-cols-2">
                                @foreach ($nextGoals['progress'] as $achievement)
                                    <article class="rounded-xl border border-[#D1D7E0] bg-white p-4 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                                        <div class="flex items-start gap-3">
                                            <x-achievements.badge :icon="$achievement['icon']" :label="$achievement['display_name']" size="md" state="muted" />
                                            <div class="min-w-0 flex-1">
                                                <h4 class="font-semibold text-gray-900 dark:text-white">{{ $achievement['display_name'] }}</h4>
                                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $achievement['description'] }}</p>
                                            </div>
                                            <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                {{ $achievement['current'] }}/{{ $achievement['target'] }}
                                            </span>
                                        </div>
                                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700" aria-label="{{ $achievement['display_name'] }} progress">
                                            <div class="h-full rounded-full bg-blue-600 dark:bg-blue-400" style="width: {{ $achievement['progress_percent'] }}%"></div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    @endif
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
                                            @if (($achievement->metadata['passage'] ?? null) && ($achievement->metadata['date_read'] ?? null))
                                                <p class="mt-1 text-xs font-semibold text-blue-700 dark:text-blue-300">
                                                    {{ $achievement->metadata['passage'] }} · {{ \Carbon\Carbon::parse($achievement->metadata['date_read'])->format('F j, Y') }}
                                                </p>
                                            @endif
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

        </x-ui.page-shell>
    @endfragment
@endsection

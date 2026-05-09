@props([
    'payload',
])

@php
    $earned = collect($payload['earned'] ?? []);
    $progress = collect($payload['progress'] ?? []);
    $reading = $payload['reading'] ?? [];
    $heroIcon = $earned->count() === 1 ? ($earned->first()['icon'] ?? 'trophy') : 'trophy';
@endphp

@if ($earned->isNotEmpty())
    <div id="achievement-celebration-root" hx-swap-oob="innerHTML">
        <div id="achievement-celebration-modal"
            class="fixed inset-0 z-stack-modal flex items-center justify-center bg-gray-950/70 px-4 py-6 backdrop-blur-sm"
            role="dialog" aria-modal="true" aria-labelledby="achievement-celebration-title"
            x-data="{ open: true, progressReady: false }"
            x-show="open"
            x-transition.opacity
            x-init="
                $nextTick(() => $refs.closeButton?.focus());
                setTimeout(() => progressReady = true, 120);
                if (typeof window.confetti === 'function') {
                    window.confetti({ particleCount: 180, spread: 90, origin: { y: 0.35 } });
                }
            "
            @keydown.escape.window="open = false">
            <div class="absolute inset-0" aria-hidden="true" @click="open = false"></div>

            <div class="relative w-full max-w-lg max-h-full p-4">
                <div class="relative max-h-[min(86vh,760px)] overflow-y-auto rounded-lg bg-gray-50 shadow dark:bg-[#2f3746]">
                    <button type="button"
                        class="absolute top-3 end-2.5 z-10 inline-flex h-8 w-8 items-center justify-center rounded-lg bg-transparent text-sm text-gray-400 hover:bg-gray-200 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white"
                        x-ref="closeButton"
                        @click="open = false">
                        <svg class="h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                        </svg>
                        <span class="sr-only">Close modal</span>
                    </button>

                    <div class="border-b border-gray-200/80 bg-white px-5 pb-5 pt-5 dark:border-white/10 dark:bg-[#2f3746] sm:px-6">
                        <div class="mb-2.5 flex justify-center">
                            <x-achievements.badge :icon="$heroIcon" size="xl" />
                        </div>

                        <div class="text-center">
                            <p class="text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">
                                {{ $earned->count() === 1 ? 'Achievement unlocked' : 'Achievements unlocked' }}
                            </p>
                            <h2 id="achievement-celebration-title" class="mt-1 text-2xl font-bold text-gray-950 dark:text-white sm:text-3xl">
                                {{ $earned->count() === 1 ? $earned->first()['display_name'] : $earned->count().' achievements unlocked' }}
                            </h2>
                            @if (! empty($reading['passage']) && ! empty($reading['date']))
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $reading['passage'] }} recorded for {{ $reading['date'] }}.
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="bg-gray-50 px-5 py-5 dark:bg-[#2f3746] sm:px-6">
                        <div class="space-y-3">
                            @foreach ($earned as $achievement)
                                <article class="rounded-lg border border-gray-200/70 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                                    <div class="flex gap-3">
                                        <x-achievements.badge :icon="$achievement['icon'] ?? 'trophy'" :label="$achievement['display_name']" size="sm" class="mt-0.5" />
                                        <div class="min-w-0">
                                            <h3 class="font-semibold text-gray-950 dark:text-white">{{ $achievement['display_name'] }}</h3>
                                            <p class="mt-1 text-sm leading-5 text-gray-700 dark:text-gray-300">{{ $achievement['description'] }}</p>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        @if ($progress->isNotEmpty())
                            <section class="mt-6" aria-labelledby="achievement-next-up-title">
                                <h3 id="achievement-next-up-title" class="text-sm font-semibold text-gray-950 dark:text-white">Next up</h3>
                                <div class="mt-3 space-y-2.5">
                                    @foreach ($progress as $next)
                                        <div class="rounded-lg border border-gray-200/70 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-white/5">
                                            <div class="flex items-center gap-3">
                                                <x-achievements.badge :icon="$next['icon'] ?? 'trophy'" :label="$next['display_name']" size="xs" state="locked" />
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-center justify-between gap-3 text-sm">
                                                        <p class="min-w-0 truncate font-medium text-gray-800 dark:text-gray-100">{{ $next['display_name'] }}</p>
                                                        <p class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $next['current'] }}/{{ $next['target'] }}</p>
                                                    </div>
                                                    <div class="mt-2 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700/80">
                                                        <div class="h-full rounded-full bg-primary-600 transition-[width] duration-500 ease-in-out dark:bg-primary-400"
                                                            style="width: 0%"
                                                            x-bind:style="progressReady ? 'width: {{ $next['progress_percent'] }}%' : 'width: 0%'"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-gray-200/80 bg-gray-50 px-5 py-4 dark:border-white/10 dark:bg-[#2f3746] sm:flex-row sm:justify-end sm:px-6">
                        <button type="button"
                            class="w-full rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-900 hover:bg-gray-50 hover:text-gray-900 focus-visible:z-10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:border-gray-500 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600 dark:hover:text-white dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-gray-900 sm:w-auto"
                            @click="open = false">
                            Keep reading
                        </button>
                        <a href="{{ route('achievements.index') }}"
                            hx-get="{{ route('achievements.index') }}"
                            hx-target="#page-container"
                            hx-swap="innerHTML"
                            hx-push-url="true"
                            class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-gray-900 rounded-lg"
                            @click="open = false">
                            View trophy shelf
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

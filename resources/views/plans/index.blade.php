@extends('layouts.authenticated')

@section('page-title', 'Reading Plans')
@section('page-subtitle', 'Structured guides for your Bible reading journey')

@section('content')
    @fragment('content')
        @php
            $startingPassagePlans = collect($plans)
                ->filter(fn (array $planData): bool => ! $planData['is_subscribed'])
                ->mapWithKeys(function (array $planData): array {
                    $plan = $planData['plan'];

                    return [
                        $plan->slug => [
                            'name' => $plan->getShortName(),
                            'subscribeUrl' => route('plans.subscribe', $plan),
                            'firstDay' => $plan->getFirstDayNumber(),
                            'days' => collect($plan->days ?? [])->map(fn (array $day): array => [
                                'day' => (int) $day['day'],
                                'optionLabel' => $day['label'].' - Day '.$day['day'],
                            ])->values()->all(),
                        ],
                    ];
                })
                ->all();
        @endphp

        <x-ui.page-shell width="medium" id="main-content">
            <x-ui.page-header
                title="Reading Plans"
                subtitle="Structured guides to help you read the Bible consistently"
            />

                    <div class="space-y-6" x-data>
                        @forelse ($plans as $planData)
                            @php
                                $plan = $planData['plan'];
                                $subscription = $planData['subscription'];
                                $isSubscribed = $planData['is_subscribed'];
                            @endphp

                            <div
                                class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                                <div class="p-6">
                                    @if ($isSubscribed && $subscription)
                                        {{-- Subscribed Plan Card - Streamlined for quick action --}}
                                        <div class="relative">
                                            {{-- Header row: Title + CTA (CTA moves to bottom on mobile) --}}
                                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                                <div class="flex items-center justify-between sm:justify-start gap-2">
                                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                                                        {{ $plan->getShortName() }}
                                                    </h3>
                                                    @if (!$subscription->is_active)
                                                        <span
                                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                                                            Paused
                                                        </span>
                                                    @endif
                                                </div>
                                                @if ($subscription->is_active)
                                                    <button hx-get="{{ route('plans.today', $plan) }}"
                                                        hx-target="#page-container" hx-swap="innerHTML" hx-push-url="true"
                                                        class="order-last sm:order-none w-full sm:w-auto flex-shrink-0 inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors shadow-sm cursor-pointer">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                                        </svg>
                                                        Continue Reading
                                                    </button>
                                                @else
                                                    <button hx-get="{{ route('plans.today', $plan) }}"
                                                        hx-target="#page-container" hx-swap="innerHTML" hx-push-url="true"
                                                        class="order-last sm:order-none w-full sm:w-auto flex-shrink-0 inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors cursor-pointer">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                        View Progress
                                                    </button>
                                                @endif

                                                {{-- Progress info (shown between title and button on mobile) --}}
                                                <div class="flex-1 sm:hidden">
                                                    <div class="flex items-center justify-between text-sm">
                                                        <span class="text-gray-600 dark:text-gray-400">
                                                            Day {{ $subscription->getDayNumber() }} of
                                                            {{ $plan->getLastDayNumber() }}
                                                        </span>
                                                        <span class="font-medium text-primary-600 dark:text-primary-400">
                                                            {{ $subscription->getCompletedDaysCount() }} of {{ $subscription->getTrackedDaysCount() }} tracked days complete
                                                        </span>
                                                    </div>
                                                    <div class="mt-2 w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                                        <div class="bg-primary-600 h-2 rounded-full transition-all duration-300"
                                                            style="width: {{ $subscription->getProgress() }}%"></div>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Progress info (desktop only - below header) --}}
                                            <div class="hidden sm:block mt-4">
                                                <div class="flex items-center justify-between text-sm">
                                                    <span class="text-gray-600 dark:text-gray-400">
                                                        Day {{ $subscription->getDayNumber() }} of {{ $plan->getLastDayNumber() }}
                                                    </span>
                                                    <span class="font-medium text-primary-600 dark:text-primary-400">
                                                        {{ $subscription->getCompletedDaysCount() }} of {{ $subscription->getTrackedDaysCount() }} tracked days complete
                                                    </span>
                                                </div>
                                                <div class="mt-2 w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                                    <div class="bg-primary-600 h-2 rounded-full transition-all duration-300"
                                                        style="width: {{ $subscription->getProgress() }}%"></div>
                                                </div>
                                            </div>

                                        </div>
                                    @else
                                        {{-- Unsubscribed Plan Card --}}
                                        <div class="relative">
                                            {{-- Content wrapper with responsive ordering --}}
                                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                                <div class="flex-1">
                                                    {{-- Title + inline pill on desktop --}}
                                                    <div
                                                        class="flex items-center justify-between sm:justify-start gap-2 flex-wrap">
                                                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                                                            {{ $plan->getShortName() }}
                                                        </h3>
                                                        <span
                                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                            {{ $plan->getDaysCount() }} days
                                                        </span>
                                                    </div>
                                                    <p class="mt-2 text-gray-600 dark:text-gray-400">
                                                        {{ $plan->description }}
                                                    </p>
                                                    <button type="button"
                                                        data-modal-target="reading-plan-start-modal"
                                                        data-modal-toggle="reading-plan-start-modal"
                                                        data-reading-plan-start-trigger
                                                        data-plan-slug="{{ $plan->slug }}"
                                                        x-on:click="$dispatch('open-reading-plan-start', { slug: @js($plan->slug) })"
                                                        class="mt-3 inline-flex text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300">
                                                        Start from a different passage
                                                    </button>
                                                </div>
                                                <form hx-post="{{ route('plans.subscribe', $plan) }}"
                                                    hx-target="#page-container" hx-swap="innerHTML"
                                                    @if ($has_active_plan) hx-confirm="Starting this plan will pause your current active plan. Continue?" @endif
                                                    class="order-last sm:order-none w-full sm:w-auto flex-shrink-0">
                                                    @csrf
                                                    <input type="hidden" name="start_day" value="{{ $plan->getFirstDayNumber() }}">
                                                    <button type="submit"
                                                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors shadow-sm">
                                                        Start from Day {{ $plan->getFirstDayNumber() }}
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div
                                class="text-center py-12 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                                <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">No reading plans
                                    available</h3>
                                <p class="mt-2 text-gray-500 dark:text-gray-400">Check back soon for new reading plans.</p>
                            </div>
                        @endforelse
                    </div>

                    @if ($startingPassagePlans !== [])
                        <x-modals.reading-plan-start :plans="$startingPassagePlans" :has-active-plan="$has_active_plan" />
                    @endif
        </x-ui.page-shell>
    @endfragment
@endsection

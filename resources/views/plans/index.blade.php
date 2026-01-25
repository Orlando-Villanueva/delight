@extends('layouts.authenticated')

@section('page-title', 'Reading Plans')
@section('page-subtitle', 'Structured guides for your Bible reading journey')

@section('content')
    @fragment('content')
        <div class="flex-1">
            <div id="main-content">
                <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                    {{-- Page Header --}}
                    <div class="mb-6">
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Reading Plans</h1>
                        <p class="mt-1 text-gray-600 dark:text-gray-400">Structured guides to help you read the Bible
                            consistently</p>
                    </div>

                    <div class="space-y-6">
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
                                                        {{ $plan->name }}
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
                                                            {{ $plan->getDaysCount() }}
                                                        </span>
                                                        <span class="font-medium text-primary-600 dark:text-primary-400">
                                                            {{ $subscription->getProgress() }}% complete
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
                                                        Day {{ $subscription->getDayNumber() }} of {{ $plan->getDaysCount() }}
                                                    </span>
                                                    <span class="font-medium text-primary-600 dark:text-primary-400">
                                                        {{ $subscription->getProgress() }}% complete
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
                                                            {{ $plan->name }}
                                                        </h3>
                                                        <span
                                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                            {{ $plan->getDaysCount() }} days
                                                        </span>
                                                    </div>
                                                    <p class="mt-2 text-gray-600 dark:text-gray-400">
                                                        {{ $plan->description }}
                                                    </p>
                                                </div>
                                                <form hx-post="{{ route('plans.subscribe', $plan) }}"
                                                    hx-target="#page-container" hx-swap="innerHTML"
                                                    @if ($has_active_plan) hx-confirm="Starting this plan will pause your current active plan. Continue?" @endif
                                                    class="order-last sm:order-none w-full sm:w-auto flex-shrink-0">
                                                    @csrf
                                                    <button type="submit"
                                                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors shadow-sm">
                                                        Start Plan
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
                </div>
            </div>
        </div>
    @endfragment
@endsection

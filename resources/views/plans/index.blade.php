@extends('layouts.authenticated')

@section('page-title', 'Reading Plans')
@section('page-subtitle', 'Structured guides for your Bible reading journey')

@section('content')
    @fragment('content')
        <div class="flex-1">
            <div id="main-content">
                <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
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
                                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                                        <div class="flex-1">
                                            <h3
                                                class="text-xl font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                                <svg class="w-5 h-5 text-primary-500" aria-hidden="true"
                                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M12 6.03v13m0-13c-2.819-.831-4.715-1.076-8.029-1.023A.99.99 0 0 0 3 6v11c0 .563.466 1.014 1.03 1.007 3.122-.043 5.018.212 7.97 1.023m0-13c2.819-.831 4.715-1.076 8.029-1.023A.99.99 0 0 1 21 6v11c0 .563-.466 1.014-1.03 1.007-3.122-.043-5.018.212-7.97 1.023" />
                                                </svg>
                                                {{ $plan->name }}
                                            </h3>
                                            <p class="mt-2 text-gray-600 dark:text-gray-400">
                                                {{ $plan->description }}
                                            </p>
                                            <div class="mt-3 flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                                                <span class="flex items-center gap-1">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                    {{ $plan->getDaysCount() }} days
                                                </span>
                                            </div>
                                        </div>

                                        <div class="flex-shrink-0">
                                            @if ($isSubscribed)
                                                <div class="flex flex-col items-end gap-2">
                                                    <span
                                                        class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd"
                                                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                        Subscribed
                                                    </span>
                                                    <a href="{{ route('plans.today') }}" hx-get="{{ route('plans.today') }}"
                                                        hx-target="#page-container" hx-swap="innerHTML" hx-push-url="true"
                                                        class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">
                                                        View today's reading →
                                                    </a>
                                                </div>
                                            @else
                                                <form hx-post="{{ route('plans.subscribe', $plan) }}"
                                                    hx-target="#page-container" hx-swap="innerHTML">
                                                    @csrf
                                                    <button type="submit"
                                                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:focus:ring-offset-gray-800">
                                                        Start Plan
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>

                                    @if ($isSubscribed && $subscription)
                                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                            <div class="flex items-center justify-between text-sm">
                                                <span class="text-gray-600 dark:text-gray-400">
                                                    Day {{ $subscription->getDayNumber() }} of
                                                    {{ $plan->getDaysCount() }}
                                                </span>
                                                <span class="font-medium text-gray-900 dark:text-white">
                                                    {{ $subscription->getProgress() }}% complete
                                                </span>
                                            </div>
                                            <div class="mt-2 w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                                <div class="bg-primary-600 h-2 rounded-full transition-all duration-300"
                                                    style="width: {{ $subscription->getProgress() }}%"></div>
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

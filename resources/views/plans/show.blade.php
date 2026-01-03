@extends('layouts.authenticated')

@section('page-title', $plan->name)
@section('page-subtitle', 'Start your reading journey')

@section('content')
    @fragment('content')
        <div class="flex-1">
            <div id="main-content">
                <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                    <div
                        class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="p-6">
                            {{-- Header --}}
                            <div class="flex items-start gap-4">
                                <div
                                    class="flex-shrink-0 w-12 h-12 rounded-lg bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $plan->name }}</h1>
                                    <p class="mt-1 text-gray-600 dark:text-gray-400">{{ $plan->getDaysCount() }} days</p>
                                </div>
                            </div>

                            {{-- Description --}}
                            <div class="mt-6">
                                <p class="text-gray-700 dark:text-gray-300">{{ $plan->description }}</p>
                            </div>

                            {{-- Subscribe CTA --}}
                            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                                <p class="text-gray-600 dark:text-gray-400 mb-4">
                                    Subscribe to start your reading journey. Your plan will begin today.
                                </p>
                                <form hx-post="{{ route('plans.subscribe', $plan) }}" hx-target="#page-container"
                                    hx-swap="innerHTML">
                                    @csrf
                                    <button type="submit"
                                        class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:focus:ring-offset-gray-800">
                                        Start This Plan
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- Back link --}}
                    <div class="mt-6">
                        <a href="{{ route('plans.index') }}" hx-get="{{ route('plans.index') }}" hx-target="#page-container"
                            hx-swap="innerHTML" hx-push-url="true"
                            class="inline-flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            Back to all plans
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endfragment
@endsection

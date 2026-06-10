@extends('layouts.authenticated')

@section('page-title', 'Reading Plan')
@section('page-subtitle', 'Day ' . $day_number . ' of ' . $total_days)

@section('content')
    @fragment('content')
        <div class="flex-1">
            <div id="main-content">
                <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                    @fragment('reading-list')
                        <div id="reading-list-container">
                                <div class="space-y-4 sm:space-y-6">
                            {{-- Progress Header --}}
                            <div
                                class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                                <div class="flex items-start justify-between gap-4">
                                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ $plan->getShortName() }}
                                    </h2>
                                    <div class="text-right">
                                        <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                                            {{ $progress }}%
                                        </p>
                                        @if ($is_complete)
                                            <p class="text-xs font-medium text-green-600 dark:text-green-400">Tracking complete</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="mb-3 sm:mb-4">
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        <span class="sm:hidden">Day {{ $day_number }}/{{ $total_days }}@if ($subscription->start_day > 1) · tracking from {{ $subscription->start_day }}@endif · {{ $completed_days_count }}/{{ $tracked_days_count }} complete</span>
                                        <span class="hidden sm:inline">Day {{ $day_number }} of {{ $total_days }}@if ($subscription->start_day > 1) · tracking from Day {{ $subscription->start_day }}@endif · {{ $completed_days_count }} of {{ $tracked_days_count }} tracked days complete</span>
                                    </p>
                                    @if ($current_day !== $day_number)
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Current day: {{ $current_day }}
                                        </p>
                                    @endif
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                    <div class="bg-primary-600 h-2 rounded-full transition-all duration-500"
                                        style="width: {{ $progress }}%"></div>
                                </div>
                                <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        @if ($previous_day !== null)
                                            <a href="{{ route('plans.today', ['plan' => $plan, 'day' => $previous_day]) }}"
                                                hx-get="{{ route('plans.today', ['plan' => $plan, 'day' => $previous_day]) }}"
                                                hx-target="#page-container" hx-swap="innerHTML" hx-push-url="true"
                                                class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 dark:text-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 rounded-lg transition-colors">
                                                <span class="sm:hidden">Previous</span>
                                                <span class="hidden sm:inline">Previous Day</span>
                                            </a>
                                        @else
                                            <span
                                                class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-400 bg-gray-100 dark:text-gray-500 dark:bg-gray-700 rounded-lg cursor-not-allowed">
                                                <span class="sm:hidden">Previous</span>
                                                <span class="hidden sm:inline">Previous Day</span>
                                            </span>
                                        @endif
                                        @if ($next_day !== null)
                                            <a href="{{ route('plans.today', ['plan' => $plan, 'day' => $next_day]) }}"
                                                hx-get="{{ route('plans.today', ['plan' => $plan, 'day' => $next_day]) }}"
                                                hx-target="#page-container" hx-swap="innerHTML" hx-push-url="true"
                                                class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 dark:text-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 rounded-lg transition-colors">
                                                <span class="sm:hidden">Next</span>
                                                <span class="hidden sm:inline">Next Day</span>
                                            </a>
                                        @else
                                            <span
                                                class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-400 bg-gray-100 dark:text-gray-500 dark:bg-gray-700 rounded-lg cursor-not-allowed">
                                                <span class="sm:hidden">Next</span>
                                                <span class="hidden sm:inline">Next Day</span>
                                            </span>
                                        @endif
                                    </div>
                                    @if ($current_day !== $day_number)
                                        <a href="{{ route('plans.today', ['plan' => $plan, 'day' => $current_day]) }}"
                                            hx-get="{{ route('plans.today', ['plan' => $plan, 'day' => $current_day]) }}"
                                            hx-target="#page-container" hx-swap="innerHTML" hx-push-url="true"
                                            class="inline-flex items-center text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">
                                            Go to Current Day
                                        </a>
                                    @endif
                                </div>
                            </div>

                            @if ($reading)
                                {{-- Today's Reading Card --}}
                                <div
                                    class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                                    <div class="p-4 sm:p-6">
                                        <div class="flex items-center justify-between gap-3 mb-3 sm:mb-4">
                                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                                Day {{ $day_number }}<span class="hidden sm:inline"> Reading</span>
                                            </h3>
                                            @if ($is_active && !$is_before_tracking && !$reading['all_completed'])
                                                <form hx-post="{{ route('plans.logAll', $plan) }}"
                                                    hx-target="#reading-list-container" hx-swap="outerHTML">
                                                    @csrf
                                                    <input type="hidden" name="day" value="{{ $day_number }}">
                                                    <button type="submit"
                                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-white bg-accent-500 hover:bg-accent-600 rounded-lg transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        <span class="sm:hidden">Complete day</span>
                                                        <span class="hidden sm:inline">Mark day complete</span>
                                                    </button>
                                                </form>
                                            @elseif ($reading['all_completed'])
                                                <span
                                                    class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-green-700 dark:text-green-400 bg-green-100 dark:bg-green-900/30 rounded-lg">
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd"
                                                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                            clip-rule="evenodd" />
                                                    </svg>
                                                    All Complete!
                                                </span>
                                            @endif
                                        </div>

                                        @if ($is_before_tracking)
                                            <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-700/40">
                                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Before tracking</p>
                                                <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">This day is before where you started tracking this plan.</p>
                                            </div>
                                        @endif

                                        @if ($is_active && !$is_before_tracking && $unlinked_today_chapters_count > 0)
                                            <div
                                                class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-blue-200/60 bg-blue-50/50 px-3 py-2 text-blue-900 dark:border-blue-900/30 dark:bg-blue-900/10 dark:text-blue-100">
                                                <p class="text-xs leading-none text-blue-800 dark:text-blue-200">
                                                    Found {{ $unlinked_today_chapters_count }} of
                                                    {{ $unlinked_today_chapters_total }} chapters from today.
                                                </p>
                                                <form hx-post="{{ route('plans.applyTodaysReadings', $plan) }}"
                                                    hx-target="#reading-list-container" hx-swap="outerHTML"
                                                    class="flex items-center">
                                                    @csrf
                                                    <input type="hidden" name="day" value="{{ $day_number }}">
                                                    <button type="submit"
                                                        class="inline-flex items-center text-xs font-medium leading-none text-primary-600 hover:text-primary-500 dark:text-primary-400">
                                                        Apply to this day
                                                    </button>
                                                </form>
                                            </div>
                                        @endif

                                        @if (!$is_active)
                                            <div
                                                class="mb-4 flex flex-col items-center gap-3 rounded-lg border border-gray-200/60 bg-gray-50/50 px-4 py-4 text-gray-900 dark:border-gray-700/60 dark:bg-gray-800/50 dark:text-gray-100 sm:flex-row sm:justify-between sm:gap-2 sm:px-3 sm:py-2">
                                                <p
                                                    class="text-center text-sm leading-relaxed text-gray-700 dark:text-gray-300 sm:text-left sm:leading-none">
                                                    <span class="font-semibold">Plan paused.</span> Resume to continue logging.
                                                </p>
                                                <form hx-post="{{ route('plans.activate', $plan) }}"
                                                    hx-target="#reading-list-container" hx-swap="outerHTML"
                                                    @if ($has_other_active_plan) hx-confirm="This will pause your current active plan. Continue?" @endif
                                                    class="w-full sm:w-auto">
                                                    @csrf
                                                    <button type="submit"
                                                        class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-700 sm:w-auto sm:px-3 sm:py-1.5">
                                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 16 16">
                                                            <path
                                                                d="M10.804 8 5 4.633v6.734zm.792-.696a.802.802 0 0 1 0 1.392l-6.363 3.692C4.713 12.69 4 12.345 4 11.692V4.308c0-.653.713-.998 1.233-.696z" />
                                                        </svg>
                                                        Resume Plan
                                                    </button>
                                                </form>
                                            </div>
                                        @endif

                                        {{-- Chapter List --}}
                                        <div class="space-y-2">
                                            @foreach ($reading['chapters'] as $chapter)
                                                <div
                                                    class="flex items-center justify-between p-3 rounded-lg {{ $chapter['completed'] ? 'bg-green-50 dark:bg-green-900/20' : 'bg-gray-50 dark:bg-gray-700/50' }}">
                                                    <div class="flex items-center gap-3">
                                                        @if ($chapter['completed'])
                                                            <div
                                                                class="flex-shrink-0 w-6 h-6 rounded-full bg-green-500 flex items-center justify-center">
                                                                <svg class="w-4 h-4 text-white" fill="currentColor"
                                                                    viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd"
                                                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                                        clip-rule="evenodd" />
                                                                </svg>
                                                            </div>
                                                        @else
                                                            <div
                                                                class="flex-shrink-0 w-6 h-6 rounded-full border-2 border-gray-300 dark:border-gray-600">
                                                            </div>
                                                        @endif
                                                        <span
                                                            class="{{ $chapter['completed'] ? 'text-green-700 dark:text-green-400' : 'text-gray-900 dark:text-white' }} font-medium">
                                                            {{ $chapter['book_name'] }} {{ $chapter['chapter'] }}
                                                        </span>
                                                    </div>

                                                    @if ($is_active && !$is_before_tracking && !$chapter['completed'])
                                                        <form hx-post="{{ route('plans.logChapter', $plan) }}"
                                                            hx-target="#reading-list-container" hx-swap="outerHTML">
                                                            @csrf
                                                            <input type="hidden" name="day" value="{{ $day_number }}">
                                                            <input type="hidden" name="book_id"
                                                                value="{{ $chapter['book_id'] }}">
                                                            <input type="hidden" name="chapter"
                                                                value="{{ $chapter['chapter'] }}">
                                                            <button type="submit"
                                                                class="inline-flex items-center px-3 py-1 text-xs font-medium text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded-md transition-colors">
                                                                Mark read
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>

                                        {{-- Completion Status --}}
                                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ $reading['completed_count'] }} of {{ $reading['total_count'] }} chapters
                                                completed
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @else
                                {{-- Plan Complete --}}
                                <div
                                    class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                                    <div
                                        class="w-16 h-16 mx-auto mb-4 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                        <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Congratulations!</h3>
                                    <p class="mt-2 text-gray-600 dark:text-gray-400">
                                        You've completed every day since you started tracking this plan.
                                    </p>
                                </div>
                            @endif

                            {{-- Navigation --}}
                            <div class="flex justify-between items-center">
                                <button type="button" hx-get="{{ route('plans.index') }}" hx-target="#page-container"
                                    hx-swap="innerHTML" hx-push-url="true"
                                    class="inline-flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 cursor-pointer">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 19l-7-7 7-7" />
                                    </svg>
                                    All Plans
                                </button>
                                <form hx-delete="{{ route('plans.unsubscribe', $plan) }}" hx-target="#page-container"
                                    hx-swap="innerHTML"
                                    hx-confirm="Are you sure you want to leave this plan? Your progress will be reset.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="inline-flex items-center text-sm font-medium text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400 transition-colors">
                                        Leave Plan
                                    </button>
                                </form>
                            </div>
                            </div>
                        </div>
                    @endfragment
                </div>
            </div>
        </div>
    @endfragment
@endsection

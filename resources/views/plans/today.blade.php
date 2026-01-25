@extends('layouts.authenticated')

@section('page-title', 'Reading Plan')
@section('page-subtitle', 'Day ' . $day_number . ' of ' . $total_days)

@section('content')
    @fragment('content')
        <div class="flex-1">
            <div id="main-content">
                <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                    @fragment('reading-list')
                        <div id="reading-list-container" class="space-y-6">
                            {{-- Progress Header --}}
                            <div
                                class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <div>
                                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                            {{ $plan->name }}
                                        </h2>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            Day {{ $day_number }} of {{ $total_days }}
                                        </p>
                                        @if ($current_day !== $day_number)
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                Current day: {{ $current_day }}
                                            </p>
                                        @endif
                                    </div>
                                    <div class="text-right">
                                        <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                                            {{ $progress }}%
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">complete</p>
                                        @if ($is_complete)
                                            <p class="text-xs font-medium text-green-600 dark:text-green-400">Plan complete</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                    <div class="bg-primary-600 h-2 rounded-full transition-all duration-500"
                                        style="width: {{ $progress }}%"></div>
                                </div>
                                <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        @if ($day_number > 1)
                                            <a href="{{ route('plans.today', ['plan' => $plan, 'day' => $day_number - 1]) }}"
                                                hx-get="{{ route('plans.today', ['plan' => $plan, 'day' => $day_number - 1]) }}"
                                                hx-target="#page-container" hx-swap="innerHTML" hx-push-url="true"
                                                class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 dark:text-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 rounded-lg transition-colors">
                                                Previous Day
                                            </a>
                                        @else
                                            <span
                                                class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-400 bg-gray-100 dark:text-gray-500 dark:bg-gray-700 rounded-lg cursor-not-allowed">
                                                Previous Day
                                            </span>
                                        @endif
                                        @if ($day_number < $total_days)
                                            <a href="{{ route('plans.today', ['plan' => $plan, 'day' => $day_number + 1]) }}"
                                                hx-get="{{ route('plans.today', ['plan' => $plan, 'day' => $day_number + 1]) }}"
                                                hx-target="#page-container" hx-swap="innerHTML" hx-push-url="true"
                                                class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 dark:text-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 rounded-lg transition-colors">
                                                Next Day
                                            </a>
                                        @else
                                            <span
                                                class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-400 bg-gray-100 dark:text-gray-500 dark:bg-gray-700 rounded-lg cursor-not-allowed">
                                                Next Day
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
                                    <div class="p-6">
                                        <div class="flex items-center justify-between mb-4">
                                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                                Day {{ $day_number }} Reading
                                            </h3>
                                            @if (!$reading['all_completed'])
                                                <form hx-post="{{ route('plans.logAll', $plan) }}" hx-target="#reading-list-container"
                                                    hx-swap="outerHTML">
                                                    @csrf
                                                    <input type="hidden" name="day" value="{{ $day_number }}">
                                                    <button type="submit"
                                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-white bg-accent-500 hover:bg-accent-600 rounded-lg transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        Mark day complete
                                                    </button>
                                                </form>
                                            @else
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

                                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                                            {{ $reading['label'] }}
                                        </p>

                                        @if ($unlinked_today_chapters_count > 0)
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

                                                    @if (!$chapter['completed'])
                                                        <form hx-post="{{ route('plans.logChapter', $plan) }}"
                                                            hx-target="#reading-list-container" hx-swap="outerHTML">
                                                            @csrf
                                                            <input type="hidden" name="day" value="{{ $day_number }}">
                                                            <input type="hidden" name="book_id" value="{{ $chapter['book_id'] }}">
                                                            <input type="hidden" name="chapter" value="{{ $chapter['chapter'] }}">
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
                                        <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Congratulations!</h3>
                                    <p class="mt-2 text-gray-600 dark:text-gray-400">
                                        You've completed this reading plan. What an accomplishment!
                                    </p>
                                </div>
                            @endif

                            {{-- Navigation --}}
                            <div class="flex justify-between items-center">
                                <a href="{{ route('plans.index') }}" hx-get="{{ route('plans.index') }}"
                                    hx-target="#page-container" hx-swap="innerHTML" hx-push-url="true"
                                    class="inline-flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                    </svg>
                                    All Plans
                                </a>
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
                    @endfragment
                </div>
            </div>
        </div>
    @endfragment
@endsection

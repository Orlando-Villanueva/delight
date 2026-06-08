@extends('layouts.authenticated')

@section('page-title', 'Choose Starting Passage')
@section('page-subtitle', $plan->name)

@section('content')
    @fragment('content')
        <x-ui.page-shell width="medium" id="main-content">
            <x-ui.page-header
                title="Choose your starting passage"
                subtitle="Find where you are in {{ $plan->name }}, then start tracking from there."
            />

            <div class="space-y-6">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <form method="GET" action="{{ route('plans.start', $plan) }}" hx-get="{{ route('plans.start', $plan) }}"
                        hx-target="#page-container" hx-swap="innerHTML" hx-push-url="true" class="space-y-3">
                        <label for="day" class="block text-sm font-medium text-gray-900 dark:text-white">
                            Starting passage
                        </label>
                        <select id="day" name="day" aria-describedby="starting-passage-help"
                            onchange="this.form.requestSubmit()"
                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @foreach ($days as $day)
                                <option value="{{ $day['day'] }}" @selected($day['day'] === $selected_day)>
                                    {{ $day['label'] }} - Day {{ $day['day'] }}
                                </option>
                            @endforeach
                        </select>
                        <p id="starting-passage-help" class="text-sm text-gray-500 dark:text-gray-400">
                            Preview updates automatically when you choose a passage.
                        </p>
                    </form>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="border-b border-gray-200 p-6 dark:border-gray-700">
                        <p class="text-sm font-medium text-primary-600 dark:text-primary-400">Day {{ $selected_day }} of {{ $total_days }}</p>
                        <h2 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $selected_reading['label'] }}</h2>
                    </div>

                    <div class="space-y-2 p-6">
                        @foreach ($selected_reading['chapters'] as $chapter)
                            <div class="rounded-lg bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900 dark:bg-gray-700/50 dark:text-white">
                                {{ $chapter['book_name'] }} {{ $chapter['chapter'] }}
                            </div>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 p-6 dark:border-gray-700">
                        <div class="flex items-center gap-2">
                            @if ($previous_day)
                                <a href="{{ route('plans.start', ['plan' => $plan, 'day' => $previous_day]) }}"
                                    hx-get="{{ route('plans.start', ['plan' => $plan, 'day' => $previous_day]) }}"
                                    hx-target="#page-container" hx-swap="innerHTML" hx-push-url="true"
                                    class="inline-flex items-center rounded-lg bg-gray-100 px-3 py-2 text-xs font-medium text-gray-700 transition-colors hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                                    Previous passage
                                </a>
                            @endif
                            @if ($next_day)
                                <a href="{{ route('plans.start', ['plan' => $plan, 'day' => $next_day]) }}"
                                    hx-get="{{ route('plans.start', ['plan' => $plan, 'day' => $next_day]) }}"
                                    hx-target="#page-container" hx-swap="innerHTML" hx-push-url="true"
                                    class="inline-flex items-center rounded-lg bg-gray-100 px-3 py-2 text-xs font-medium text-gray-700 transition-colors hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                                    Next passage
                                </a>
                            @endif
                        </div>

                        <form hx-post="{{ route('plans.subscribe', $plan) }}" hx-target="#page-container" hx-swap="innerHTML"
                            class="w-full sm:w-auto"
                            @if ($has_active_plan) hx-confirm="Starting this plan will pause your current active plan. Continue?" @endif>
                            @csrf
                            <input type="hidden" name="start_day" value="{{ $selected_day }}">
                            <button type="submit"
                                class="inline-flex w-full items-center justify-center rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-primary-700 sm:w-auto">
                                Start tracking from Day {{ $selected_day }}
                            </button>
                        </form>
                    </div>
                </div>

                <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900 dark:border-blue-900/40 dark:bg-blue-900/20 dark:text-blue-100">
                    Earlier days will be marked <span class="font-semibold">Before tracking</span>. They will not create reading logs or count toward tracked completion.
                </div>

                <a href="{{ route('plans.index') }}" hx-get="{{ route('plans.index') }}" hx-target="#page-container"
                    hx-swap="innerHTML" hx-push-url="true"
                    class="inline-flex text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                    Back to all plans
                </a>
            </div>
        </x-ui.page-shell>
    @endfragment
@endsection

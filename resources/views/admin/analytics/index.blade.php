@extends('layouts.authenticated')

@section('page-title', 'Analytics')

@section('content')
    @fragment('analytics-content')
        @php
            $onboarding = $metrics['onboarding'];
            $activation = $metrics['activation'];
            $churn = $metrics['churn_recovery'];
            $current = $metrics['current_stats'];
            $insights = $metrics['insights'];

            $statusStyles = [
                'good' => [
                    'badge' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
                    'bar' => 'bg-green-500',
                    'label' => 'On Track',
                ],
                'warn' => [
                    'badge' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
                    'bar' => 'bg-yellow-500',
                    'label' => 'Needs Work',
                ],
                'neutral' => [
                    'badge' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                    'bar' => 'bg-gray-400',
                    'label' => 'No Data',
                ],
            ];

            $onboardingStatus = $statusStyles[$onboarding['status']] ?? $statusStyles['neutral'];
            $activationStatus = $statusStyles[$activation['status']] ?? $statusStyles['neutral'];
            $churnStatus = $statusStyles[$churn['status']] ?? $statusStyles['neutral'];

            $activationProgress =
                $activation['sample_size'] > 0 && $activation['avg_hours'] > 0
                    ? min(100, ($activation['target_hours'] / $activation['avg_hours']) * 100)
                    : 0;

            $churnProgress = $churn['total'] > 0 ? $churn['rate'] : 0;

            $insightDot = [
                'warning' => 'bg-yellow-500',
                'success' => 'bg-green-500',
                'neutral' => 'bg-gray-400',
                'info' => 'bg-blue-500',
            ];
        @endphp

        <div class="w-full">
            <div class="max-w-6xl mx-auto space-y-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Analytics Dashboard</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400">How the app is doing and where to focus next.</p>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Last updated {{ $metrics['generated_at']->format('M j, Y H:i') }}
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div
                        class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Onboarding Completion
                                </p>
                                <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
                                    {{ number_format($onboarding['rate'], 1) }}%
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ number_format($onboarding['completed']) }} of {{ number_format($onboarding['total']) }}
                                    users
                                </p>
                            </div>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $onboardingStatus['badge'] }}">
                                {{ $onboardingStatus['label'] }}
                            </span>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                <span>Target 80%+</span>
                                <span>{{ number_format($onboarding['rate'], 1) }}%</span>
                            </div>
                            <div class="mt-2 h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                <div class="h-2 rounded-full {{ $onboardingStatus['bar'] }}"
                                    style="width: {{ min(100, $onboarding['rate']) }}%"></div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Activation Time
                                </p>
                                <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
                                    @if ($activation['sample_size'] > 0)
                                        {{ number_format($activation['avg_hours'], 1) }}h
                                    @else
                                        —
                                    @endif
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Avg time to first reading ({{ number_format($activation['sample_size']) }} users)
                                </p>
                            </div>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $activationStatus['badge'] }}">
                                {{ $activationStatus['label'] }}
                            </span>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                <span>Target &lt; 24h</span>
                                <span>
                                    @if ($activation['sample_size'] > 0)
                                        {{ number_format($activation['avg_hours'], 1) }}h
                                    @else
                                        —
                                    @endif
                                </span>
                            </div>
                            <div class="mt-2 h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                <div class="h-2 rounded-full {{ $activationStatus['bar'] }}"
                                    style="width: {{ min(100, $activationProgress) }}%"></div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Churn Recovery
                                </p>
                                <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
                                    @if ($churn['total'] > 0)
                                        {{ number_format($churn['rate'], 1) }}%
                                    @else
                                        —
                                    @endif
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ number_format($churn['successes']) }} of {{ number_format($churn['total']) }} recovered
                                </p>
                            </div>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $churnStatus['badge'] }}">
                                {{ $churnStatus['label'] }}
                            </span>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                <span>Target 20%+</span>
                                <span>
                                    @if ($churn['total'] > 0)
                                        {{ number_format($churn['rate'], 1) }}%
                                    @else
                                        —
                                    @endif
                                </span>
                            </div>
                            <div class="mt-2 h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                <div class="h-2 rounded-full {{ $churnStatus['bar'] }}"
                                    style="width: {{ min(100, $churnProgress) }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg p-5">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Focus Next</h2>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Actionable priorities based on current KPIs.
                            </p>
                        </div>
                    </div>
                    <ul class="mt-4 space-y-3">
                        @foreach ($insights as $insight)
                            <li class="flex gap-3">
                                <span class="mt-2 h-2.5 w-2.5 rounded-full {{ $insightDot[$insight['tone']] ?? 'bg-gray-400' }}"></span>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $insight['title'] }}</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $insight['detail'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div
                    class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg p-5">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Current Stats</h2>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Snapshot of overall usage.
                            </p>
                        </div>
                    </div>
                    @php
                        $statCards = [
                            ['label' => 'Total users', 'value' => number_format($current['total_users'])],
                            ['label' => 'Users with readings', 'value' => number_format($current['users_with_readings'])],
                            ['label' => 'Users without readings', 'value' => number_format($current['users_no_readings'])],
                            ['label' => 'Active in last 7 days', 'value' => number_format($current['active_last_7_days'])],
                            ['label' => 'Inactive over 30 days', 'value' => number_format($current['inactive_over_30_days'])],
                            ['label' => 'Users with active plan', 'value' => number_format($current['users_with_active_plan'])],
                            [
                                'label' => 'Avg reading days per user',
                                'value' => number_format($current['avg_reading_days_per_user'], 1),
                            ],
                        ];
                    @endphp
                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($statCards as $stat)
                            <div
                                class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 p-4">
                                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    {{ $stat['label'] }}
                                </p>
                                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                                    {{ $stat['value'] }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endfragment
@endsection

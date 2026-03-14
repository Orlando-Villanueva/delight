<?php

namespace App\Services;

use App\Enums\OnboardingStep;
use App\Models\OnboardingStepEvent;
use App\Models\ReadingLog;
use App\Models\ReadingPlanSubscription;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class AdminAnalyticsService
{
    private const CACHE_KEY_DASHBOARD = 'admin_analytics_stats_v2';

    private const CACHE_TTL_DASHBOARD = 300; // 5 minutes

    private const ONBOARDING_STAGE_PRIORITY = [
        'no_action' => 0,
        'dismissed' => 1,
        'log_flow_reached' => 2,
        'plan_browser_reached' => 3,
        'plan_selected' => 4,
        'reminder_requested' => 5,
    ];

    private const THIRTY_TO_SIXTY_CAMPAIGN_KEY = 'inactive_30_60_followup';

    private const THIRTY_TO_SIXTY_VARIANT_CONTROL = 'current_flow_control';

    private const THIRTY_TO_SIXTY_VARIANT_FOLLOWUP = 'two_touch_followup';

    public function getDashboardMetrics(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY_DASHBOARD);
        }

        return Cache::remember(self::CACHE_KEY_DASHBOARD, self::CACHE_TTL_DASHBOARD, function () {
            $totalUsers = User::count();
            $usersWithReadings = User::has('readingLogs')->count();
            $usersNoReadings = max(0, $totalUsers - $usersWithReadings);

            $activeLast7Days = ReadingLog::where('date_read', '>=', now()->subDays(6)->toDateString())
                ->distinct()
                ->count('user_id');

            $inactiveOver30Days = $this->countInactiveUsers(30);

            $usersWithActivePlan = ReadingPlanSubscription::where('is_active', true)
                ->distinct()
                ->count('user_id');

            $avgReadingDaysPerUser = $this->getAverageReadingDaysPerUser();
            $onboardingFunnel = $this->getOnboardingFunnelMetrics();

            $onboardingRate = $totalUsers > 0
                ? round(($usersWithReadings / $totalUsers) * 100, 1)
                : 0.0;

            $activation = $this->getActivationMetrics();
            $churnRecovery = $this->getChurnRecoveryMetrics();
            $thirtyToSixtyComparison = $this->getThirtyToSixtyComparisonMetrics();

            $weeklyActiveRate = $totalUsers > 0
                ? round(($activeLast7Days / $totalUsers) * 100, 1)
                : 0.0;

            $onboardingStatus = $totalUsers === 0
                ? 'neutral'
                : ($onboardingRate >= 80 ? 'good' : 'warn');

            $activationStatus = $activation['sample_size'] === 0
                ? 'neutral'
                : ($activation['avg_hours'] < 24 ? 'good' : 'warn');

            $churnStatus = $churnRecovery['total'] === 0
                ? 'neutral'
                : ($churnRecovery['rate'] >= 20 ? 'good' : 'warn');

            return [
                'generated_at' => now(),
                'onboarding' => [
                    'completed' => $usersWithReadings,
                    'total' => $totalUsers,
                    'rate' => $onboardingRate,
                    'target' => 80,
                    'status' => $onboardingStatus,
                ],
                'onboarding_funnel' => $onboardingFunnel,
                'activation' => [
                    'avg_hours' => $activation['avg_hours'],
                    'target_hours' => 24,
                    'sample_size' => $activation['sample_size'],
                    'status' => $activationStatus,
                ],
                'churn_recovery' => [
                    'successes' => $churnRecovery['successes'],
                    'total' => $churnRecovery['total'],
                    'rate' => $churnRecovery['rate'],
                    'status' => $churnStatus,
                    'comparisons' => [
                        'inactive_30_60' => $thirtyToSixtyComparison,
                    ],
                ],
                'current_stats' => [
                    'total_users' => $totalUsers,
                    'users_with_readings' => $usersWithReadings,
                    'users_no_readings' => $usersNoReadings,
                    'active_last_7_days' => $activeLast7Days,
                    'inactive_over_30_days' => $inactiveOver30Days,
                    'users_with_active_plan' => $usersWithActivePlan,
                    'avg_reading_days_per_user' => $avgReadingDaysPerUser,
                ],
                'weekly_activity_rate' => $weeklyActiveRate,
                'insights' => $this->buildInsights(
                    totalUsers: $totalUsers,
                    onboardingRate: $onboardingRate,
                    activationAvgHours: $activation['avg_hours'],
                    activationSampleSize: $activation['sample_size'],
                    churnRate: $churnRecovery['rate'],
                    churnTotal: $churnRecovery['total'],
                    weeklyActiveRate: $weeklyActiveRate
                ),
            ];
        });
    }

    /**
     * @return array{
     *     schema_version: string,
     *     snapshot_id: string,
     *     snapshot_generated_at: string,
     *     audit_week: array{timezone: string, iso_week: string, week_start: string, week_end: string},
     *     metrics: array<string, mixed>
     * }
     */
    public function buildSnapshotPayload(bool $fresh): array
    {
        $metrics = $this->getDashboardMetrics($fresh);
        $timezone = (string) config('analytics.snapshot_timezone', 'America/New_York');
        $snapshotGeneratedAt = now($timezone);
        $metricsGeneratedAt = $metrics['generated_at'] ?? null;

        if ($metricsGeneratedAt instanceof DateTimeInterface) {
            $metricsGeneratedAt = $metricsGeneratedAt->format(DATE_ATOM);
        }

        if (! is_string($metricsGeneratedAt) || $metricsGeneratedAt === '') {
            throw new RuntimeException('Analytics snapshot metrics.generated_at must be a non-empty ISO-8601 string.');
        }

        try {
            $metricsTimestamp = Carbon::parse($metricsGeneratedAt)->setTimezone($timezone);
        } catch (Throwable $exception) {
            throw new RuntimeException('Analytics snapshot metrics.generated_at is invalid and cannot be parsed.', previous: $exception);
        }

        $metrics['generated_at'] = $metricsGeneratedAt;

        $weekStart = $metricsTimestamp->copy()->startOfWeek(CarbonInterface::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);
        $isoWeek = $metricsTimestamp->format('o-\WW');
        $snapshotId = sprintf('%s@%s', $isoWeek, $metricsGeneratedAt);

        return [
            'schema_version' => (string) config('analytics.schema_version', 'admin_analytics_weekly_v1'),
            'snapshot_id' => $snapshotId,
            'snapshot_generated_at' => $snapshotGeneratedAt->format(DATE_ATOM),
            'audit_week' => [
                'timezone' => $timezone,
                'iso_week' => $isoWeek,
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
            ],
            'metrics' => $metrics,
        ];
    }

    private function getOnboardingFunnelMetrics(): array
    {
        $eligibleUsers = User::query()
            ->select('id', 'onboarding_dismissed_at', 'onboarding_reminder_requested_at')
            ->whereNull('celebrated_first_reading_at')
            ->whereDoesntHave('readingLogs')
            ->with(['onboardingStepEvents' => function ($query) {
                $query->select('user_id', 'step', 'occurred_at');
            }])
            ->get();

        $currentStageBreakdown = [
            'no_action' => 0,
            OnboardingStep::LogFlowReached->value => 0,
            OnboardingStep::PlanBrowserReached->value => 0,
            OnboardingStep::PlanSelected->value => 0,
            OnboardingStep::ReminderRequested->value => 0,
            OnboardingStep::Dismissed->value => 0,
        ];

        foreach ($eligibleUsers as $user) {
            $stageTimestamps = [];

            foreach ($user->onboardingStepEvents as $event) {
                if ($event->step === OnboardingStep::FirstReadingCompleted) {
                    continue;
                }

                $stageTimestamps[$event->step->value] = $event->occurred_at;
            }

            if ($user->onboarding_dismissed_at !== null) {
                $existingTimestamp = $stageTimestamps[OnboardingStep::Dismissed->value] ?? null;
                if ($existingTimestamp === null || $user->onboarding_dismissed_at->gt($existingTimestamp)) {
                    $stageTimestamps[OnboardingStep::Dismissed->value] = $user->onboarding_dismissed_at;
                }
            }

            if ($user->onboarding_reminder_requested_at !== null) {
                $existingTimestamp = $stageTimestamps[OnboardingStep::ReminderRequested->value] ?? null;
                if ($existingTimestamp === null || $user->onboarding_reminder_requested_at->gt($existingTimestamp)) {
                    $stageTimestamps[OnboardingStep::ReminderRequested->value] = $user->onboarding_reminder_requested_at;
                }
            }

            if ($stageTimestamps === []) {
                $currentStageBreakdown['no_action']++;

                continue;
            }

            $latestStage = collect($stageTimestamps)
                ->sortByDesc(function ($occurredAt, $step) {
                    return sprintf(
                        '%020d-%02d',
                        $occurredAt?->getTimestamp() ?? 0,
                        self::ONBOARDING_STAGE_PRIORITY[$step] ?? 0
                    );
                })
                ->keys()
                ->first();

            $currentStageBreakdown[$latestStage]++;
        }

        $eventStepCounts = OnboardingStepEvent::query()
            ->whereIn('step', [
                OnboardingStep::LogFlowReached->value,
                OnboardingStep::PlanBrowserReached->value,
                OnboardingStep::PlanSelected->value,
            ])
            ->selectRaw('step, count(*) as total')
            ->groupBy('step')
            ->pluck('total', 'step');

        $userStepCounts = User::query()
            ->leftJoin('onboarding_step_events as reminder_events', function ($join) {
                $join->on('users.id', '=', 'reminder_events.user_id')
                    ->where('reminder_events.step', OnboardingStep::ReminderRequested->value);
            })
            ->selectRaw('count(distinct case when users.onboarding_reminder_requested_at is not null or reminder_events.id is not null then users.id end) as reminder_requested_count')
            ->selectRaw('count(case when users.onboarding_dismissed_at is not null then 1 end) as dismissed_count')
            ->selectRaw('count(case when users.celebrated_first_reading_at is not null then 1 end) as completed_count')
            ->first();

        $stepCounts = [
            OnboardingStep::LogFlowReached->value => (int) $eventStepCounts->get(OnboardingStep::LogFlowReached->value, 0),
            OnboardingStep::PlanBrowserReached->value => (int) $eventStepCounts->get(OnboardingStep::PlanBrowserReached->value, 0),
            OnboardingStep::PlanSelected->value => (int) $eventStepCounts->get(OnboardingStep::PlanSelected->value, 0),
            OnboardingStep::ReminderRequested->value => (int) ($userStepCounts->reminder_requested_count ?? 0),
            OnboardingStep::Dismissed->value => (int) ($userStepCounts->dismissed_count ?? 0),
            OnboardingStep::FirstReadingCompleted->value => (int) ($userStepCounts->completed_count ?? 0),
        ];

        return [
            'eligible_users' => $eligibleUsers->count(),
            'current_stage_breakdown' => $currentStageBreakdown,
            'step_counts' => $stepCounts,
        ];
    }

    private function getActivationMetrics(): array
    {
        // Subquery: get each user's first reading time
        $firstReadings = DB::table('reading_logs')
            ->selectRaw('user_id, min(created_at) as first_reading_at')
            ->groupBy('user_id');

        $diffExpr = $this->secondsDiffExpression('u.created_at', 'fr.first_reading_at');

        $result = DB::table('users as u')
            ->joinSub($firstReadings, 'fr', 'u.id', '=', 'fr.user_id')
            ->selectRaw("avg(case when {$diffExpr} < 0 then 0 else {$diffExpr} end) as avg_seconds")
            ->selectRaw('count(*) as sample_size')
            ->first();

        if (! $result || $result->sample_size === 0) {
            return [
                'avg_hours' => 0.0,
                'sample_size' => 0,
            ];
        }

        return [
            'avg_hours' => round(($result->avg_seconds ?? 0) / 3600, 1),
            'sample_size' => (int) $result->sample_size,
        ];
    }

    private function getChurnRecoveryMetrics(): array
    {
        $latestEmails = DB::table('churn_recovery_emails')
            ->selectRaw('user_id, max(sent_at) as last_sent_at')
            ->whereNull('churn_recovery_campaign_id')
            ->whereNull('deleted_at')
            ->groupBy('user_id');

        $total = DB::query()
            ->fromSub($latestEmails, 'latest')
            ->count();

        if ($total === 0) {
            return [
                'successes' => 0,
                'total' => 0,
                'rate' => 0.0,
            ];
        }

        $dateAddExpr = $this->dateAddExpression('latest.last_sent_at', 7);

        $successes = DB::query()
            ->fromSub($latestEmails, 'latest')
            ->join('reading_logs as rl', 'rl.user_id', '=', 'latest.user_id')
            ->whereColumn('rl.created_at', '>=', 'latest.last_sent_at')
            ->whereRaw("rl.created_at <= {$dateAddExpr}")
            ->distinct()
            ->count('latest.user_id');

        $rate = round(($successes / $total) * 100, 1);

        return [
            'successes' => $successes,
            'total' => $total,
            'rate' => $rate,
        ];
    }

    private function getThirtyToSixtyComparisonMetrics(): array
    {
        $campaigns = DB::table('churn_recovery_campaigns as crc')
            ->where('crc.campaign_key', self::THIRTY_TO_SIXTY_CAMPAIGN_KEY)
            ->whereNull('crc.deleted_at');

        $control = $this->getThirtyToSixtyVariantMetrics(clone $campaigns, self::THIRTY_TO_SIXTY_VARIANT_CONTROL);
        $followup = $this->getThirtyToSixtyVariantMetrics(clone $campaigns, self::THIRTY_TO_SIXTY_VARIANT_FOLLOWUP);

        return [
            'control' => $control,
            'followup' => $followup,
            'lift' => round($followup['rate'] - $control['rate'], 1),
        ];
    }

    private function getThirtyToSixtyVariantMetrics(Builder $campaigns, string $variant): array
    {
        $variantCampaigns = (clone $campaigns)
            ->where('crc.variant', $variant);

        $total = (clone $variantCampaigns)->count();

        if ($total === 0) {
            return [
                'variant' => $variant,
                'successes' => 0,
                'total' => 0,
                'rate' => 0.0,
            ];
        }

        $successes = DB::query()
            ->fromSub(
                $variantCampaigns->select('crc.id', 'crc.started_at', 'crc.observed_until', 'crc.user_id'),
                'campaigns'
            )
            ->join('reading_logs as rl', 'rl.user_id', '=', 'campaigns.user_id')
            ->whereColumn('rl.created_at', '>=', 'campaigns.started_at')
            ->whereColumn('rl.created_at', '<=', 'campaigns.observed_until')
            ->distinct()
            ->count('campaigns.id');

        return [
            'variant' => $variant,
            'successes' => $successes,
            'total' => $total,
            'rate' => round(($successes / $total) * 100, 1),
        ];
    }

    private function countInactiveUsers(int $days): int
    {
        $cutoff = now()->subDays($days)->toDateString();

        $lastReadSubquery = DB::table('reading_logs')
            ->selectRaw('user_id, max(date_read) as last_read_date')
            ->groupBy('user_id');

        return DB::table('users')
            ->leftJoinSub($lastReadSubquery, 'last_reads', 'users.id', '=', 'last_reads.user_id')
            ->where(function ($query) use ($cutoff) {
                $query->whereNull('last_reads.last_read_date')
                    ->orWhere('last_reads.last_read_date', '<', $cutoff);
            })
            ->count();
    }

    private function getAverageReadingDaysPerUser(): float
    {
        $daysPerUser = DB::table('reading_logs')
            ->selectRaw('user_id, count(distinct date_read) as days')
            ->groupBy('user_id')
            ->pluck('days');

        if ($daysPerUser->isEmpty()) {
            return 0.0;
        }

        return round($daysPerUser->avg(), 1);
    }

    private function buildInsights(
        int $totalUsers,
        float $onboardingRate,
        float $activationAvgHours,
        int $activationSampleSize,
        float $churnRate,
        int $churnTotal,
        float $weeklyActiveRate
    ): array {
        if ($totalUsers === 0) {
            return [
                [
                    'tone' => 'neutral',
                    'title' => 'No user data yet',
                    'detail' => 'Analytics will populate once users sign up and begin logging readings.',
                ],
            ];
        }

        $insights = [];

        if ($onboardingRate < 80) {
            $insights[] = [
                'tone' => 'warning',
                'title' => 'Onboarding drop-off',
                'detail' => sprintf(
                    'Only %s%% of users have logged their first reading. Strengthen the onboarding flow and first-reading prompts.',
                    number_format($onboardingRate, 1)
                ),
            ];
        }

        if ($activationSampleSize > 0 && $activationAvgHours >= 24) {
            $insights[] = [
                'tone' => 'warning',
                'title' => 'Activation is slow',
                'detail' => sprintf(
                    'Average time to first reading is %sh (target <24h). Reduce friction between signup and the first log.',
                    number_format($activationAvgHours, 1)
                ),
            ];
        }

        if ($churnTotal > 0 && $churnRate < 20) {
            $insights[] = [
                'tone' => 'warning',
                'title' => 'Churn recovery is weak',
                'detail' => sprintf(
                    'Recovery success is %s%% (target 20%%+). Improve re-engagement messaging or timing.',
                    number_format($churnRate, 1)
                ),
            ];
        }

        if ($weeklyActiveRate < 15) {
            $insights[] = [
                'tone' => 'warning',
                'title' => 'Weekly activity is low',
                'detail' => sprintf(
                    'Only %s%% of users were active in the last 7 days. Consider reminders or habit cues.',
                    number_format($weeklyActiveRate, 1)
                ),
            ];
        }

        if (empty($insights)) {
            $insights[] = [
                'tone' => 'success',
                'title' => 'Metrics are healthy',
                'detail' => 'Key KPIs are within targets. Keep shipping improvements and monitor for regressions.',
            ];
        }

        return array_slice($insights, 0, 4);
    }

    private function dateAddExpression(string $column, int $days): string
    {
        $driver = DB::getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => "DATE_ADD($column, INTERVAL $days DAY)",
            'pgsql' => "$column + interval '{$days} days'",
            'sqlite' => "datetime($column, '+$days days')",
            default => "$column + interval '{$days} days'",
        };
    }

    private function secondsDiffExpression(string $startColumn, string $endColumn): string
    {
        $driver = DB::getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => "TIMESTAMPDIFF(SECOND, {$startColumn}, {$endColumn})",
            'pgsql' => "EXTRACT(EPOCH FROM ({$endColumn} - {$startColumn}))",
            'sqlite' => "(julianday({$endColumn}) - julianday({$startColumn})) * 86400",
            default => "EXTRACT(EPOCH FROM ({$endColumn} - {$startColumn}))",
        };
    }
}

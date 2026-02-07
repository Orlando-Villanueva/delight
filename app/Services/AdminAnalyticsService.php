<?php

namespace App\Services;

use App\Models\ReadingLog;
use App\Models\ReadingPlanSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsService
{
    private const CACHE_TTL_DASHBOARD = 300; // 5 minutes

    public function getDashboardMetrics(): array
    {
        return Cache::remember('admin_analytics_stats_v1', self::CACHE_TTL_DASHBOARD, function () {
            $totalUsers = User::count();
            $usersWithReadings = User::has('readingLogs')->count();
            $usersNoReadings = max(0, $totalUsers - $usersWithReadings);

            $activeLast7Days = ReadingLog::where('date_read', '>=', now()->subDays(7)->toDateString())
                ->distinct()
                ->count('user_id');

            $inactiveOver30Days = $this->countInactiveUsers(30);

            $usersWithActivePlan = ReadingPlanSubscription::where('is_active', true)
                ->distinct()
                ->count('user_id');

            $avgReadingDaysPerUser = $this->getAverageReadingDaysPerUser();

            $onboardingRate = $totalUsers > 0
                ? round(($usersWithReadings / $totalUsers) * 100, 1)
                : 0.0;

            $activation = $this->getActivationMetrics();
            $churnRecovery = $this->getChurnRecoveryMetrics();

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

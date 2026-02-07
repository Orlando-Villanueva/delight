<?php

use App\Models\ChurnRecoveryEmail;
use App\Models\ReadingLog;
use App\Models\ReadingPlan;
use App\Models\ReadingPlanSubscription;
use App\Models\User;
use App\Services\AdminAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->service = app(AdminAnalyticsService::class);
});

afterEach(function () {
    Cache::flush();
    Carbon::setTestNow();
});

describe('Empty State', function () {
    it('can return_zero_metrics_when_no_users_exist', function () {
        $metrics = $this->service->getDashboardMetrics();

        $this->assertSame(0, $metrics['current_stats']['total_users']);
        $this->assertSame(0, $metrics['current_stats']['users_with_readings']);
        $this->assertSame(0, $metrics['current_stats']['users_no_readings']);
        $this->assertSame(0, $metrics['current_stats']['active_last_7_days']);
        $this->assertSame(0.0, $metrics['onboarding']['rate']);
        $this->assertSame('neutral', $metrics['onboarding']['status']);
    });

    it('can return_neutral_insights_when_no_users_exist', function () {
        $metrics = $this->service->getDashboardMetrics();

        $this->assertCount(1, $metrics['insights']);
        $this->assertSame('neutral', $metrics['insights'][0]['tone']);
        $this->assertSame('No user data yet', $metrics['insights'][0]['title']);
    });
});

describe('Onboarding Metrics', function () {
    it('can calculate_onboarding_rate_correctly', function () {
        Carbon::setTestNow('2026-02-10 12:00:00');

        // 10 users total, 8 have readings
        $usersWithReadings = User::factory()->count(8)->create();
        User::factory()->count(2)->create(); // No readings

        foreach ($usersWithReadings as $index => $user) {
            ReadingLog::factory()->for($user)->create([
                'date_read' => now()->subDays(1 + ($index % 30))->toDateString(),
            ]);
        }

        $metrics = $this->service->getDashboardMetrics();

        $this->assertSame(10, $metrics['onboarding']['total']);
        $this->assertSame(8, $metrics['onboarding']['completed']);
        $this->assertSame(80.0, $metrics['onboarding']['rate']);
        $this->assertSame('good', $metrics['onboarding']['status']);
    });

    it('can warn_when_onboarding_status_is_below_80_percent', function () {
        Carbon::setTestNow('2026-02-10 12:00:00');

        // 10 users total, only 7 have readings (70%)
        $usersWithReadings = User::factory()->count(7)->create();
        User::factory()->count(3)->create();

        foreach ($usersWithReadings as $user) {
            ReadingLog::factory()->for($user)->create([
                'date_read' => now()->subDay()->toDateString(),
            ]);
        }

        $metrics = $this->service->getDashboardMetrics();

        $this->assertSame(70.0, $metrics['onboarding']['rate']);
        $this->assertSame('warn', $metrics['onboarding']['status']);
    });
});

describe('Activation Metrics', function () {
    it('can calculate_activation_time_correctly', function () {
        Carbon::setTestNow('2026-02-10 12:00:00');

        // User A: created 12 hours ago, first reading 6 hours ago = 6 hour activation
        $userA = User::factory()->create([
            'created_at' => now()->subHours(12),
        ]);
        ReadingLog::factory()->for($userA)->create([
            'date_read' => now()->toDateString(),
            'created_at' => now()->subHours(6),
        ]);

        // User B: created 24 hours ago, first reading 12 hours ago = 12 hour activation
        $userB = User::factory()->create([
            'created_at' => now()->subHours(24),
        ]);
        ReadingLog::factory()->for($userB)->create([
            'date_read' => now()->toDateString(),
            'created_at' => now()->subHours(12),
        ]);

        // Average: (6 + 12) / 2 = 9 hours
        $metrics = $this->service->getDashboardMetrics();

        $this->assertSame(9.0, $metrics['activation']['avg_hours']);
        $this->assertSame(2, $metrics['activation']['sample_size']);
        $this->assertSame('good', $metrics['activation']['status']); // < 24h is good
    });

    it('can warn_when_activation_status_is_over_24_hours', function () {
        Carbon::setTestNow('2026-02-10 12:00:00');

        // User took 48 hours to first reading
        $user = User::factory()->create([
            'created_at' => now()->subHours(72),
        ]);
        ReadingLog::factory()->for($user)->create([
            'date_read' => now()->subDay()->toDateString(),
            'created_at' => now()->subHours(24),
        ]);

        $metrics = $this->service->getDashboardMetrics();

        $this->assertSame(48.0, $metrics['activation']['avg_hours']);
        $this->assertSame('warn', $metrics['activation']['status']);
    });

    it('can set_activation_status_to_neutral_when_no_sample', function () {
        User::factory()->create(); // User without any readings

        $metrics = $this->service->getDashboardMetrics();

        $this->assertSame(0.0, $metrics['activation']['avg_hours']);
        $this->assertSame(0, $metrics['activation']['sample_size']);
        $this->assertSame('neutral', $metrics['activation']['status']);
    });
});

describe('Churn Recovery Metrics', function () {
    it('can calculate_churn_recovery_rate_correctly', function () {
        Carbon::setTestNow('2026-02-10 12:00:00');

        // User A: received email 5 days ago, logged reading 3 days ago (within 7 days) = SUCCESS
        $userA = User::factory()->create();
        ChurnRecoveryEmail::create([
            'user_id' => $userA->id,
            'email_number' => 1,
            'sent_at' => now()->subDays(5),
        ]);
        ReadingLog::factory()->for($userA)->create([
            'date_read' => now()->subDays(3)->toDateString(),
            'created_at' => now()->subDays(3),
        ]);

        // User B: received email 10 days ago, logged reading 2 days ago (outside 7-day window) = FAIL
        $userB = User::factory()->create();
        ChurnRecoveryEmail::create([
            'user_id' => $userB->id,
            'email_number' => 1,
            'sent_at' => now()->subDays(10),
        ]);
        ReadingLog::factory()->for($userB)->create([
            'date_read' => now()->subDays(2)->toDateString(),
            'created_at' => now()->subDays(2),
        ]);

        $metrics = $this->service->getDashboardMetrics();

        $this->assertSame(2, $metrics['churn_recovery']['total']);
        $this->assertSame(1, $metrics['churn_recovery']['successes']);
        $this->assertSame(50.0, $metrics['churn_recovery']['rate']);
    });

    it('can set_churn_recovery_status_to_good_at_20_percent_or_above', function () {
        Carbon::setTestNow('2026-02-10 12:00:00');

        // Create 5 users with churn emails, 1 recovered (20%)
        for ($i = 0; $i < 5; $i++) {
            $user = User::factory()->create();
            ChurnRecoveryEmail::create([
                'user_id' => $user->id,
                'email_number' => 1,
                'sent_at' => now()->subDays(5),
            ]);

            if ($i === 0) {
                // Only first user recovered
                ReadingLog::factory()->for($user)->create([
                    'date_read' => now()->subDays(3)->toDateString(),
                    'created_at' => now()->subDays(3),
                ]);
            }
        }

        $metrics = $this->service->getDashboardMetrics();

        $this->assertSame(20.0, $metrics['churn_recovery']['rate']);
        $this->assertSame('good', $metrics['churn_recovery']['status']);
    });

    it('can warn_when_churn_recovery_status_is_below_20_percent', function () {
        Carbon::setTestNow('2026-02-10 12:00:00');

        // Create 10 users with churn emails, only 1 recovered (10%)
        for ($i = 0; $i < 10; $i++) {
            $user = User::factory()->create();
            ChurnRecoveryEmail::create([
                'user_id' => $user->id,
                'email_number' => 1,
                'sent_at' => now()->subDays(5),
            ]);

            if ($i === 0) {
                ReadingLog::factory()->for($user)->create([
                    'date_read' => now()->subDays(3)->toDateString(),
                    'created_at' => now()->subDays(3),
                ]);
            }
        }

        $metrics = $this->service->getDashboardMetrics();

        $this->assertSame(10.0, $metrics['churn_recovery']['rate']);
        $this->assertSame('warn', $metrics['churn_recovery']['status']);
    });

    it('can set_churn_recovery_status_to_neutral_when_no_emails_sent', function () {
        User::factory()->create();

        $metrics = $this->service->getDashboardMetrics();

        $this->assertSame(0, $metrics['churn_recovery']['total']);
        $this->assertSame('neutral', $metrics['churn_recovery']['status']);
    });

    it('can ignore_soft_deleted_emails_for_churn_recovery', function () {
        Carbon::setTestNow('2026-02-10 12:00:00');

        $user = User::factory()->create();
        $email = ChurnRecoveryEmail::create([
            'user_id' => $user->id,
            'email_number' => 1,
            'sent_at' => now()->subDays(5),
        ]);
        $email->delete(); // Proper soft delete

        $metrics = $this->service->getDashboardMetrics();

        $this->assertSame(0, $metrics['churn_recovery']['total']);
    });
});

describe('Activity Metrics', function () {
    it('can count_active_users_in_last_7_days', function () {
        Carbon::setTestNow('2026-02-10 12:00:00');

        // 3 users active in last 7 days
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create();
            ReadingLog::factory()->for($user)->create([
                'date_read' => now()->subDays(1 + $i)->toDateString(),
            ]);
        }

        // 2 users active more than 7 days ago
        for ($i = 0; $i < 2; $i++) {
            $user = User::factory()->create();
            ReadingLog::factory()->for($user)->create([
                'date_read' => now()->subDays(10 + $i)->toDateString(),
            ]);
        }

        $metrics = $this->service->getDashboardMetrics();

        $this->assertSame(3, $metrics['current_stats']['active_last_7_days']);
    });

    it('can count_inactive_users_over_30_days', function () {
        Carbon::setTestNow('2026-02-10 12:00:00');

        // 2 users inactive (no readings ever)
        User::factory()->count(2)->create();

        // 1 user with reading 35 days ago (inactive)
        $inactiveUser = User::factory()->create();
        ReadingLog::factory()->for($inactiveUser)->create([
            'date_read' => now()->subDays(35)->toDateString(),
        ]);

        // 1 user with reading 5 days ago (active)
        $activeUser = User::factory()->create();
        ReadingLog::factory()->for($activeUser)->create([
            'date_read' => now()->subDays(5)->toDateString(),
        ]);

        $metrics = $this->service->getDashboardMetrics();

        $this->assertSame(3, $metrics['current_stats']['inactive_over_30_days']);
    });

    it('can calculate_average_reading_days_per_user', function () {
        Carbon::setTestNow('2026-02-10 12:00:00');

        // User A: 5 unique reading days
        $userA = User::factory()->create();
        for ($i = 1; $i <= 5; $i++) {
            ReadingLog::factory()->for($userA)->create([
                'date_read' => now()->subDays($i)->toDateString(),
            ]);
        }

        // User B: 3 unique reading days
        $userB = User::factory()->create();
        for ($i = 1; $i <= 3; $i++) {
            ReadingLog::factory()->for($userB)->create([
                'date_read' => now()->subDays($i)->toDateString(),
            ]);
        }

        // Average: (5 + 3) / 2 = 4.0
        $metrics = $this->service->getDashboardMetrics();

        $this->assertSame(4.0, $metrics['current_stats']['avg_reading_days_per_user']);
    });

    it('can count_users_with_active_reading_plan', function () {
        $plan = ReadingPlan::factory()->create();

        // 2 users with active subscriptions
        $activeUsers = User::factory()->count(2)->create();
        foreach ($activeUsers as $user) {
            ReadingPlanSubscription::factory()->for($user)->for($plan)->create([
                'is_active' => true,
            ]);
        }

        // 1 user with inactive subscription
        $inactiveUser = User::factory()->create();
        ReadingPlanSubscription::factory()->for($inactiveUser)->for($plan)->create([
            'is_active' => false,
        ]);

        $metrics = $this->service->getDashboardMetrics();

        $this->assertSame(2, $metrics['current_stats']['users_with_active_plan']);
    });
});

describe('Insights', function () {
    it('can generate_onboarding_dropoff_insight_when_rate_below_80', function () {
        Carbon::setTestNow('2026-02-10 12:00:00');

        // 50% onboarding rate
        $userWithReading = User::factory()->create();
        ReadingLog::factory()->for($userWithReading)->create([
            'date_read' => now()->subDay()->toDateString(),
        ]);
        User::factory()->create(); // No reading

        $metrics = $this->service->getDashboardMetrics();

        $onboardingInsight = collect($metrics['insights'])->firstWhere('title', 'Onboarding drop-off');
        $this->assertNotNull($onboardingInsight);
        $this->assertSame('warning', $onboardingInsight['tone']);
    });

    it('can generate_slow_activation_insight_when_over_24_hours', function () {
        Carbon::setTestNow('2026-02-10 12:00:00');

        $user = User::factory()->create([
            'created_at' => now()->subHours(72),
        ]);
        ReadingLog::factory()->for($user)->create([
            'date_read' => now()->subDay()->toDateString(),
            'created_at' => now()->subHours(24),
        ]);

        $metrics = $this->service->getDashboardMetrics();

        $activationInsight = collect($metrics['insights'])->firstWhere('title', 'Activation is slow');
        $this->assertNotNull($activationInsight);
        $this->assertSame('warning', $activationInsight['tone']);
    });

    it('can generate_weak_churn_recovery_insight_below_20_percent', function () {
        Carbon::setTestNow('2026-02-10 12:00:00');

        // 10% recovery rate
        for ($i = 0; $i < 10; $i++) {
            $user = User::factory()->create();
            ChurnRecoveryEmail::create([
                'user_id' => $user->id,
                'email_number' => 1,
                'sent_at' => now()->subDays(5),
            ]);

            if ($i === 0) {
                ReadingLog::factory()->for($user)->create([
                    'date_read' => now()->subDays(3)->toDateString(),
                    'created_at' => now()->subDays(3),
                ]);
            }
        }

        $metrics = $this->service->getDashboardMetrics();

        $churnInsight = collect($metrics['insights'])->firstWhere('title', 'Churn recovery is weak');
        $this->assertNotNull($churnInsight);
        $this->assertSame('warning', $churnInsight['tone']);
    });

    it('can generate_low_weekly_activity_insight_below_15_percent', function () {
        Carbon::setTestNow('2026-02-10 12:00:00');

        // 10 users, only 1 active this week (10%)
        User::factory()->count(9)->create();
        $activeUser = User::factory()->create();
        ReadingLog::factory()->for($activeUser)->create([
            'date_read' => now()->subDays(2)->toDateString(),
        ]);

        $metrics = $this->service->getDashboardMetrics();

        $activityInsight = collect($metrics['insights'])->firstWhere('title', 'Weekly activity is low');
        $this->assertNotNull($activityInsight);
        $this->assertSame('warning', $activityInsight['tone']);
    });

    it('can generate_healthy_metrics_insight_when_all_kpis_good', function () {
        Carbon::setTestNow('2026-02-10 12:00:00');

        // Create scenario where all KPIs are good:
        // - 100% onboarding (all users have readings)
        // - < 24h activation
        // - >= 20% churn recovery
        // - >= 15% weekly activity

        $users = User::factory()->count(5)->create([
            'created_at' => now()->subHours(2),
        ]);

        foreach ($users as $user) {
            ReadingLog::factory()->for($user)->create([
                'date_read' => now()->subDay()->toDateString(),
                'created_at' => now()->subHours(1),
            ]);

            ChurnRecoveryEmail::create([
                'user_id' => $user->id,
                'email_number' => 1,
                'sent_at' => now()->subDays(3),
            ]);
        }

        $metrics = $this->service->getDashboardMetrics();

        $healthyInsight = collect($metrics['insights'])->firstWhere('title', 'Metrics are healthy');
        $this->assertNotNull($healthyInsight);
        $this->assertSame('success', $healthyInsight['tone']);
    });

    it('can limit_insights_to_maximum_of_4', function () {
        Carbon::setTestNow('2026-02-10 12:00:00');

        // Create scenario triggering all 4 warnings
        User::factory()->count(10)->create([
            'created_at' => now()->subDays(5),
        ]);

        // 1 user with readings (10% onboarding) + slow activation
        $user = User::factory()->create([
            'created_at' => now()->subHours(72),
        ]);
        ReadingLog::factory()->for($user)->create([
            'date_read' => now()->subDays(10)->toDateString(),
            'created_at' => now()->subHours(24),
        ]);

        // 10% churn recovery
        for ($i = 0; $i < 10; $i++) {
            $churnUser = User::factory()->create();
            ChurnRecoveryEmail::create([
                'user_id' => $churnUser->id,
                'email_number' => 1,
                'sent_at' => now()->subDays(5),
            ]);
        }

        $metrics = $this->service->getDashboardMetrics();

        $this->assertCount(4, $metrics['insights']);
    });
});

describe('Caching', function () {
    it('can cache_dashboard_metrics', function () {
        User::factory()->create();

        // First call populates cache
        $metrics1 = $this->service->getDashboardMetrics();

        // Add another user
        User::factory()->create();

        // Second call returns cached data
        $metrics2 = $this->service->getDashboardMetrics();

        $this->assertSame(1, $metrics2['current_stats']['total_users']);
        $this->assertSame($metrics1['generated_at']->timestamp, $metrics2['generated_at']->timestamp);
    });

    it('can verify_cache_key_is_versioned', function () {
        User::factory()->create();
        $this->service->getDashboardMetrics();

        $this->assertTrue(Cache::has('admin_analytics_stats_v1'));
    });
});

describe('Weekly Activity Rate', function () {
    it('can calculate_weekly_activity_rate_correctly', function () {
        Carbon::setTestNow('2026-02-10 12:00:00');

        // 4 users total, 2 active in last 7 days = 50%
        User::factory()->count(2)->create();
        $activeUsers = User::factory()->count(2)->create();

        foreach ($activeUsers as $user) {
            ReadingLog::factory()->for($user)->create([
                'date_read' => now()->subDays(3)->toDateString(),
            ]);
        }

        $metrics = $this->service->getDashboardMetrics();

        $this->assertSame(50.0, $metrics['weekly_activity_rate']);
    });
});

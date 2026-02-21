<?php

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config([
        'mail.admin_address' => 'admin@example.com',
        'analytics.export_token' => 'test-analytics-token',
        'analytics.snapshot_timezone' => 'America/New_York',
        'analytics.schema_version' => 'admin_analytics_weekly_v1',
    ]);

    Cache::flush();
});

afterEach(function () {
    Carbon::setTestNow();
    Cache::flush();
});

it('can return analytics snapshot for admins without token', function () {
    $admin = User::factory()->create([
        'email' => 'admin@example.com',
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.analytics.snapshot'));

    $response
        ->assertOk()
        ->assertJsonPath('schema_version', 'admin_analytics_weekly_v1')
        ->assertJsonPath('audit_week.timezone', 'America/New_York');
});

it('can return analytics snapshot for token-authenticated guests', function () {
    Carbon::setTestNow(Carbon::create(2026, 2, 21, 9, 0, 0, 'America/New_York'));

    $response = $this
        ->withHeader('X-Analytics-Token', 'test-analytics-token')
        ->getJson(route('admin.analytics.snapshot'));

    $response
        ->assertOk()
        ->assertJsonPath('schema_version', 'admin_analytics_weekly_v1')
        ->assertJsonPath('audit_week.timezone', 'America/New_York')
        ->assertJsonPath('audit_week.iso_week', '2026-W08')
        ->assertJsonPath('audit_week.week_start', '2026-02-16')
        ->assertJsonPath('audit_week.week_end', '2026-02-22')
        ->assertJsonStructure([
            'schema_version',
            'snapshot_generated_at',
            'audit_week' => [
                'timezone',
                'iso_week',
                'week_start',
                'week_end',
            ],
            'metrics' => [
                'generated_at',
                'onboarding' => ['completed', 'total', 'rate', 'target', 'status'],
                'activation' => ['avg_hours', 'target_hours', 'sample_size', 'status'],
                'churn_recovery' => ['successes', 'total', 'rate', 'status'],
                'current_stats' => [
                    'total_users',
                    'users_with_readings',
                    'users_no_readings',
                    'active_last_7_days',
                    'inactive_over_30_days',
                    'users_with_active_plan',
                    'avg_reading_days_per_user',
                ],
                'weekly_activity_rate',
                'insights',
            ],
        ]);
});

it('forbids requests without admin auth or valid token', function () {
    $this->getJson(route('admin.analytics.snapshot'))
        ->assertForbidden();

    $this
        ->withHeader('X-Analytics-Token', 'invalid-token')
        ->getJson(route('admin.analytics.snapshot'))
        ->assertForbidden();
});

it('can bypass cache when fresh equals one', function () {
    Carbon::setTestNow(Carbon::create(2026, 2, 21, 8, 0, 0, 'America/New_York'));

    $first = $this
        ->withHeader('X-Analytics-Token', 'test-analytics-token')
        ->getJson(route('admin.analytics.snapshot'));

    Carbon::setTestNow(Carbon::create(2026, 2, 21, 8, 4, 0, 'America/New_York'));

    $second = $this
        ->withHeader('X-Analytics-Token', 'test-analytics-token')
        ->getJson(route('admin.analytics.snapshot'));

    Carbon::setTestNow(Carbon::create(2026, 2, 21, 8, 6, 0, 'America/New_York'));

    $fresh = $this
        ->withHeader('X-Analytics-Token', 'test-analytics-token')
        ->getJson(route('admin.analytics.snapshot', ['fresh' => 1]));

    expect($first->json('metrics.generated_at'))->toBe($second->json('metrics.generated_at'));
    expect($fresh->json('metrics.generated_at'))->not->toBe($second->json('metrics.generated_at'));
});

it('rate limits token requests to sixty requests per minute', function () {
    for ($i = 0; $i < 60; $i++) {
        $this->withHeader('X-Analytics-Token', 'test-analytics-token')
            ->getJson(route('admin.analytics.snapshot'))
            ->assertOk();
    }

    $this->withHeader('X-Analytics-Token', 'test-analytics-token')
        ->getJson(route('admin.analytics.snapshot'))
        ->assertTooManyRequests();
});

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

it('can return analytics snapshot for bearer token-authenticated guests', function () {
    Carbon::setTestNow(Carbon::create(2026, 2, 21, 9, 0, 0, 'America/New_York'));

    $response = $this
        ->withHeader('Authorization', 'Bearer test-analytics-token')
        ->getJson(route('admin.analytics.snapshot'));

    $metricsGeneratedAt = $response->json('metrics.generated_at');

    $response
        ->assertOk()
        ->assertJsonPath('schema_version', 'admin_analytics_weekly_v1')
        ->assertJsonPath('audit_week.timezone', 'America/New_York')
        ->assertJsonPath('audit_week.iso_week', '2026-W08')
        ->assertJsonPath('audit_week.week_start', '2026-02-16')
        ->assertJsonPath('audit_week.week_end', '2026-02-22')
        ->assertHeader('X-Analytics-Snapshot-Id', '2026-W08@'.$metricsGeneratedAt)
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
                'onboarding',
                'activation',
                'churn_recovery',
                'current_stats',
                'weekly_activity_rate',
                'insights',
            ],
        ]);
});

it('forbids requests without admin auth or valid token', function () {
    $this->getJson(route('admin.analytics.snapshot'))
        ->assertForbidden();

    $this
        ->withHeader('Authorization', 'Bearer invalid-token')
        ->getJson(route('admin.analytics.snapshot'))
        ->assertForbidden();
});

it('returns validation error when token-authenticated caller requests fresh snapshot', function () {
    $response = $this
        ->withHeader('Authorization', 'Bearer test-analytics-token')
        ->getJson(route('admin.analytics.snapshot', ['fresh' => 1]));

    $response
        ->assertUnprocessable()
        ->assertExactJson([
            'error' => [
                'code' => 'fresh_not_allowed_for_token',
                'message' => 'Query parameter fresh=1 is not allowed for token-authenticated callers.',
            ],
        ]);
});

it('keeps token snapshot id stable while cached metrics are unchanged', function () {
    Carbon::setTestNow(Carbon::create(2026, 2, 21, 9, 0, 0, 'America/New_York'));

    $first = $this
        ->withHeader('Authorization', 'Bearer test-analytics-token')
        ->getJson(route('admin.analytics.snapshot'));

    Carbon::setTestNow(Carbon::create(2026, 2, 21, 9, 4, 0, 'America/New_York'));

    $second = $this
        ->withHeader('Authorization', 'Bearer test-analytics-token')
        ->getJson(route('admin.analytics.snapshot'));

    $firstSnapshotId = $first->baseResponse->headers->get('X-Analytics-Snapshot-Id');
    $secondSnapshotId = $second->baseResponse->headers->get('X-Analytics-Snapshot-Id');

    expect($first->json('metrics.generated_at'))->toBe($second->json('metrics.generated_at'));
    expect($firstSnapshotId)->toBe($secondSnapshotId);
    expect($first->json('snapshot_generated_at'))->not->toBe($second->json('snapshot_generated_at'));
});

it('keeps fresh behavior for admin-session callers', function () {
    $admin = User::factory()->create([
        'email' => 'admin@example.com',
    ]);

    Carbon::setTestNow(Carbon::create(2026, 2, 21, 8, 0, 0, 'America/New_York'));

    $first = $this
        ->actingAs($admin)
        ->getJson(route('admin.analytics.snapshot'));

    Carbon::setTestNow(Carbon::create(2026, 2, 21, 8, 4, 0, 'America/New_York'));

    $second = $this
        ->actingAs($admin)
        ->getJson(route('admin.analytics.snapshot'));

    Carbon::setTestNow(Carbon::create(2026, 2, 21, 8, 6, 0, 'America/New_York'));

    $fresh = $this
        ->actingAs($admin)
        ->getJson(route('admin.analytics.snapshot', ['fresh' => 1]));

    expect($first->json('metrics.generated_at'))->toBe($second->json('metrics.generated_at'));
    expect($fresh->json('metrics.generated_at'))->not->toBe($second->json('metrics.generated_at'));
});

it('rate limits token requests to sixty requests per minute', function () {
    for ($i = 0; $i < 60; $i++) {
        $this->withHeader('Authorization', 'Bearer test-analytics-token')
            ->getJson(route('admin.analytics.snapshot'))
            ->assertOk();
    }

    $this->withHeader('Authorization', 'Bearer test-analytics-token')
        ->getJson(route('admin.analytics.snapshot'))
        ->assertTooManyRequests();
});

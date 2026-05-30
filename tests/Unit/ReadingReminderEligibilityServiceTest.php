<?php

use App\Models\ReadingLog;
use App\Models\User;
use App\Services\ReadingReminderEligibilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allows daily reminder after the local default time only when today is unread', function () {
    $service = app(ReadingReminderEligibilityService::class);
    $user = reminderEligibleUser();

    expect($service->isEligible($user, 'daily_reading', Carbon::parse('2026-05-26 08:59:00', 'America/Toronto')))->toBeFalse()
        ->and($service->isEligible($user, 'daily_reading', Carbon::parse('2026-05-26 09:00:00', 'America/Toronto')))->toBeTrue();

    ReadingLog::factory()->for($user)->create([
        'date_read' => '2026-05-26',
    ]);

    expect($service->isEligible($user->fresh(), 'daily_reading', Carbon::parse('2026-05-26 09:01:00', 'America/Toronto')))->toBeFalse();
});

it('allows streak warning after the evening default only when an active streak is at risk', function () {
    $service = app(ReadingReminderEligibilityService::class);
    $user = reminderEligibleUser();

    ReadingLog::factory()->for($user)->create([
        'date_read' => '2026-05-25',
    ]);

    expect($service->isEligible($user->fresh(), 'streak_risk', Carbon::parse('2026-05-26 17:59:00', 'America/Toronto')))->toBeFalse()
        ->and($service->isEligible($user->fresh(), 'streak_risk', Carbon::parse('2026-05-26 18:00:00', 'America/Toronto')))->toBeTrue();

    ReadingLog::factory()->for($user)->create([
        'date_read' => '2026-05-26',
        'chapter' => 2,
    ]);

    expect($service->isEligible($user->fresh(), 'streak_risk', Carbon::parse('2026-05-26 18:01:00', 'America/Toronto')))->toBeFalse();
});

it('does not allow streak warning without an active recent streak', function () {
    $service = app(ReadingReminderEligibilityService::class);
    $user = reminderEligibleUser();

    ReadingLog::factory()->for($user)->create([
        'date_read' => '2026-05-24',
    ]);

    expect($service->isEligible($user->fresh(), 'streak_risk', Carbon::parse('2026-05-26 18:00:00', 'America/Toronto')))->toBeFalse();
});

it('uses actual subscription rows rather than the account connected marker for eligibility', function () {
    $service = app(ReadingReminderEligibilityService::class);
    $user = User::factory()->create([
        'push_notifications_enabled_at' => null,
        'daily_reading_reminder_enabled_at' => now(),
        'streak_warning_enabled_at' => now(),
        'push_notification_timezone' => 'America/Toronto',
    ]);

    expect($service->isEligible($user, 'daily_reading', Carbon::parse('2026-05-26 09:00:00', 'America/Toronto')))->toBeFalse();

    $user->updatePushSubscription('https://example.com/subscription-'.$user->id, 'key', 'token', 'aes128gcm');

    expect($service->isEligible($user->fresh(), 'daily_reading', Carbon::parse('2026-05-26 09:00:00', 'America/Toronto')))->toBeTrue();
});

it('falls back to the app timezone when a stored reminder timezone is invalid', function () {
    config(['app.timezone' => 'America/Toronto']);

    $service = app(ReadingReminderEligibilityService::class);
    $user = reminderEligibleUser();
    $user->forceFill(['push_notification_timezone' => 'Not/AZone'])->save();

    expect($service->isEligible($user->fresh(), 'daily_reading', Carbon::parse('2026-05-26 13:00:00', 'UTC')))->toBeTrue();
});

function reminderEligibleUser(): User
{
    $user = User::factory()->create([
        'push_notifications_enabled_at' => now(),
        'daily_reading_reminder_enabled_at' => now(),
        'streak_warning_enabled_at' => now(),
        'push_notification_timezone' => 'America/Toronto',
    ]);

    $user->updatePushSubscription('https://example.com/subscription-'.$user->id, 'key', 'token', 'aes128gcm');

    return $user;
}

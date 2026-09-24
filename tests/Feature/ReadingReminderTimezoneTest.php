<?php

use App\Jobs\SendReadingReminderPush;
use App\Models\ReadingLog;
use App\Models\User;
use App\Models\WebPushReminderDelivery;
use App\Notifications\ReadingReminderPushNotification;
use App\Services\ReadingReminderEligibilityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Notification::fake();
});

function accountTimezoneReminderUser(string $timezone): User
{
    $user = User::factory()->create([
        'reading_timezone' => $timezone,
        'daily_reading_reminder_enabled_at' => now(),
        'streak_warning_enabled_at' => now(),
    ]);
    $user->updatePushSubscription('https://example.com/reminder-'.$user->id, 'key', 'token');

    return $user;
}

it('uses account local reminder thresholds across timezones and daylight saving changes', function (string $zone, string $date, string $type, string $before, string $due) {
    $user = accountTimezoneReminderUser($zone);
    ReadingLog::factory()->for($user)->create(['date_read' => Carbon::parse($date)->subDay()->toDateString()]);
    $service = app(ReadingReminderEligibilityService::class);
    expect($service->isEligible($user, $type, Carbon::parse("$date $before", $zone)))->toBeFalse();
    expect($service->isEligible($user, $type, Carbon::parse("$date $due", $zone)))->toBeTrue();
    ReadingLog::factory()->for($user)->create(['date_read' => $date]);
    expect($service->isEligible($user, $type, Carbon::parse("$date $due", $zone)))->toBeFalse();
})->with([
    'Tokyo daily' => ['Asia/Tokyo', '2026-09-10', 'daily_reading', '08:59', '09:00'],
    'Honolulu evening' => ['Pacific/Honolulu', '2026-09-10', 'streak_risk', '17:59', '18:00'],
    'spring daily' => ['America/Toronto', '2026-03-08', 'daily_reading', '08:59', '09:00'],
    'fall evening' => ['America/Toronto', '2026-11-01', 'streak_risk', '17:59', '18:00'],
]);

it('reconsiders a queued reminder at the new local due time without sending twice', function (string $type, string $instant, string $later) {
    Queue::fake();
    $this->travelTo(Carbon::parse($instant, 'UTC'));
    $user = accountTimezoneReminderUser('America/Toronto');
    ReadingLog::factory()->for($user)->create(['date_read' => '2026-09-09']);
    $delivery = WebPushReminderDelivery::factory()->for($user)->create([
        'reminder_type' => $type, 'reminder_date' => '2026-09-10', 'scheduled_for_at' => now(),
    ]);
    $this->actingAs($user)->patchJson(route('settings.update'), ['reading_timezone' => 'America/Chicago'])->assertOk();
    (new SendReadingReminderPush($delivery->id))->handle();
    Notification::assertNothingSent();
    expect($delivery->fresh()->skipped_at)->not->toBeNull();

    $this->travelTo(Carbon::parse($later, 'UTC'));
    $this->artisan('push:dispatch-reading-reminders')->assertSuccessful();
    expect($delivery->fresh()->skipped_at)->toBeNull();
    Queue::assertPushed(SendReadingReminderPush::class);
    (new SendReadingReminderPush($delivery->id))->handle();
    (new SendReadingReminderPush($delivery->id))->handle();
    Notification::assertSentToTimes($user, ReadingReminderPushNotification::class, 1);
    expect($delivery->fresh()->sent_at)->not->toBeNull();
    $this->artisan('push:dispatch-reading-reminders')->assertSuccessful();
    expect(WebPushReminderDelivery::where('user_id', $user->id)->where('reminder_type', $type)->count())->toBe(1);
})->with([
    'morning' => ['daily_reading', '2026-09-10 13:05:00', '2026-09-10 14:00:00'],
    'evening' => ['streak_risk', '2026-09-10 22:05:00', '2026-09-10 23:00:00'],
]);

it('sends a newly due reminder after correction without skipping the change day', function () {
    $this->travelTo(Carbon::parse('2026-09-10 01:00:00', 'UTC'));
    $user = accountTimezoneReminderUser('America/Toronto');
    $oldDelivery = WebPushReminderDelivery::factory()->for($user)->create([
        'reminder_type' => 'daily_reading', 'reminder_date' => '2026-09-09', 'sent_at' => now()->subHours(5),
    ]);
    $this->actingAs($user)->patchJson(route('settings.update'), ['reading_timezone' => 'Asia/Tokyo'])->assertOk();
    $this->artisan('push:dispatch-reading-reminders')->assertSuccessful();
    Notification::assertSentToTimes($user, ReadingReminderPushNotification::class, 1);
    expect(WebPushReminderDelivery::where('user_id', $user->id)->whereDate('reminder_date', '2026-09-10')->first()->sent_at)->not->toBeNull();

    $this->patchJson(route('settings.update'), ['reading_timezone' => 'America/Toronto'])->assertOk();
    $this->artisan('push:dispatch-reading-reminders')->assertSuccessful();
    Notification::assertSentToTimes($user, ReadingReminderPushNotification::class, 1);
    expect($oldDelivery->fresh()->sent_at)->not->toBeNull();
});

it('suppresses an old queued date and uses the corrected calendar reading log before sending', function () {
    $this->travelTo(Carbon::parse('2026-09-10 01:00:00', 'UTC'));
    $user = accountTimezoneReminderUser('America/Toronto');
    $oldDelivery = WebPushReminderDelivery::factory()->for($user)->create([
        'reminder_type' => 'daily_reading', 'reminder_date' => '2026-09-09',
    ]);
    $this->actingAs($user)->patchJson(route('settings.update'), ['reading_timezone' => 'Asia/Tokyo'])->assertOk();
    (new SendReadingReminderPush($oldDelivery->id))->handle();
    expect($oldDelivery->fresh()->skipped_at)->not->toBeNull();
    ReadingLog::factory()->for($user)->create(['date_read' => '2026-09-10']);
    $this->artisan('push:dispatch-reading-reminders')->assertSuccessful();
    Notification::assertNothingSent();
    expect(WebPushReminderDelivery::where('user_id', $user->id)->count())->toBe(1);
});

it('does not reopen exhausted or already sent deliveries', function (string $status) {
    $this->travelTo(Carbon::parse('2026-09-10 01:00:00', 'UTC'));
    $user = accountTimezoneReminderUser('Asia/Tokyo');
    $delivery = WebPushReminderDelivery::factory()->for($user)->create([
        'reminder_type' => 'daily_reading', 'reminder_date' => '2026-09-10', $status => now(),
    ]);
    $this->artisan('push:dispatch-reading-reminders')->assertSuccessful();
    Notification::assertNothingSent();
    expect($delivery->fresh()->getAttribute($status))->not->toBeNull();
})->with(['sent_at', 'failed_at']);

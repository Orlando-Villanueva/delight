<?php

use App\Models\ReadingLog;
use App\Models\User;
use App\Services\ReadingCalendarService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

it('establishes the first reported timezone without changing reading history or reminder preferences', function () {
    $user = User::factory()->create();
    ReadingLog::factory()->for($user)->create(['date_read' => '2026-09-08']);
    $history = DB::table('reading_logs')->where('user_id', $user->id)->get()->toJson();
    $calendar = app(ReadingCalendarService::class);

    $timezone = $calendar->establishTimezone($user, 'Asia/Tokyo');

    expect($timezone)->toBe('Asia/Tokyo');
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'reading_timezone' => 'Asia/Tokyo',
        'push_notification_timezone' => null,
    ]);
    expect(DB::table('reading_logs')->where('user_id', $user->id)->get()->toJson())->toBe($history);
});

it('preserves the established calendar when another client reports a different timezone', function () {
    $user = User::factory()->create();
    $otherClient = User::findOrFail($user->id);
    $calendar = app(ReadingCalendarService::class);
    $calendar->establishTimezone($user, 'Asia/Tokyo');

    $timezone = $calendar->establishTimezone($otherClient, 'America/Toronto');

    expect($timezone)->toBe('Asia/Tokyo');
    $this->assertDatabaseHas('users', ['id' => $user->id, 'reading_timezone' => 'Asia/Tokyo']);
});

it('prefers a saved reminder timezone when establishing a calendar', function () {
    $user = User::factory()->create(['push_notification_timezone' => 'America/Toronto']);

    $timezone = app(ReadingCalendarService::class)->establishTimezone($user, 'Asia/Tokyo');

    expect($timezone)->toBe('America/Toronto');
    $this->assertDatabaseHas('users', ['id' => $user->id, 'reading_timezone' => 'America/Toronto']);
});

it('keeps the fallback provisional until a usable timezone arrives', function (?string $reportedTimezone) {
    config(['app.timezone' => 'America/New_York']);
    $user = User::factory()->create(['push_notification_timezone' => 'invalid/timezone']);
    $calendar = app(ReadingCalendarService::class);

    expect($calendar->establishTimezone($user, $reportedTimezone))->toBe('America/New_York');
    $this->assertDatabaseHas('users', ['id' => $user->id, 'reading_timezone' => null]);

    expect($calendar->establishTimezone($user, 'Asia/Tokyo'))->toBe('Asia/Tokyo');
    $this->assertDatabaseHas('users', ['id' => $user->id, 'reading_timezone' => 'Asia/Tokyo']);
})->with([null, '', 'invalid/timezone', '+09:00']);

it('resolves the account calendar without persisting a fallback or changing the reference instant', function () {
    config(['app.timezone' => 'America/New_York']);
    $user = User::factory()->create();
    $reference = Carbon::parse('2026-09-10 02:00:00', 'UTC');
    $calendar = app(ReadingCalendarService::class);

    expect($calendar->todayFor($user, $reference)->toDateString())->toBe('2026-09-09');
    expect($reference->format('Y-m-d H:i:s e'))->toBe('2026-09-10 02:00:00 UTC');
    $this->assertDatabaseHas('users', ['id' => $user->id, 'reading_timezone' => null]);
});

it('uses the current instant in the saved timezone when no reference is supplied', function () {
    $this->travelTo(Carbon::parse('2026-09-09 15:00:00', 'UTC'));
    $user = User::factory()->make(['reading_timezone' => 'Asia/Tokyo']);

    expect(app(ReadingCalendarService::class)->nowFor($user)->format('Y-m-d H:i:s e'))
        ->toBe('2026-09-10 00:00:00 Asia/Tokyo');
});

it('uses a valid reminder timezone or the application fallback for an invalid saved calendar', function (?string $reminderTimezone, string $expectedTimezone) {
    config(['app.timezone' => 'America/New_York']);
    $user = User::factory()->make([
        'reading_timezone' => 'invalid/timezone',
        'push_notification_timezone' => $reminderTimezone,
    ]);

    expect(app(ReadingCalendarService::class)->timezoneFor($user))->toBe($expectedTimezone);
})->with([
    'valid reminder timezone' => ['Asia/Tokyo', 'Asia/Tokyo'],
    'missing reminder timezone' => [null, 'America/New_York'],
    'invalid reminder timezone' => ['invalid/timezone', 'America/New_York'],
]);

it('uses the account timezone for today and yesterday across date boundaries', function (string $timezone, string $instant, string $today, string $yesterday) {
    $user = User::factory()->make(['reading_timezone' => $timezone]);
    $reference = Carbon::parse($instant, 'UTC');
    $calendar = app(ReadingCalendarService::class);

    expect($calendar->todayFor($user, $reference)->toDateString())->toBe($today);
    expect($calendar->yesterdayFor($user, $reference)->toDateString())->toBe($yesterday);
})->with([
    'Tokyo before midnight' => ['Asia/Tokyo', '2026-09-09 14:59:59', '2026-09-09', '2026-09-08'],
    'Tokyo at midnight' => ['Asia/Tokyo', '2026-09-09 15:00:00', '2026-09-10', '2026-09-09'],
    'Honolulu behind application date' => ['Pacific/Honolulu', '2026-09-10 05:00:00', '2026-09-09', '2026-09-08'],
    'Toronto year boundary' => ['America/Toronto', '2027-01-01 05:00:00', '2027-01-01', '2026-12-31'],
    'UTC supported' => ['UTC', '2026-09-10 00:00:00', '2026-09-10', '2026-09-09'],
]);

it('uses calendar days across daylight saving transitions', function (string $instant, string $today, string $yesterday, int $hours) {
    $user = User::factory()->make(['reading_timezone' => 'America/Toronto']);
    $reference = Carbon::parse($instant, 'UTC');
    $calendar = app(ReadingCalendarService::class);
    $start = $calendar->todayFor($user, $reference);
    $previousStart = $calendar->yesterdayFor($user, $reference);

    expect($start->toDateString())->toBe($today);
    expect($previousStart->toDateString())->toBe($yesterday);
    expect($previousStart->diffInHours($start))->toEqual($hours);
})->with([
    'spring forward' => ['2026-03-09 04:00:00', '2026-03-09', '2026-03-08', 23],
    'fall back' => ['2026-11-02 05:00:00', '2026-11-02', '2026-11-01', 25],
]);

it('backfills only valid reminder timezones without modifying historical records or existing calendars', function () {
    $user = User::factory()->create(['push_notification_timezone' => 'Asia/Tokyo']);
    $missing = User::factory()->create();
    $invalid = User::factory()->create(['push_notification_timezone' => 'invalid/timezone']);
    $established = User::factory()->create([
        'reading_timezone' => 'America/Toronto',
        'push_notification_timezone' => 'Asia/Tokyo',
    ]);
    ReadingLog::factory()->for($user)->create(['date_read' => '2026-09-08']);
    $history = DB::table('reading_logs')->get()->toJson();
    $migration = require database_path('migrations/2026_09_09_222011_backfill_reading_timezones_from_reminder_preferences.php');

    $migration->up();
    $migration->up();

    $this->assertDatabaseHas('users', ['id' => $user->id, 'reading_timezone' => 'Asia/Tokyo']);
    $this->assertDatabaseHas('users', ['id' => $missing->id, 'reading_timezone' => null]);
    $this->assertDatabaseHas('users', ['id' => $invalid->id, 'reading_timezone' => null]);
    $this->assertDatabaseHas('users', ['id' => $established->id, 'reading_timezone' => 'America/Toronto']);
    expect(DB::table('reading_logs')->get()->toJson())->toBe($history);
});

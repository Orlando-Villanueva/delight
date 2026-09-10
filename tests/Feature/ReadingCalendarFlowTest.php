<?php

use App\Models\ReadingLog;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Services\ReadingCalendarService;
use App\Services\ReadingLogService;
use App\Services\ReadingPlanService;
use App\Services\UserStatisticsService;
use Carbon\Carbon;

it('establishes the mobile calendar before returning dates and validating a reading', function () {
    $this->travelTo(Carbon::parse('2026-09-09 15:00:00', 'UTC'));
    $user = User::factory()->create();
    $token = $user->createToken('Phone', ['mobile'])->plainTextToken;

    $this->withToken($token)->withHeader('X-Reading-Timezone', 'Asia/Tokyo')
        ->getJson('/api/v1/bootstrap')
        ->assertOk()
        ->assertJsonPath('data.today', '2026-09-10')
        ->assertJsonPath('data.yesterday', '2026-09-09')
        ->assertJsonPath('data.has_read_today', false);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'reading_timezone' => 'Asia/Tokyo']);
    $this->postJson('/api/v1/reading-logs', [
        'book_id' => 1, 'start_chapter' => 1, 'date_read' => '2026-09-10',
    ])->assertCreated();
    $this->getJson('/api/v1/bootstrap')->assertOk()
        ->assertJsonPath('data.has_read_today', true)
        ->assertJsonPath('data.current_streak', 1)
        ->assertJsonPath('data.activity.13', ['date' => '2026-09-10', 'count' => 1]);
    $this->postJson('/api/v1/reading-logs', [
        'book_id' => 1, 'start_chapter' => 2, 'date_read' => '2026-09-08',
    ])->assertUnprocessable()->assertJsonValidationErrors('date_read');
    $this->assertDatabaseCount('reading_logs', 1);
});

it('renders and accepts the same web reading dates as mobile for accounts ahead and behind the application', function (string $timezone, string $instant, string $today, string $yesterday) {
    $this->travelTo(Carbon::parse($instant, 'UTC'));
    $user = User::factory()->create();

    $this->actingAs($user)->withUnencryptedCookie('reading_timezone_report', $timezone)
        ->get(route('logs.create'))->assertOk()
        ->assertSee('value="'.$today.'"', false)
        ->assertSee('value="'.$yesterday.'"', false)
        ->assertDontSee('reading_timezone_report=', false);

    $this->post(route('logs.store'), [
        'book_id' => 1, 'start_chapter' => 1, 'date_read' => $today,
    ])->assertSuccessful();
    $this->assertDatabaseHas('reading_logs', ['user_id' => $user->id, 'date_read' => $today.' 00:00:00']);
    $token = $user->createToken('Phone', ['mobile'])->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/bootstrap')->assertOk()
        ->assertJsonPath('data.today', $today)
        ->assertJsonPath('data.yesterday', $yesterday)
        ->assertJsonPath('data.has_read_today', true)
        ->assertJsonPath('data.current_streak', 1);
})->with([
    'Tokyo' => ['Asia/Tokyo', '2026-09-09 15:00:00', '2026-09-10', '2026-09-09'],
    'Honolulu' => ['Pacific/Honolulu', '2026-09-10 05:00:00', '2026-09-09', '2026-09-08'],
]);

it('keeps old clients and unusable timezone reports working with a provisional fallback', function (?string $timezone) {
    config(['app.timezone' => 'America/New_York']);
    $this->travelTo(Carbon::parse('2026-09-09 15:00:00', 'UTC'));
    $user = User::factory()->create();
    $token = $user->createToken('Phone', ['mobile'])->plainTextToken;

    $this->withToken($token)->withHeaders($timezone === null ? [] : ['X-Reading-Timezone' => $timezone])
        ->getJson('/api/v1/bootstrap')->assertOk()->assertJsonPath('data.today', '2026-09-09');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'reading_timezone' => null]);
})->with([null, 'invalid/timezone', '+09:00']);

it('does not let timezone reports bypass mobile authentication or token abilities', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Other client', ['other'])->plainTextToken;

    $this->withHeader('X-Reading-Timezone', 'Asia/Tokyo')->getJson('/api/v1/bootstrap')->assertUnauthorized();
    $this->withToken($token)->getJson('/api/v1/bootstrap')->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $user->id, 'reading_timezone' => null]);
});

it('keeps an established timezone when another client reports a different timezone', function () {
    $this->travelTo(Carbon::parse('2026-09-09 15:00:00', 'UTC'));
    $user = User::factory()->create(['reading_timezone' => 'Asia/Tokyo']);
    $token = $user->createToken('Tablet', ['mobile'])->plainTextToken;

    $this->withToken($token)->withHeader('X-Reading-Timezone', 'America/Toronto')
        ->getJson('/api/v1/bootstrap')->assertOk()->assertJsonPath('data.today', '2026-09-10');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'reading_timezone' => 'Asia/Tokyo']);
});

it('does not reuse provisional calendar caches after timezone establishment', function () {
    config(['app.timezone' => 'America/New_York']);
    $this->travelTo(Carbon::parse('2026-09-09 15:00:00', 'UTC'));
    $user = User::factory()->create();
    ReadingLog::factory()->for($user)->create(['date_read' => '2026-09-08']);
    $stats = app(UserStatisticsService::class);
    expect($stats->getDashboardStatistics($user)['streaks']['current_streak'])->toBe(1);

    app(ReadingCalendarService::class)->establishTimezone($user, 'Asia/Tokyo');

    expect($stats->getDashboardStatistics($user)['streaks']['current_streak'])->toBe(0);
    expect(collect($stats->getMonthlyCalendarData($user)['calendar'])->first(fn ($day) => $day && $day['isToday'])['dateString'])
        ->toBe('2026-09-10');
});

it('refreshes cached streaks activity and calendar labels at the account midnight', function () {
    $this->travelTo(Carbon::parse('2026-09-09 14:59:59', 'UTC'));
    $user = User::factory()->create(['reading_timezone' => 'Asia/Tokyo']);
    ReadingLog::factory()->for($user)->create(['date_read' => '2026-09-08']);
    $stats = app(UserStatisticsService::class);
    expect($stats->getDashboardStatistics($user)['streaks']['current_streak'])->toBe(1);
    $stats->getMonthlyCalendarData($user);

    $this->travelTo(Carbon::parse('2026-09-09 15:00:00', 'UTC'));

    $dashboard = $stats->getDashboardStatistics($user);
    expect($dashboard['streaks']['current_streak'])->toBe(0);
    expect($dashboard['streaks']['recent_reading_activity_series'][13])->toBe(['date' => '2026-09-10', 'count' => 0]);
    expect(collect($stats->getMonthlyCalendarData($user)['calendar'])->first(fn ($day) => $day && $day['isToday'])['dateString'])
        ->toBe('2026-09-10');
});

it('uses the account month week and year for reading summaries', function () {
    $this->travelTo(Carbon::parse('2026-12-31 15:00:00', 'UTC'));
    $user = User::factory()->create(['reading_timezone' => 'Asia/Tokyo']);
    ReadingLog::factory()->for($user)->create(['date_read' => '2027-01-01']);
    $stats = app(UserStatisticsService::class);

    $summary = $stats->getReadingSummary($user);

    expect($summary['this_month_days'])->toBe(1);
    expect($summary['this_week_days'])->toBe(1);
    expect($summary['days_since_first_reading'])->toBe(1);
    expect($stats->getMonthlyCalendarData($user)['year'])->toBe(2027);
});

it('defaults service writes to the account date and invalidates previously cached results', function () {
    $this->travelTo(Carbon::parse('2026-09-09 15:00:00', 'UTC'));
    $user = User::factory()->create(['reading_timezone' => 'Asia/Tokyo']);
    $stats = app(UserStatisticsService::class);
    $stats->getDashboardStatistics($user);

    $log = app(ReadingLogService::class)->logReading($user, ['book_id' => 1, 'chapter' => 1]);

    expect($log->date_read->toDateString())->toBe('2026-09-10');
    expect($stats->getDashboardStatistics($user)['streaks']['current_streak'])->toBe(1);
    expect($stats->calculateSmartTimeAgo($log, $user))->toBe('just now');
});

it('uses the account date when subscribing to a plan and logging its chapters', function () {
    $this->travelTo(Carbon::parse('2026-09-09 15:00:00', 'UTC'));
    $user = User::factory()->create(['reading_timezone' => 'Asia/Tokyo']);
    $plan = ReadingPlan::factory()->create(['days' => [[
        'day' => 1,
        'label' => 'Genesis 1-2',
        'chapters' => [
            ['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 1],
            ['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 2],
        ],
    ]]]);
    $subscription = app(ReadingPlanService::class)->subscribe($user, $plan);

    $this->actingAs($user)->post(route('plans.logChapter', $plan), [
        'book_id' => 1, 'chapter' => 1, 'day' => 1,
    ])->assertOk();
    $this->post(route('plans.logAll', $plan), ['day' => 1])->assertOk();

    expect($subscription->started_at->toDateString())->toBe('2026-09-10');
    expect($user->readingLogs()->count())->toBe(2);
    expect($user->readingLogs()->whereDate('date_read', '2026-09-10')->count())->toBe(2);
});

it('keeps the current streak through daylight saving changes using account calendar dates', function (string $instant, string $today, string $yesterday) {
    $this->travelTo(Carbon::parse($instant, 'UTC'));
    $user = User::factory()->create(['reading_timezone' => 'America/Toronto']);
    ReadingLog::factory()->for($user)->create(['date_read' => $yesterday]);
    $token = $user->createToken('Phone', ['mobile'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/bootstrap')->assertOk()
        ->assertJsonPath('data.today', $today)
        ->assertJsonPath('data.yesterday', $yesterday)
        ->assertJsonPath('data.has_read_today', false)
        ->assertJsonPath('data.current_streak', 1);
})->with([
    'spring forward' => ['2026-03-09 04:00:00', '2026-03-09', '2026-03-08'],
    'fall back' => ['2026-11-02 05:00:00', '2026-11-02', '2026-11-01'],
]);

it('refreshes the previous month calendar when yesterday is logged across a month boundary', function () {
    $this->travelTo(Carbon::parse('2026-12-31 15:00:00', 'UTC'));
    $user = User::factory()->create(['reading_timezone' => 'Asia/Tokyo']);
    $stats = app(UserStatisticsService::class);
    expect($stats->getMonthlyCalendarData($user, 2026, 12)['thisMonthReadings'])->toBe(0);

    $log = app(ReadingLogService::class)->logReading($user, [
        'book_id' => 1, 'chapter' => 1, 'date_read' => '2026-12-31',
    ]);

    expect($stats->getMonthlyCalendarData($user, 2026, 12)['thisMonthReadings'])->toBe(1);

    app(ReadingLogService::class)->deleteReadingLog($log);

    expect($stats->getMonthlyCalendarData($user, 2026, 12)['thisMonthReadings'])->toBe(0);
});

it('detects timezone on login and registration pages without reloading them or tracking the landing page', function () {
    $this->get(route('login'))->assertOk()->assertSee('data-reload-after-detection="false"', false);
    $this->get(route('register'))->assertOk()->assertSee('data-reload-after-detection="false"', false);
    $this->get('/')->assertOk()->assertDontSee('reading_timezone_report', false);
});

it('uses the pre-authentication timezone on the first dashboard response', function (string $flow) {
    $this->travelTo(Carbon::parse('2026-09-09 15:00:00', 'UTC'));
    $credentials = ['email' => 'tokyo@example.test', 'password' => 'Example-password-123!'];
    if ($flow === 'login') {
        User::factory()->create($credentials);
    }

    $this->withUnencryptedCookie('reading_timezone_report', 'Asia/Tokyo')
        ->post('/'.$flow, $credentials + [
            'name' => 'Tokyo Reader',
            'password_confirmation' => $credentials['password'],
        ])->assertRedirect();
    $this->assertAuthenticated();

    $this->get(route('dashboard'))->assertOk()
        ->assertViewHas('calendarData', fn ($data) => collect($data['calendar'])
            ->first(fn ($day) => $day && $day['isToday'])['dateString'] === '2026-09-10')
        ->assertDontSee('data-reload-after-detection', false)
        ->assertCookieExpired('reading_timezone_report');

    $this->assertDatabaseHas('users', ['email' => $credentials['email'], 'reading_timezone' => 'Asia/Tokyo']);
})->with(['login', 'register']);

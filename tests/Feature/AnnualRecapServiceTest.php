<?php

use App\Models\AnnualRecap;
use App\Models\BookProgress;
use App\Models\ReadingLog;
use App\Models\User;
use App\Services\AnnualRecapService;
use App\Services\ReadingLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

it('calculates yearly streak counts consecutive days correctly', function () {
    $user = User::factory()->create();
    $year = 2025;

    $dates = [
        '2025-01-01',
        '2025-01-02',
        '2025-01-03',
        '2025-01-04',
        '2025-01-05',
        '2025-01-07',
        '2025-01-08',
        '2025-01-09',
    ];

    foreach ($dates as $date) {
        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'date_read' => $date,
            'created_at' => Carbon::parse($date)->setTime(10, 0),
        ]);
    }

    $recap = app(AnnualRecapService::class)->getRecap($user, $year);

    expect($recap['yearly_streak']['count'])->toBe(5)
        ->and($recap['yearly_streak']['start'])->toBe('Jan 1')
        ->and($recap['yearly_streak']['end'])->toBe('Jan 5');
});

it('calculates streaks with duplicate days', function () {
    $user = User::factory()->create();
    $year = 2025;

    foreach (['2025-02-01', '2025-02-01', '2025-02-02', '2025-02-03'] as $date) {
        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'date_read' => $date,
        ]);
    }

    $recap = app(AnnualRecapService::class)->getRecap($user, $year);

    expect($recap['yearly_streak']['count'])->toBe(3);
});

it('calculates streaks across months', function () {
    $user = User::factory()->create();
    $year = 2025;

    foreach (['2025-01-31', '2025-02-01'] as $date) {
        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'date_read' => $date,
        ]);
    }

    $recap = app(AnnualRecapService::class)->getRecap($user, $year);

    expect($recap['yearly_streak']['count'])->toBe(2)
        ->and($recap['yearly_streak']['start'])->toBe('Jan 31')
        ->and($recap['yearly_streak']['end'])->toBe('Feb 1');
});

it('persists past year recap snapshots', function () {
    $user = User::factory()->create();
    $year = now()->year - 1;

    ReadingLog::factory()->for($user)->create([
        'date_read' => "{$year}-02-01",
    ]);

    app(AnnualRecapService::class)->getRecap($user, $year);

    $recap = AnnualRecap::query()
        ->where('user_id', $user->id)
        ->where('year', $year)
        ->first();

    expect($recap)->not->toBeNull()
        ->and($recap->snapshot)->not->toBeEmpty();
});

it('caches current year recaps', function () {
    $user = User::factory()->create();
    $year = now()->year;

    ReadingLog::factory()->for($user)->create([
        'date_read' => now()->startOfYear()->addDay()->toDateString(),
    ]);

    app(AnnualRecapService::class)->getRecap($user, $year);

    expect(Cache::has(AnnualRecapService::cacheKeyFor($user, $year)))->toBeTrue();
});

it('includes deuterocanonical top books for opted-in users', function () {
    $user = User::factory()->create([
        'deuterocanonical_books_enabled_at' => now(),
    ]);
    $year = now()->year;

    ReadingLog::factory()->for($user)->create([
        'book_id' => 67,
        'chapter' => 1,
        'passage_text' => 'Tobit 1',
        'date_read' => now()->startOfYear()->addDay()->toDateString(),
    ]);

    $recap = app(AnnualRecapService::class)->getRecap($user, $year);

    expect($recap['top_books']->first()['name'])->toBe('Tobit');
});

it('excludes deuterocanonical top books for opted-out users without dropping history counts', function () {
    $user = User::factory()->create();
    $year = now()->year;

    ReadingLog::factory()->for($user)->create([
        'book_id' => 67,
        'chapter' => 1,
        'passage_text' => 'Tobit 1',
        'date_read' => now()->startOfYear()->addDay()->toDateString(),
    ]);

    $recap = app(AnnualRecapService::class)->getRecap($user, $year);

    expect($recap['top_books'])->toBeEmpty()
        ->and($recap['total_chapters_read'])->toBe(1)
        ->and($recap['active_days_count'])->toBe(1);
});

it('invalidates current year recap cache when reading logs are created', function () {
    $user = User::factory()->create();
    $year = now()->year;
    $cacheKey = AnnualRecapService::cacheKeyFor($user, $year);

    Cache::put($cacheKey, ['cached' => true], 3600);

    expect(Cache::has($cacheKey))->toBeTrue();

    app(ReadingLogService::class)->logReading($user, [
        'book_id' => 1,
        'chapter' => 1,
        'date_read' => now()->toDateString(),
    ]);

    expect(Cache::has($cacheKey))->toBeFalse();
});

it('invalidates the account-local recap year across the server year boundary', function () {
    $user = User::factory()->create([
        'created_at' => '2025-01-01 12:00:00',
        'reading_timezone' => 'Pacific/Honolulu',
    ]);
    $accountYear = 2025;
    $serverYear = 2026;

    $this->travelTo(Carbon::parse('2026-01-01 05:30:00', 'UTC'));

    AnnualRecap::create([
        'user_id' => $user->id,
        'year' => $accountYear,
        'snapshot' => ['year' => $accountYear, 'total_chapters_read' => 0],
        'generated_at' => now(),
    ]);
    Cache::put(AnnualRecapService::cacheKeyFor($user, $accountYear), ['cached' => true], 3600);
    Cache::put(AnnualRecapService::cacheKeyFor($user, $serverYear), ['cached' => true], 3600);

    app(ReadingLogService::class)->logReadingWithResult($user, [
        'book_id' => 1,
        'chapter' => 1,
        'date_read' => '2025-12-31',
    ], evaluateAchievements: false);

    expect($user->readingLogs()->count())->toBe(1)
        ->and($user->readingLogs()->value('date_read')->toDateString())->toBe('2025-12-31');

    expect(Cache::has(AnnualRecapService::cacheKeyFor($user, $accountYear)))->toBeFalse()
        ->and(Cache::has(AnnualRecapService::cacheKeyFor($user, $serverYear)))->toBeTrue()
        ->and(AnnualRecap::query()->where('user_id', $user->id)->where('year', $accountYear)->exists())->toBeFalse();

    $recap = app(AnnualRecapService::class)->getRecap($user, $accountYear);

    expect($recap['total_chapters_read'])->toBe(1);

    $this->travelBack();
});

it('counts book completions using the database timestamp timezone', function () {
    $user = User::factory()->create([
        'created_at' => '2025-01-01 12:00:00',
        'reading_timezone' => 'Pacific/Honolulu',
    ]);

    $this->travelTo(Carbon::parse('2026-01-01 10:30:00', 'UTC'));

    ReadingLog::factory()->for($user)->create([
        'book_id' => 1,
        'chapter' => 1,
        'date_read' => '2025-12-31',
    ]);
    BookProgress::factory()->for($user)->create([
        'book_id' => 1,
        'is_completed' => true,
        'last_updated' => now()->subHour(),
    ]);

    $recap = app(AnnualRecapService::class)->getRecap($user, 2025);

    expect($recap['books_completed_count'])->toBe(1);

    $this->travelBack();
});

it('uses percentage thresholds for partial year reader personalities', function () {
    Carbon::setTestNow(Carbon::create(2025, 12, 31, 12, 0, 0));

    try {
        $user = User::factory()->create();
        $year = 2025;
        $startDate = Carbon::create(2025, 8, 1);

        for ($i = 0; $i < 130; $i++) {
            ReadingLog::factory()->create([
                'user_id' => $user->id,
                'date_read' => $startDate->copy()->addDays($i)->toDateString(),
            ]);
        }

        $recap = app(AnnualRecapService::class)->getRecap($user, $year);

        expect($recap['reader_personality']['name'])->toBe('Daily Devotee')
            ->and($recap['reader_personality']['stats'])->toContain('85%');
    } finally {
        Carbon::setTestNow();
    }
});

it('shows the dashboard card during December', function () {
    $state = app(AnnualRecapService::class)->getDashboardCardState(Carbon::create(2025, 12, 15, 12, 0, 0));

    expect($state['show'])->toBeTrue()
        ->and($state['year'])->toBe(2025)
        ->and($state['end_label'])->toBe('Dec 15, 2025')
        ->and($state['is_final'])->toBeFalse();
});

it('uses the supplied timezone for recap card boundaries', function () {
    $state = app(AnnualRecapService::class)->getDashboardCardState(
        Carbon::create(2025, 12, 1, 0, 30, 0, 'Asia/Tokyo')
    );

    expect($state['show'])->toBeTrue()
        ->and($state['year'])->toBe(2025)
        ->and($state['end_label'])->toBe('Dec 1, 2025');
});

it('shows the dashboard card during the January grace period', function () {
    $state = app(AnnualRecapService::class)->getDashboardCardState(Carbon::create(2026, 1, 2, 12, 0, 0));

    expect($state['show'])->toBeTrue()
        ->and($state['year'])->toBe(2025)
        ->and($state['end_label'])->toBe('Dec 31, 2025')
        ->and($state['is_final'])->toBeTrue();
});

it('hides the dashboard card after the January grace period', function () {
    $state = app(AnnualRecapService::class)->getDashboardCardState(Carbon::create(2026, 1, 10, 12, 0, 0));

    expect($state['show'])->toBeFalse();
});

it('hides the dashboard card before December', function () {
    $state = app(AnnualRecapService::class)->getDashboardCardState(Carbon::create(2025, 11, 30, 12, 0, 0));

    expect($state['show'])->toBeFalse();
});

it('hides the dashboard card when the view is missing', function () {
    $state = app(AnnualRecapService::class)->getDashboardCardState(Carbon::create(2024, 12, 15, 12, 0, 0));

    expect($state['show'])->toBeFalse()
        ->and($state['year'])->toBe(2024);
});

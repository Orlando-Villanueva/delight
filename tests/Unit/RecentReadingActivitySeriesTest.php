<?php

use App\Models\ReadingLog;
use App\Models\User;
use App\Services\ReadingLogService;
use App\Services\UserStatisticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
});

afterEach(function (): void {
    Cache::flush();
    Carbon::setTestNow();
});

it('builds an exact zero-filled 14 day recent reading activity series', function (): void {
    Carbon::setTestNow('2024-01-14 10:00:00');

    $user = User::factory()->create();

    createReadingActivityLog($user, '2023-12-31');
    createReadingActivityLog($user, '2024-01-01');
    createReadingActivityLog($user, '2024-01-07');
    createReadingActivityLog($user, '2024-01-07', 2);
    createReadingActivityLog($user, '2024-01-13');
    createReadingActivityLog($user, '2024-01-14');
    createReadingActivityLog($user, '2024-01-14', 2);
    createReadingActivityLog($user, '2024-01-14', 3);

    $series = app(ReadingLogService::class)->getRecentReadingActivitySeries($user);

    expect($series)
        ->toHaveCount(14)
        ->and($series[0])->toBe(['date' => '2024-01-01', 'count' => 1])
        ->and($series[5])->toBe(['date' => '2024-01-06', 'count' => 0])
        ->and($series[6])->toBe(['date' => '2024-01-07', 'count' => 2])
        ->and($series[12])->toBe(['date' => '2024-01-13', 'count' => 1])
        ->and($series[13])->toBe(['date' => '2024-01-14', 'count' => 3])
        ->and(collect($series)->pluck('date')->all())->toBe([
            '2024-01-01',
            '2024-01-02',
            '2024-01-03',
            '2024-01-04',
            '2024-01-05',
            '2024-01-06',
            '2024-01-07',
            '2024-01-08',
            '2024-01-09',
            '2024-01-10',
            '2024-01-11',
            '2024-01-12',
            '2024-01-13',
            '2024-01-14',
        ]);
});

it('returns fourteen zero days when there is no recent activity', function (): void {
    Carbon::setTestNow('2024-01-14 10:00:00');

    $user = User::factory()->create();

    createReadingActivityLog($user, '2023-12-20');

    $series = app(ReadingLogService::class)->getRecentReadingActivitySeries($user);

    expect($series)
        ->toHaveCount(14)
        ->and(collect($series)->pluck('count')->all())->toBe(array_fill(0, 14, 0))
        ->and($series[0]['date'])->toBe('2024-01-01')
        ->and($series[13]['date'])->toBe('2024-01-14');
});

it('rejects non positive activity windows', function (): void {
    $user = User::factory()->create();

    app(ReadingLogService::class)->getRecentReadingActivitySeries($user, 0);
})->throws(InvalidArgumentException::class, 'Recent reading activity window must be at least one day.');

it('keeps record calculations tied to the current streak series while exposing recent activity', function (): void {
    Carbon::setTestNow('2024-01-14 10:00:00');

    $user = User::factory()->create();

    foreach (['2024-01-01', '2024-01-02', '2024-01-03', '2024-01-04'] as $date) {
        createReadingActivityLog($user, $date);
    }

    createReadingActivityLog($user, '2024-01-10');
    createReadingActivityLog($user, '2024-01-14');

    $stats = app(UserStatisticsService::class)->getStreakStatistics($user);

    expect($stats['current_streak'])->toBe(1)
        ->and($stats['record_status'])->toBe('none')
        ->and($stats['record_previous_best'])->toBe(4)
        ->and($stats['current_streak_started_at'])->toBe('2024-01-14')
        ->and($stats['current_streak_series'])->toBe([
            ['date' => '2024-01-14', 'count' => 1],
        ])
        ->and($stats['recent_reading_activity_series'])->toHaveCount(14)
        ->and($stats['recent_reading_activity_series'][0])->toBe(['date' => '2024-01-01', 'count' => 1])
        ->and($stats['recent_reading_activity_series'][9])->toBe(['date' => '2024-01-10', 'count' => 1])
        ->and($stats['recent_reading_activity_series'][13])->toBe(['date' => '2024-01-14', 'count' => 1]);
});

it('invalidates recent activity cache after an additional same day reading', function (): void {
    Carbon::setTestNow('2024-01-14 10:00:00');

    $user = User::factory()->create();
    $readingLogService = app(ReadingLogService::class);
    $statisticsService = app(UserStatisticsService::class);

    $readingLogService->logReading($user, [
        'book_id' => 1,
        'chapter' => 1,
        'date_read' => '2024-01-14',
    ]);

    $initialStats = $statisticsService->getStreakStatistics($user);

    expect(Cache::has("user_recent_reading_activity_series_{$user->id}"))->toBeTrue()
        ->and($initialStats['recent_reading_activity_series'][13])->toBe(['date' => '2024-01-14', 'count' => 1]);

    $readingLogService->logReading($user, [
        'book_id' => 1,
        'chapter' => 2,
        'date_read' => '2024-01-14',
    ]);

    expect(Cache::has("user_recent_reading_activity_series_{$user->id}"))->toBeFalse();

    $refreshedStats = $statisticsService->getStreakStatistics($user);

    expect($refreshedStats['recent_reading_activity_series'][13])->toBe(['date' => '2024-01-14', 'count' => 2]);
});

it('manual user statistics invalidation clears streak series caches', function (): void {
    Carbon::setTestNow('2024-01-14 10:00:00');

    $user = User::factory()->create();
    createReadingActivityLog($user, '2024-01-14');

    $statisticsService = app(UserStatisticsService::class);

    $statisticsService->getStreakStatistics($user);

    expect(Cache::has("user_current_streak_series_{$user->id}"))->toBeTrue()
        ->and(Cache::has("user_recent_reading_activity_series_{$user->id}"))->toBeTrue();

    $statisticsService->invalidateUserCache($user);

    expect(Cache::has("user_current_streak_series_{$user->id}"))->toBeFalse()
        ->and(Cache::has("user_recent_reading_activity_series_{$user->id}"))->toBeFalse();
});

it('renders recent activity accessibility copy and exact values without streak era wording', function (): void {
    $activitySeries = collect(range(1, 14))
        ->map(fn (int $day): array => [
            'date' => Carbon::parse('2024-01-01')->addDays($day - 1)->toDateString(),
            'count' => $day === 14 ? 2 : 0,
        ])
        ->all();

    $html = renderStreakCounterWithActivity($activitySeries);

    expect($html)
        ->toContain('Recent 14-day reading activity')
        ->toContain('Recent 14-day reading activity counts.')
        ->toContain('Jan 1, 2024: 0 readings')
        ->toContain('Jan 14, 2024: 2 readings')
        ->not->toContain('since streak began')
        ->not->toContain('current streak')
        ->not->toContain('No readings logged in the last 14 days yet.');
});

it('renders an intentional zero activity state near the chart', function (): void {
    $activitySeries = collect(range(1, 14))
        ->map(fn (int $day): array => [
            'date' => Carbon::parse('2024-01-01')->addDays($day - 1)->toDateString(),
            'count' => 0,
        ])
        ->all();

    $html = renderStreakCounterWithActivity($activitySeries);

    expect($html)
        ->toContain('Recent 14-day reading activity')
        ->toContain('No readings were logged in this window.')
        ->toContain('No readings logged in the last 14 days yet.')
        ->toContain('Jan 14, 2024: 0 readings')
        ->not->toContain('since streak began');
});

it('labels fallback streak series as current streak activity', function (): void {
    $streakSeries = [
        ['date' => '2024-01-13', 'count' => 1],
        ['date' => '2024-01-14', 'count' => 1],
    ];

    $html = renderStreakCounterWithActivity([], $streakSeries);

    expect($html)
        ->toContain('Current streak reading activity')
        ->toContain('Current streak reading activity counts.')
        ->toContain('At least one reading was logged during this streak.')
        ->toContain('Jan 13, 2024: 1 reading')
        ->toContain('Jan 14, 2024: 1 reading')
        ->not->toContain('Recent 14-day reading activity')
        ->not->toContain('No readings logged in the last 14 days yet.');
});

function createReadingActivityLog(User $user, string $dateRead, int $chapter = 1): ReadingLog
{
    return ReadingLog::factory()->for($user)->create([
        'book_id' => 1,
        'chapter' => $chapter,
        'passage_text' => "Genesis {$chapter}",
        'date_read' => $dateRead,
    ]);
}

function renderStreakCounterWithActivity(array $activitySeries, array $streakSeries = []): string
{
    return Blade::render(
        <<<'BLADE'
        <x-ui.streak-counter
            :current-streak="0"
            :longest-streak="0"
            :activity-series="$activitySeries"
            :streak-series="$streakSeries"
            :state-classes="$stateClasses"
        />
        BLADE,
        [
            'activitySeries' => $activitySeries,
            'streakSeries' => $streakSeries,
            'stateClasses' => ['showIcon' => false],
        ],
    );
}

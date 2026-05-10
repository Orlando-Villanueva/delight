<?php

use App\Models\BookProgress;
use App\Models\ReadingLog;
use App\Models\User;
use App\Models\UserAchievement;
use App\Services\AchievementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Carbon::setTestNow('2026-05-06 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function achievement_log_reading(User $user, string $date, int $chapter): void
{
    ReadingLog::factory()->for($user)->create([
        'book_id' => 1,
        'chapter' => $chapter,
        'passage_text' => "Genesis {$chapter}",
        'date_read' => $date,
    ]);
}

function achievement_progress(User $user, int $bookId, string $bookName, int $totalChapters, array $chaptersRead): BookProgress
{
    return BookProgress::factory()->for($user)->create([
        'book_id' => $bookId,
        'book_name' => $bookName,
        'total_chapters' => $totalChapters,
        'chapters_read' => $chaptersRead,
        'completion_percent' => round((count($chaptersRead) / $totalChapters) * 100, 2),
        'is_completed' => count($chaptersRead) >= $totalChapters,
        'last_updated' => now(),
    ]);
}

it('awards milestone achievements idempotently with stable context keys', function () {
    $user = User::factory()->create();

    foreach (range(0, 29) as $offset) {
        achievement_log_reading($user, today()->subDays(29 - $offset)->toDateString(), $offset + 1);
    }

    achievement_progress($user, 1, 'Genesis', 50, range(1, 50));
    achievement_progress($user, 2, 'Exodus', 40, range(1, 40));
    achievement_progress($user, 19, 'Psalms', 150, range(1, 150));
    achievement_progress($user, 43, 'John', 21, range(1, 21));
    achievement_progress($user, 44, 'Acts', 28, range(1, 28));
    achievement_progress($user, 45, 'Romans', 16, range(1, 16));

    $firstRun = app(AchievementService::class)->evaluateAndAward($user);
    $secondRun = app(AchievementService::class)->evaluateAndAward($user);

    expect($firstRun['awarded'])->toBeGreaterThan(0)
        ->and($firstRun['awarded_achievements'])->toHaveCount($firstRun['awarded'])
        ->and($secondRun['awarded'])->toBe(0)
        ->and($secondRun['awarded_achievements'])->toBeEmpty()
        ->and($secondRun['skipped_duplicates'])->toBeGreaterThan(0);

    expect($user->achievements()->pluck('context_key', 'achievement_key')->all())->toMatchArray([
        'first_reading' => 'first-reading',
        'first_week' => 'reading-days:7',
        'first_month' => 'reading-days:30',
        'reading_streak_7' => 'streak:7',
        'reading_streak_30' => 'streak:30',
        'personal_best_streak' => 'streak:30',
        'bible_progress_25' => 'progress:25',
    ]);

    $completedJohn = $user->achievements()
        ->where('achievement_key', 'book_completed')
        ->where('context_key', 'book:43')
        ->first();

    expect($completedJohn)->not->toBeNull()
        ->and($completedJohn->display_name)->toBe('Completed John')
        ->and($completedJohn->metadata)->toMatchArray([
            'book_id' => 43,
            'book_name' => 'John',
        ]);
});

it('builds celebration payload with earned achievements and relevant locked progress', function () {
    $user = User::factory()->create();

    achievement_log_reading($user, today()->toDateString(), 1);

    $log = $user->readingLogs()->first();
    $result = app(AchievementService::class)->evaluateAndAward($user);
    $payload = app(AchievementService::class)->getCelebrationPayload($user, $result['awarded_achievements'], $log);

    expect($payload['earned'])->not->toBeEmpty()
        ->and(collect($payload['earned'])->pluck('display_name')->all())->toContain('First reading')
        ->and($payload['progress'])->not->toBeEmpty()
        ->and(collect($payload['progress'])->pluck('display_name')->all())->not->toContain('First reading')
        ->and($payload['reading'])->toMatchArray([
            'passage' => 'Genesis 1',
            'date' => 'May 6, 2026',
        ]);
});

it('stores first reading context on the first reading achievement', function () {
    $user = User::factory()->create();

    achievement_log_reading($user, today()->toDateString(), 3);

    $result = app(AchievementService::class)->evaluateAndAward($user);
    $firstReading = $result['awarded_achievements']->firstWhere('achievement_key', 'first_reading');

    expect($firstReading)->not->toBeNull()
        ->and($firstReading->metadata)->toMatchArray([
            'book_id' => 1,
            'book_name' => 'Genesis',
            'chapter' => 3,
            'passage' => 'Genesis 3',
            'date_read' => '2026-05-06',
        ]);
});

it('builds curated next goals with almost finished books first', function () {
    $user = User::factory()->create();

    achievement_log_reading($user, today()->toDateString(), 1);
    achievement_progress($user, 1, 'Genesis', 50, array_values(array_diff(range(1, 50), [7, 19, 28, 41])));
    achievement_progress($user, 2, 'Exodus', 40, [1, 2, 3]);
    achievement_progress($user, 43, 'John', 21, array_values(array_diff(range(1, 21), [20, 21])));

    app(AchievementService::class)->evaluateAndAward($user);

    $shelf = app(AchievementService::class)->getShelfData($user);

    expect($shelf['next_goals']['books'])->toHaveCount(2)
        ->and($shelf['next_goals']['books']->pluck('book_name')->all())->toBe(['John', 'Genesis'])
        ->and($shelf['next_goals']['books']->first())->toMatchArray([
            'book_name' => 'John',
            'chapters_read' => 19,
            'total_chapters' => 21,
            'chapters_remaining' => 2,
            'missing_chapters' => [20, 21],
        ])
        ->and($shelf['next_goals']['progress'])->not->toBeEmpty()
        ->and($shelf['next_goals']['progress']->pluck('display_name')->all())->toContain('25% Bible progress')
        ->and($shelf['next_goals']['progress']->pluck('display_name')->all())->not->toContain('4-week target streak');
});

it('treats a duplicate inserted during award creation as a skipped duplicate', function () {
    $user = User::factory()->create();
    achievement_log_reading($user, today()->toDateString(), 1);
    $insertedDuplicate = false;

    UserAchievement::creating(function (UserAchievement $achievement) use (&$insertedDuplicate): void {
        if ($insertedDuplicate || $achievement->achievement_key !== 'first_reading') {
            return;
        }

        $insertedDuplicate = true;

        DB::table('user_achievements')->insert([
            'user_id' => $achievement->user_id,
            'achievement_key' => $achievement->achievement_key,
            'context_key' => $achievement->context_key,
            'category' => $achievement->category,
            'display_name' => $achievement->display_name,
            'description' => $achievement->description,
            'icon' => $achievement->icon,
            'style' => $achievement->style,
            'sort_order' => $achievement->sort_order,
            'metadata' => json_encode($achievement->metadata),
            'earned_at' => $achievement->earned_at,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    try {
        $result = app(AchievementService::class)->evaluateAndAward($user);
    } finally {
        UserAchievement::flushEventListeners();
    }

    expect($result['awarded'])->toBe(0)
        ->and($result['skipped_duplicates'])->toBe(1)
        ->and($user->achievements()->where('achievement_key', 'first_reading')->count())->toBe(1);
});

it('evaluates weekly target streaks across consecutive achieved weeks', function () {
    $user = User::factory()->create();
    $weekStart = Carbon::parse('2026-02-08');
    $chapter = 1;

    foreach (range(0, 11) as $weekOffset) {
        foreach ([0, 1, 3, 5] as $dayOffset) {
            achievement_log_reading(
                $user,
                $weekStart->copy()->addWeeks($weekOffset)->addDays($dayOffset)->toDateString(),
                $chapter
            );
            $chapter++;
        }
    }

    app(AchievementService::class)->evaluateAndAward($user);

    expect($user->achievements()->where('achievement_key', 'weekly_consistency_4')->exists())->toBeTrue()
        ->and($user->achievements()->where('achievement_key', 'weekly_consistency_8')->exists())->toBeTrue()
        ->and($user->achievements()->where('achievement_key', 'weekly_consistency_12')->exists())->toBeTrue();
});

it('keeps earned deuterocanonical achievements visible after opt out but does not newly evaluate them while opted out', function () {
    $optedIn = User::factory()->create([
        'deuterocanonical_books_enabled_at' => now(),
    ]);

    achievement_log_reading($optedIn, today()->toDateString(), 1);
    achievement_progress($optedIn, 67, 'Tobit', 14, range(1, 14));

    app(AchievementService::class)->evaluateAndAward($optedIn);
    $optedIn->forceFill(['deuterocanonical_books_enabled_at' => null])->save();

    $earnedShelf = app(AchievementService::class)->getShelfData($optedIn);

    expect($earnedShelf['earned']->flatten(1)->pluck('display_name')->all())->toContain('Completed Tobit');

    $optedOut = User::factory()->create();
    achievement_log_reading($optedOut, today()->toDateString(), 1);
    achievement_progress($optedOut, 67, 'Tobit', 14, range(1, 14));

    app(AchievementService::class)->evaluateAndAward($optedOut);

    expect($optedOut->achievements()->where('context_key', 'book:67')->exists())->toBeFalse();
});

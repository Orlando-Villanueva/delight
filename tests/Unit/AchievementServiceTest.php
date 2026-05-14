<?php

use App\Models\BookProgress;
use App\Models\ReadingLog;
use App\Models\User;
use App\Models\UserAchievement;
use App\Services\AchievementService;
use App\Services\BibleReferenceService;
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

function achievement_spread_bible_progress(User $user, int $chaptersToRead): void
{
    $books = collect(app(BibleReferenceService::class)->listBibleBooks());
    $remaining = $chaptersToRead;

    $books->each(function (array $book) use ($user, &$remaining): void {
        if ($remaining <= 0) {
            return;
        }

        $readCount = $book['chapters'] <= 10
            ? min($book['chapters'], $remaining)
            : min((int) floor($book['chapters'] * 0.4), $remaining);
        $chaptersRead = range(1, max(1, $readCount));
        $remaining -= count($chaptersRead);

        achievement_progress($user, $book['id'], $book['name'], $book['chapters'], $chaptersRead);
    });
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
        'first_month' => 'reading-days:30',
        'reading_streak_7' => 'streak:7',
        'reading_streak_30' => 'streak:30',
        'bible_progress_25' => 'progress:25',
    ]);

    expect($user->achievements()->where('achievement_key', 'personal_best_streak')->exists())->toBeFalse();

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

it('does not award first week for seven distinct non consecutive reading days', function () {
    $user = User::factory()->create();

    foreach (range(0, 6) as $offset) {
        achievement_log_reading($user, today()->subDays($offset * 2)->toDateString(), $offset + 1);
    }

    app(AchievementService::class)->evaluateAndAward($user);

    expect($user->achievements()->where('achievement_key', 'first_week')->exists())->toBeFalse()
        ->and($user->achievements()->where('achievement_key', 'reading_streak_7')->exists())->toBeFalse();
});

it('does not persist or surface a personal best for a first uninterrupted streak', function () {
    $user = User::factory()->create();
    achievement_log_reading($user, today()->subDay()->toDateString(), 1);
    achievement_log_reading($user, today()->toDateString(), 2);

    $result = app(AchievementService::class)->evaluateAndAward($user);
    $payload = app(AchievementService::class)->getCelebrationPayload(
        $user,
        collect(),
        $user->readingLogs()->latest('date_read')->first(),
        true
    );

    expect($result['candidates']->pluck('achievement_key')->all())->not->toContain('personal_best_streak')
        ->and($user->achievements()->where('achievement_key', 'personal_best_streak')->exists())->toBeFalse()
        ->and($payload['record'])->toBeNull();
});

it('returns a transient personal best record only when a new run beats a previous best', function () {
    $user = User::factory()->create();

    foreach (['2026-04-01', '2026-04-02', '2026-04-03'] as $index => $date) {
        achievement_log_reading($user, $date, $index + 1);
    }

    foreach (['2026-05-03', '2026-05-04', '2026-05-05', '2026-05-06'] as $index => $date) {
        achievement_log_reading($user, $date, $index + 4);
    }

    $log = $user->readingLogs()->whereDate('date_read', '2026-05-06')->first();
    $payload = app(AchievementService::class)->getCelebrationPayload($user, collect(), $log, true);

    expect($payload['record'])->toMatchArray([
        'eyebrow' => 'Personal best',
        'title' => 'Longest streak: 4 days',
        'description' => 'You beat your previous best of 3 days.',
        'current_streak' => 4,
        'previous_best' => 3,
    ]);
});

it('does not return another personal best record after the exact record-breaking day', function () {
    $user = User::factory()->create();

    foreach (['2026-04-01', '2026-04-02', '2026-04-03'] as $index => $date) {
        achievement_log_reading($user, $date, $index + 1);
    }

    foreach (['2026-05-02', '2026-05-03', '2026-05-04', '2026-05-05', '2026-05-06'] as $index => $date) {
        achievement_log_reading($user, $date, $index + 4);
    }

    $log = $user->readingLogs()->whereDate('date_read', '2026-05-06')->first();
    $payload = app(AchievementService::class)->getCelebrationPayload($user, collect(), $log, true);

    expect($payload['record'])->toBeNull();
});

it('still awards fixed streak milestones when a run breaks the previous best', function () {
    $user = User::factory()->create();

    foreach (range(0, 5) as $offset) {
        achievement_log_reading($user, Carbon::parse('2026-04-01')->addDays($offset)->toDateString(), $offset + 1);
    }

    foreach (range(0, 6) as $offset) {
        achievement_log_reading($user, Carbon::parse('2026-04-30')->addDays($offset)->toDateString(), $offset + 10);
    }

    $log = $user->readingLogs()->whereDate('date_read', '2026-05-06')->first();
    $result = app(AchievementService::class)->evaluateAndAward($user);
    $payload = app(AchievementService::class)->getCelebrationPayload($user, $result['awarded_achievements'], $log, true);

    expect($result['awarded_achievements']->pluck('achievement_key')->all())->toContain('reading_streak_7')
        ->and($result['awarded_achievements']->pluck('achievement_key')->all())->not->toContain('personal_best_streak')
        ->and($payload['record'])->toMatchArray([
            'title' => 'Longest streak: 7 days',
            'previous_best' => 6,
        ]);
});

it('uses clear first 30 reading days copy for the distinct day milestone', function () {
    $user = User::factory()->create();

    foreach (range(0, 29) as $offset) {
        achievement_log_reading($user, today()->subDays($offset * 2)->toDateString(), $offset + 1);
    }

    app(AchievementService::class)->evaluateAndAward($user);

    $achievement = $user->achievements()->where('achievement_key', 'first_month')->first();

    expect($achievement)->not->toBeNull()
        ->and($achievement->display_name)->toBe('First 30 reading days')
        ->and($achievement->description)->toBe('You logged 30 distinct reading days.');
});

it('chooses first reading as the dashboard milestone for new users', function () {
    $user = User::factory()->create();

    $milestone = app(AchievementService::class)->getDashboardMilestone($user)['milestone'];

    expect($milestone)->toMatchArray([
        'achievement_key' => 'first_reading',
        'display_name' => 'First reading',
        'current' => 0,
        'target' => 1,
    ]);
});

it('chooses the active streak threshold as the primary dashboard milestone', function () {
    $user = User::factory()->create();
    achievement_log_reading($user, today()->toDateString(), 1);

    $milestone = app(AchievementService::class)->getDashboardMilestone($user)['milestone'];

    expect($milestone)->toMatchArray([
        'achievement_key' => 'reading_streak_7',
        'display_name' => '7-day reading streak',
        'current' => 1,
        'target' => 7,
    ]);
});

it('chooses weekly rhythm when the week is active but the daily streak is broken', function () {
    $user = User::factory()->create();
    achievement_log_reading($user, '2026-05-03', 1);
    achievement_log_reading($user, '2026-05-04', 2);

    $milestone = app(AchievementService::class)->getDashboardMilestone($user)['milestone'];

    expect($milestone)->toMatchArray([
        'achievement_key' => 'weekly_rhythm',
        'display_name' => '4 days this week',
        'current' => 2,
        'target' => 4,
    ]);
});

it('chooses a nearly finished book over low progress catalog goals', function () {
    $user = User::factory()->create();
    achievement_log_reading($user, '2026-04-01', 1);
    achievement_progress($user, 43, 'John', 21, array_values(array_diff(range(1, 21), [20, 21])));

    $milestone = app(AchievementService::class)->getDashboardMilestone($user)['milestone'];

    expect($milestone)->toMatchArray([
        'achievement_key' => 'book_completed',
        'context_key' => 'book:43',
        'display_name' => 'Finish John',
        'current' => 19,
        'target' => 21,
    ]);
});

it('chooses a one chapter book goal over a far away yearly streak milestone', function () {
    $user = User::factory()->create();

    foreach (range(0, 99) as $offset) {
        achievement_log_reading($user, Carbon::parse('2025-12-24')->addDays($offset)->toDateString(), $offset + 1);
    }

    foreach (range(0, 11) as $offset) {
        achievement_log_reading($user, today()->subDays(11 - $offset)->toDateString(), $offset + 101);
    }

    achievement_progress($user, 15, 'Ezra', 10, range(1, 9));

    app(AchievementService::class)->evaluateAndAward($user);

    $milestone = app(AchievementService::class)->getDashboardMilestone($user)['milestone'];

    expect($milestone)->toMatchArray([
        'achievement_key' => 'book_completed',
        'context_key' => 'book:15',
        'display_name' => 'Finish Ezra',
        'current' => 9,
        'target' => 10,
    ]);
});

it('keeps a near streak threshold eligible when no closer book goal exists', function () {
    $user = User::factory()->create();

    foreach (range(0, 97) as $offset) {
        achievement_log_reading($user, today()->subDays(97 - $offset)->toDateString(), $offset + 1);
    }

    app(AchievementService::class)->evaluateAndAward($user);

    $milestone = app(AchievementService::class)->getDashboardMilestone($user)['milestone'];

    expect($milestone)->toMatchArray([
        'achievement_key' => 'reading_streak_100',
        'display_name' => '100-day reading streak',
        'current' => 98,
        'target' => 100,
    ]);
});

it('chooses Bible progress when it is the strongest live milestone', function () {
    $user = User::factory()->create();
    achievement_log_reading($user, '2026-04-01', 1);
    achievement_spread_bible_progress($user, 240);

    $milestone = app(AchievementService::class)->getDashboardMilestone($user)['milestone'];

    expect($milestone)->toMatchArray([
        'achievement_key' => 'bible_progress_25',
        'display_name' => '25% Bible progress',
        'target' => 25,
    ]);
});

it('chooses nearly completed testament progress when it is close', function () {
    $user = User::factory()->create();
    achievement_log_reading($user, '2026-04-01', 1);
    $books = collect(app(BibleReferenceService::class)->listBibleBooks('new'));

    $books->take($books->count() - 4)->each(function (array $book) use ($user): void {
        achievement_progress($user, $book['id'], $book['name'], $book['chapters'], range(1, $book['chapters']));
    });

    $milestone = app(AchievementService::class)->getDashboardMilestone($user)['milestone'];

    expect($milestone)->toMatchArray([
        'achievement_key' => 'testament_completed',
        'context_key' => 'testament:new',
        'display_name' => 'Complete the New Testament',
    ]);
});

it('builds celebration payload with earned achievements and relevant locked progress', function () {
    $user = User::factory()->create();

    achievement_log_reading($user, today()->toDateString(), 1);

    $log = $user->readingLogs()->first();
    $result = app(AchievementService::class)->evaluateAndAward($user);
    $payload = app(AchievementService::class)->getCelebrationPayload($user, $result['awarded_achievements'], $log, true);

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

it('does not create permanent weekly target streak achievements', function () {
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
    $shelf = app(AchievementService::class)->getShelfData($user);

    expect($user->achievements()->where('achievement_key', 'weekly_consistency_4')->exists())->toBeFalse()
        ->and($user->achievements()->where('achievement_key', 'weekly_consistency_8')->exists())->toBeFalse()
        ->and($user->achievements()->where('achievement_key', 'weekly_consistency_12')->exists())->toBeFalse()
        ->and($shelf['locked']->pluck('achievement_key')->all())->not->toContain('weekly_consistency_4')
        ->and($shelf['next_goals']['progress']->pluck('display_name')->all())->not->toContain('4-week target streak');
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

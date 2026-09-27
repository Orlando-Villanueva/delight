<?php

use App\Models\ReadingLog;
use App\Models\User;
use App\Models\UserAchievement;
use App\Services\AchievementService;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-05-06 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function achievement_backfill_user_with_john(): User
{
    $user = User::factory()->create();

    foreach (range(1, 21) as $chapter) {
        ReadingLog::factory()->for($user)->create([
            'book_id' => 43,
            'chapter' => $chapter,
            'passage_text' => "John {$chapter}",
            'date_read' => today()->subDays(30)->toDateString(),
        ]);
    }

    return $user;
}

/**
 * @return array<int, string>
 */
function achievement_backfill_genesis_completions(User $user, int $firstCompletion, int $lastCompletion): array
{
    $firstDate = Carbon::parse('2026-01-01');
    $completionDates = [];

    foreach (range($firstCompletion, $lastCompletion) as $completionNumber) {
        foreach (range(1, 50) as $chapter) {
            $date = $firstDate->copy()->addDays($completionNumber - 1);

            ReadingLog::factory()->for($user)->create([
                'book_id' => 1,
                'chapter' => $chapter,
                'passage_text' => "Genesis {$chapter}",
                'date_read' => $date->toDateString(),
            ]);

            if ($chapter === 50) {
                $completionDates[$completionNumber] = $date->toDateString();
            }
        }
    }

    return $completionDates;
}

it('backfills achievements for existing users and skips duplicates on rerun', function () {
    $user = achievement_backfill_user_with_john();

    $this->artisan('achievements:backfill')
        ->expectsOutput('Users scanned: 1')
        ->expectsOutputToContain('Achievements awarded:')
        ->assertSuccessful();

    expect($user->achievements()->where('achievement_key', 'book_completed')->where('context_key', 'book:43')->exists())->toBeTrue();
    $countAfterFirstRun = $user->achievements()->count();

    $this->artisan('achievements:backfill')
        ->expectsOutput('Users scanned: 1')
        ->expectsOutput('Achievements awarded: 0')
        ->expectsOutputToContain('Skipped duplicates:')
        ->assertSuccessful();

    expect($user->achievements()->count())->toBe($countAfterFirstRun);
});

it('can dry run without writing achievements', function () {
    $user = achievement_backfill_user_with_john();

    $this->artisan('achievements:backfill --dry-run')
        ->expectsOutput('Dry run: yes')
        ->expectsOutput('Users scanned: 1')
        ->expectsOutputToContain('Would award:')
        ->assertSuccessful();

    expect($user->achievements()->count())->toBe(0);
});

it('backfills each book completion with its completion date and only awards the next pass later', function () {
    $user = User::factory()->create();
    $completionDates = achievement_backfill_genesis_completions($user, 1, 3);

    foreach ([1, 2] as $completionNumber) {
        UserAchievement::factory()->for($user)->create([
            'achievement_key' => 'book_completed',
            'context_key' => $completionNumber === 1 ? 'book:1' : "book:1:completion:{$completionNumber}",
            'category' => 'books',
            'display_name' => 'Completed Genesis',
            'description' => 'You completed Genesis.',
            'metadata' => [
                'book_id' => 1,
                'book_name' => 'Genesis',
                'completion_number' => $completionNumber,
            ],
            'earned_at' => '2026-05-06 10:00:00',
        ]);
    }

    $unrelatedAchievement = UserAchievement::factory()->for($user)->create([
        'earned_at' => '2026-05-06 10:00:00',
    ]);

    $this->artisan("achievements:backfill {$user->id} --dry-run")
        ->expectsOutput('Dry run: yes')
        ->expectsOutput('Users scanned: 1')
        ->expectsOutput('Would award: 1')
        ->expectsOutput('Would update completion dates: 2')
        ->expectsOutput('Would award by achievement type:')
        ->expectsOutput('  book_completed: 1')
        ->expectsOutput('Would update dates by achievement type:')
        ->expectsOutput('  book_completed: 2')
        ->assertSuccessful();

    expect($user->achievements()->count())->toBe(3)
        ->and($user->achievements()->where('achievement_key', 'book_completed')->first()->earned_at->toDateString())->toBe('2026-05-06')
        ->and($user->achievements()->where('achievement_key', 'book_completed')->first()->completed_on)->toBeNull();

    $this->artisan("achievements:backfill {$user->id}")
        ->expectsOutput('Achievements awarded: 1')
        ->expectsOutput('Completion dates updated: 2')
        ->assertSuccessful();

    $completions = $user->achievements()
        ->where('achievement_key', 'book_completed')
        ->orderBy('earned_at')
        ->get();

    expect($completions)->toHaveCount(3)
        ->and($user->achievements()->whereNotIn('achievement_key', ['book_completed', 'testament_completed', 'bible_completed'])->count())->toBe(1)
        ->and($unrelatedAchievement->fresh()->earned_at->toDateString())->toBe('2026-05-06')
        ->and($unrelatedAchievement->fresh()->completed_on)->toBeNull();

    foreach ($completionDates as $completionNumber => $date) {
        $completion = $completions->firstWhere('context_key', $completionNumber === 1
            ? 'book:1'
            : "book:1:completion:{$completionNumber}");

        expect($completion->completed_on->toDateString())->toBe($date)
            ->and($completion->earned_at->toDateString())->toBe('2026-05-06');
    }

    $completionHistoryQueries = 0;
    DB::listen(function (QueryExecuted $query) use (&$completionHistoryQueries): void {
        $sql = strtolower($query->sql);

        if (str_contains($sql, 'from "reading_logs"') && str_contains($sql, 'order by "date_read" asc, "id" asc')) {
            $completionHistoryQueries++;
        }
    });

    $rerun = app(AchievementService::class)->evaluateAndAward($user);

    expect($rerun['awarded'])->toBe(0)
        ->and($rerun['updated_completion_dates'])->toBe(0)
        ->and($completionHistoryQueries)->toBe(0);

    $fourthCompletionDate = achievement_backfill_genesis_completions($user, 4, 4)[4];
    $this->travelTo('2026-12-31 10:00:00');
    $evaluation = app(AchievementService::class)->evaluateAndAward($user);
    $newBookCompletions = $evaluation['awarded_achievements']->where('achievement_key', 'book_completed');
    $fourthCompletion = $newBookCompletions->first();

    expect($newBookCompletions)->toHaveCount(1)
        ->and($fourthCompletion->context_key)->toBe('book:1:completion:4')
        ->and($fourthCompletion->earned_at->toDateString())->toBe('2026-12-31')
        ->and($fourthCompletion->completed_on->toDateString())->toBe($fourthCompletionDate);

    $completionLog = $user->readingLogs()->where('book_id', 1)->where('chapter', 50)->whereDate('date_read', $fourthCompletionDate)->first();
    $celebration = app(AchievementService::class)->getCelebrationPayload($user, collect([$fourthCompletion]), $completionLog, false);

    expect($celebration['earned'][0]['display_name'])->toBe('Completed Genesis for the 4th time');
});

it('can backfill a single user', function () {
    $target = achievement_backfill_user_with_john();
    $other = achievement_backfill_user_with_john();

    $this->artisan("achievements:backfill {$target->id}")
        ->expectsOutput('Users scanned: 1')
        ->assertSuccessful();

    expect($target->achievements()->count())->toBeGreaterThan(0)
        ->and($other->achievements()->count())->toBe(0);
});

it('does not backfill first week for seven distinct non consecutive reading days', function () {
    $user = User::factory()->create();

    foreach (range(0, 6) as $offset) {
        ReadingLog::factory()->for($user)->create([
            'book_id' => 1,
            'chapter' => $offset + 1,
            'passage_text' => 'Genesis '.($offset + 1),
            'date_read' => today()->subDays($offset * 2)->toDateString(),
        ]);
    }

    $this->artisan("achievements:backfill {$user->id}")
        ->expectsOutput('Users scanned: 1')
        ->assertSuccessful();

    expect($user->achievements()->where('achievement_key', 'first_week')->exists())->toBeFalse()
        ->and($user->achievements()->where('achievement_key', 'reading_streak_7')->exists())->toBeFalse();
});

it('does not backfill personal best streak records', function () {
    $user = User::factory()->create();

    foreach (range(0, 29) as $offset) {
        ReadingLog::factory()->for($user)->create([
            'book_id' => 1,
            'chapter' => $offset + 1,
            'passage_text' => 'Genesis '.($offset + 1),
            'date_read' => today()->subDays(29 - $offset)->toDateString(),
        ]);
    }

    $this->artisan("achievements:backfill {$user->id}")
        ->expectsOutput('Users scanned: 1')
        ->assertSuccessful();

    expect($user->achievements()->where('achievement_key', 'reading_streak_7')->exists())->toBeTrue()
        ->and($user->achievements()->where('achievement_key', 'reading_streak_30')->exists())->toBeTrue()
        ->and($user->achievements()->where('achievement_key', 'personal_best_streak')->exists())->toBeFalse();
});

it('does not backfill permanent weekly target streak achievements', function () {
    $user = User::factory()->create();
    $weekStart = Carbon::parse('2026-02-08');
    $chapter = 1;

    foreach (range(0, 11) as $weekOffset) {
        foreach ([0, 1, 3, 5] as $dayOffset) {
            ReadingLog::factory()->for($user)->create([
                'book_id' => 1,
                'chapter' => $chapter,
                'passage_text' => "Genesis {$chapter}",
                'date_read' => $weekStart->copy()->addWeeks($weekOffset)->addDays($dayOffset)->toDateString(),
            ]);
            $chapter++;
        }
    }

    $this->artisan("achievements:backfill {$user->id}")
        ->expectsOutput('Users scanned: 1')
        ->assertSuccessful();

    expect($user->achievements()->where('achievement_key', 'weekly_consistency_4')->exists())->toBeFalse()
        ->and($user->achievements()->where('achievement_key', 'weekly_consistency_8')->exists())->toBeFalse()
        ->and($user->achievements()->where('achievement_key', 'weekly_consistency_12')->exists())->toBeFalse();
});

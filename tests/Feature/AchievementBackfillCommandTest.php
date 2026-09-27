<?php

use App\Models\ReadingLog;
use App\Models\User;
use App\Models\UserAchievement;
use App\Services\AchievementService;
use App\Services\BibleReferenceService;
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

it('dates an earned deuterocanonical completion after opt out without awarding another', function () {
    $user = User::factory()->create();

    foreach (range(1, 14) as $chapter) {
        ReadingLog::factory()->for($user)->create([
            'book_id' => 67,
            'chapter' => $chapter,
            'passage_text' => "Tobit {$chapter}",
            'date_read' => $chapter === 14 ? '2026-02-17' : '2026-02-01',
        ]);
        ReadingLog::factory()->for($user)->create([
            'book_id' => 67,
            'chapter' => $chapter,
            'passage_text' => "Tobit {$chapter}",
            'date_read' => '2026-03-01',
        ]);
    }

    $award = UserAchievement::factory()->for($user)->create([
        'achievement_key' => 'book_completed',
        'context_key' => 'book:67',
        'category' => 'books',
        'display_name' => 'Completed Tobit',
        'description' => 'You completed Tobit.',
        'metadata' => ['book_id' => 67, 'book_name' => 'Tobit', 'completion_number' => 1],
    ]);

    $this->artisan("achievements:backfill {$user->id} --dry-run")
        ->expectsOutput('Would update completion dates: 1')
        ->assertSuccessful();

    expect($award->fresh()->completed_on)->toBeNull();

    $this->artisan("achievements:backfill {$user->id}")
        ->expectsOutput('Completion dates updated: 1')
        ->assertSuccessful();

    expect($award->fresh()->completed_on->toDateString())->toBe('2026-02-17')
        ->and($user->achievements()->where('achievement_key', 'book_completed')->count())->toBe(1);
});

it('keeps a canonical Daniel completion date after opting in to the Catholic canon', function () {
    $user = User::factory()->create(['deuterocanonical_books_enabled_at' => now()]);

    foreach (range(1, 14) as $chapter) {
        ReadingLog::factory()->for($user)->create([
            'book_id' => 27,
            'chapter' => $chapter,
            'passage_text' => "Daniel {$chapter}",
            'date_read' => $chapter <= 12 ? '2026-02-01' : '2026-03-01',
        ]);
    }

    $award = UserAchievement::factory()->for($user)->create([
        'achievement_key' => 'book_completed',
        'context_key' => 'book:27',
        'category' => 'books',
        'metadata' => ['book_id' => 27, 'book_name' => 'Daniel'],
        'earned_at' => '2026-02-03 10:00:00',
    ]);

    $this->artisan("achievements:backfill {$user->id}")
        ->expectsOutput('Completion dates updated: 1')
        ->assertSuccessful();

    expect($award->fresh()->completed_on->toDateString())->toBe('2026-02-01')
        ->and($user->achievements()->where('context_key', 'book:27')->count())->toBe(1);
});

it('leaves an ambiguous Daniel award date unset after opting out', function () {
    $user = User::factory()->create();

    foreach (range(1, 14) as $chapter) {
        ReadingLog::factory()->for($user)->create([
            'book_id' => 27,
            'chapter' => $chapter,
            'passage_text' => "Daniel {$chapter}",
            'date_read' => $chapter <= 12 ? '2026-02-01' : '2026-03-01',
        ]);
    }

    $award = UserAchievement::factory()->for($user)->create([
        'achievement_key' => 'book_completed',
        'context_key' => 'book:27',
        'category' => 'books',
        'metadata' => ['book_id' => 27, 'book_name' => 'Daniel'],
        'earned_at' => '2026-03-02 10:00:00',
    ]);

    $this->artisan("achievements:backfill {$user->id}")
        ->expectsOutput('Completion dates updated: 0')
        ->assertSuccessful();

    expect($award->fresh()->completed_on)->toBeNull()
        ->and($user->achievements()->where('context_key', 'book:27')->count())->toBe(1);

    $historyQueries = 0;
    DB::listen(function (QueryExecuted $query) use (&$historyQueries): void {
        if (str_contains(strtolower($query->sql), 'ranked_readings')) {
            $historyQueries++;
        }
    });

    $evaluation = app(AchievementService::class)->evaluateAndAward($user);

    expect($evaluation['updated_completion_dates'])->toBe(0)
        ->and($award->fresh()->completed_on)->toBeNull()
        ->and($historyQueries)->toBe(0);
});

it('preserves a canonical Old Testament award date after opting in', function () {
    $user = User::factory()->create(['deuterocanonical_books_enabled_at' => now()]);
    $timestamps = now();
    $rows = [];

    foreach (app(BibleReferenceService::class)->listBibleBooks('old') as $book) {
        foreach (range(1, $book['chapters']) as $chapter) {
            $rows[] = [
                'user_id' => $user->id,
                'book_id' => $book['id'],
                'chapter' => $chapter,
                'passage_text' => "{$book['name']} {$chapter}",
                'date_read' => '2026-02-01',
                'created_at' => $timestamps,
                'updated_at' => $timestamps,
            ];
        }
    }

    foreach ([17 => range(11, 16), 27 => [13, 14]] as $bookId => $chapters) {
        foreach ($chapters as $chapter) {
            $rows[] = [
                'user_id' => $user->id,
                'book_id' => $bookId,
                'chapter' => $chapter,
                'passage_text' => "{$bookId} {$chapter}",
                'date_read' => '2026-03-01',
                'created_at' => $timestamps,
                'updated_at' => $timestamps,
            ];
        }
    }

    foreach (array_chunk($rows, 200) as $batch) {
        DB::table('reading_logs')->insert($batch);
    }

    $award = UserAchievement::factory()->for($user)->create([
        'achievement_key' => 'testament_completed',
        'context_key' => 'testament:old',
        'category' => 'testaments',
        'metadata' => ['testament' => 'old'],
        'earned_at' => '2026-02-03 10:00:00',
    ]);

    $this->artisan("achievements:backfill {$user->id}")
        ->expectsOutput('Completion dates updated: 1')
        ->assertSuccessful();

    expect($award->fresh()->completed_on->toDateString())->toBe('2026-02-01');
});

it('dates a retained Catholic Bible award from the Catholic chapters after opt out', function () {
    $user = User::factory()->create();
    $bibleReferenceService = app(BibleReferenceService::class);
    $canonicalChapterCounts = collect($bibleReferenceService->listBibleBooks())->pluck('chapters', 'id');
    $timestamps = now();
    $rows = [];

    foreach ($bibleReferenceService->listBibleBooks(includeDeuterocanonical: true) as $book) {
        foreach (range(1, $book['chapters']) as $chapter) {
            $rows[] = [
                'user_id' => $user->id,
                'book_id' => $book['id'],
                'chapter' => $chapter,
                'passage_text' => "{$book['name']} {$chapter}",
                'date_read' => $chapter <= $canonicalChapterCounts->get($book['id'], 0) ? '2026-02-01' : '2026-03-01',
                'created_at' => $timestamps,
                'updated_at' => $timestamps,
            ];
        }
    }

    foreach (array_chunk($rows, 200) as $batch) {
        DB::table('reading_logs')->insert($batch);
    }

    $award = UserAchievement::factory()->for($user)->create([
        'achievement_key' => 'bible_completed',
        'context_key' => 'bible:with-deuterocanonical:completion:1',
        'category' => 'bible',
        'metadata' => ['collection_key' => 'with-deuterocanonical', 'completion_number' => 1],
        'earned_at' => '2026-03-02 10:00:00',
    ]);

    $this->artisan("achievements:backfill {$user->id}")
        ->expectsOutput('Completion dates updated: 1')
        ->assertSuccessful();

    expect($award->fresh()->completed_on->toDateString())->toBe('2026-03-01');

    $canonicalAward = $user->achievements()
        ->where('achievement_key', 'bible_completed')
        ->where('context_key', 'bible:canonical:completion:1')
        ->firstOrFail();

    expect($canonicalAward->completed_on->toDateString())->toBe('2026-02-01');
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

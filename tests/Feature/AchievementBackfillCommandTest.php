<?php

use App\Models\BookProgress;
use App\Models\ReadingLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    ReadingLog::factory()->for($user)->create([
        'book_id' => 43,
        'chapter' => 1,
        'passage_text' => 'John 1',
        'date_read' => today()->toDateString(),
    ]);

    BookProgress::factory()->for($user)->create([
        'book_id' => 43,
        'book_name' => 'John',
        'total_chapters' => 21,
        'chapters_read' => range(1, 21),
        'completion_percent' => 100,
        'is_completed' => true,
        'last_updated' => now(),
    ]);

    return $user;
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

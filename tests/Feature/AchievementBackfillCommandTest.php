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

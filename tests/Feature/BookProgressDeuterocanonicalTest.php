<?php

use App\Models\BookProgress;
use App\Models\ReadingLog;
use App\Models\User;
use App\Services\BookProgressService;
use App\Services\BookProgressSyncService;
use App\Services\UserStatisticsService;

function createProgressFor(User $user, array $attributes): BookProgress
{
    return BookProgress::factory()->for($user)->create($attributes);
}

it('uses canonical progress totals by default', function () {
    $user = User::factory()->create();

    $summary = app(UserStatisticsService::class)->getBookProgressSummary($user);

    expect($summary['total_bible_books'])->toBe(66)
        ->and($summary['books_not_started'])->toBe(66);
});

it('uses Catholic canon progress totals for opted-in users', function () {
    $user = User::factory()->create([
        'deuterocanonical_books_enabled_at' => now(),
    ]);

    $summary = app(UserStatisticsService::class)->getBookProgressSummary($user);
    $deuterocanonical = app(BookProgressService::class)->getTestamentProgress($user, 'Deuterocanonical');

    expect($summary['total_bible_books'])->toBe(73)
        ->and($summary['books_not_started'])->toBe(73)
        ->and($deuterocanonical['not_started_books'])->toBe(7)
        ->and($deuterocanonical['processed_books']->pluck('name')->all())->toContain('Tobit');
});

it('does not include existing deuterocanonical progress in canonical summaries after disabling', function () {
    $user = User::factory()->create();

    createProgressFor($user, [
        'book_id' => 67,
        'book_name' => 'Tobit',
        'total_chapters' => 14,
        'chapters_read' => [1],
        'completion_percent' => 7.14,
        'is_completed' => false,
    ]);

    $summary = app(UserStatisticsService::class)->getBookProgressSummary($user);

    expect($summary['total_bible_books'])->toBe(66)
        ->and($summary['books_in_progress'])->toBe(0)
        ->and($summary['books_not_started'])->toBe(66);
});

it('does not include Catholic additions to Esther and Daniel in canonical summaries after disabling', function () {
    $user = User::factory()->create();

    createProgressFor($user, [
        'book_id' => 27,
        'book_name' => 'Daniel',
        'total_chapters' => 14,
        'chapters_read' => [13],
        'completion_percent' => 7.14,
        'is_completed' => false,
    ]);

    $summary = app(UserStatisticsService::class)->getBookProgressSummary($user);

    expect($summary['total_bible_books'])->toBe(66)
        ->and($summary['books_in_progress'])->toBe(0)
        ->and($summary['books_not_started'])->toBe(66)
        ->and($summary['overall_progress_percent'])->toBe(0.0);
});

it('does not include Catholic additions to Esther and Daniel in the canonical progress grid after disabling', function () {
    $user = User::factory()->create();

    createProgressFor($user, [
        'book_id' => 27,
        'book_name' => 'Daniel',
        'total_chapters' => 14,
        'chapters_read' => [13],
        'completion_percent' => 7.14,
        'is_completed' => false,
    ]);

    $oldTestament = app(BookProgressService::class)->getTestamentProgress($user, 'Old');
    $daniel = $oldTestament['processed_books']->firstWhere('name', 'Daniel');

    expect($daniel['chapter_count'])->toBe(12)
        ->and($daniel['chapters_read'])->toBe(0)
        ->and($daniel['percentage'])->toBe(0.0)
        ->and($daniel['status'])->toBe('not-started')
        ->and($oldTestament['in_progress_books'])->toBe(0);
});

it('syncs Daniel progress against canonical totals for opted-out users', function () {
    $user = User::factory()->create();

    foreach (range(1, 13) as $chapter) {
        ReadingLog::factory()->for($user)->create([
            'book_id' => 27,
            'chapter' => $chapter,
            'passage_text' => "Daniel {$chapter}",
            'date_read' => today()->toDateString(),
        ]);
    }

    app(BookProgressSyncService::class)->syncBookProgressForUser($user);

    $progress = $user->bookProgress()->where('book_id', 27)->first();

    expect($progress)->not->toBeNull()
        ->and($progress->book_name)->toBe('Daniel')
        ->and($progress->total_chapters)->toBe(12)
        ->and($progress->chapters_read)->toBe(range(1, 12))
        ->and((float) $progress->completion_percent)->toBe(100.0)
        ->and($progress->is_completed)->toBeTrue();
});

it('keeps existing deuterocanonical book progress serviceable when syncing opted-out users', function () {
    $user = User::factory()->create();

    ReadingLog::factory()->for($user)->create([
        'book_id' => 67,
        'chapter' => 1,
        'passage_text' => 'Tobit 1',
        'date_read' => today()->toDateString(),
    ]);

    app(BookProgressSyncService::class)->syncBookProgressForUser($user);

    $progress = $user->bookProgress()->where('book_id', 67)->first();

    expect($progress)->not->toBeNull()
        ->and($progress->book_name)->toBe('Tobit')
        ->and($progress->total_chapters)->toBe(14)
        ->and($progress->chapters_read)->toBe([1])
        ->and((float) $progress->completion_percent)->toBe(7.14)
        ->and($progress->is_completed)->toBeFalse();
});

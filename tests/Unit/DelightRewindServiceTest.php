<?php

use App\Models\ReadingLog;
use App\Models\User;
use App\Services\BibleReferenceService;
use App\Services\DelightRewindService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('it calculates rewind stats correctly', function () {
    $user = User::factory()->create();
    $year = 2024;

    // Create logs
    // 1. Genesis (Book 1) - 3 chapters on Jan 1, 2, 3 (Streak 3)
    ReadingLog::factory()->create([
        'user_id' => $user->id,
        'book_id' => 1, // Genesis (Law)
        'chapter' => 1,
        'date_read' => "$year-01-01",
    ]);
    ReadingLog::factory()->create([
        'user_id' => $user->id,
        'book_id' => 1,
        'chapter' => 2,
        'date_read' => "$year-01-02",
    ]);
    ReadingLog::factory()->create([
        'user_id' => $user->id,
        'book_id' => 1,
        'chapter' => 3,
        'date_read' => "$year-01-03",
    ]);

    // 2. Matthew (Book 40) - 5 chapters on varying days in Feb (Gospels)
    // Feb 1 (Fri), Feb 8 (Fri), Feb 15 (Fri), Feb 22 (Fri), Feb 29 (Fri)
    // Most active day should be Friday if I align dates correctly.
    // Jan 1 2024 was Monday. Jan 2 Tue, Jan 3 Wed.
    // Feb 2 2024 was Friday.
    ReadingLog::factory()->create(['user_id' => $user->id, 'book_id' => 40, 'chapter' => 1, 'date_read' => "$year-02-02"]); // Fri
    ReadingLog::factory()->create(['user_id' => $user->id, 'book_id' => 40, 'chapter' => 2, 'date_read' => "$year-02-09"]); // Fri
    ReadingLog::factory()->create(['user_id' => $user->id, 'book_id' => 40, 'chapter' => 3, 'date_read' => "$year-02-16"]); // Fri
    ReadingLog::factory()->create(['user_id' => $user->id, 'book_id' => 40, 'chapter' => 4, 'date_read' => "$year-02-23"]); // Fri
    ReadingLog::factory()->create(['user_id' => $user->id, 'book_id' => 40, 'chapter' => 5, 'date_read' => "$year-03-01"]); // Fri

    // 3. Log from 2023 (should be ignored)
    ReadingLog::factory()->create([
        'user_id' => $user->id,
        'book_id' => 2,
        'chapter' => 1,
        'date_read' => ($year - 1) . "-12-31",
    ]);

    $service = app(DelightRewindService::class);
    $stats = $service->getRewindStats($user, $year);

    expect($stats['total_chapters'])->toBe(8) // 3 Genesis + 5 Matthew
        ->and($stats['total_books_read'])->toBe(2) // Genesis, Matthew
        ->and($stats['most_read_book']['name'])->toBe('Matthew')
        ->and($stats['most_read_testament']['name'])->toBe('New Testament') // 5 NT vs 3 OT
        ->and($stats['most_read_genre'])->toBe('Gospels') // 5 Gospels vs 3 Law
        ->and($stats['most_active_day'])->toBe('Friday') // 5 Fridays vs 1 Mon/Tue/Wed
        ->and($stats['longest_streak'])->toBe(3); // Jan 1-3
});

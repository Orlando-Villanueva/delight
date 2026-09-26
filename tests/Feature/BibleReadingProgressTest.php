<?php

use App\Models\ReadingLog;
use App\Models\User;
use App\Models\UserAchievement;
use App\Services\AchievementService;
use App\Services\BibleReferenceService;
use App\Services\BookProgressService;
use App\Services\BookProgressSyncService;
use App\Services\ReadingLogService;
use Illuminate\Support\Facades\DB;

it('uses canonical progress totals by default', function () {
    $user = User::factory()->create();

    $progress = app(BookProgressService::class)->getOverallProgress($user);

    expect($progress['total_books'])->toBe(66)
        ->and($progress['not_started_books'])->toBe(66);
});

it('uses Catholic canon progress totals for opted-in users', function () {
    $user = User::factory()->create([
        'deuterocanonical_books_enabled_at' => now(),
    ]);

    $progress = app(BookProgressService::class)->getOverallProgress($user);
    $deuterocanonical = $progress['deuterocanonical'];

    expect($progress['total_books'])->toBe(73)
        ->and($progress['not_started_books'])->toBe(73)
        ->and($deuterocanonical['not_started_books'])->toBe(7)
        ->and($deuterocanonical['processed_books']->pluck('name')->all())->toContain('Tobit');
});

it('excludes deuterocanonical reading logs from canonical summaries when disabled', function () {
    $user = User::factory()->create();

    ReadingLog::factory()->for($user)->create([
        'book_id' => 67,
        'chapter' => 1,
        'passage_text' => 'Tobit 1',
        'date_read' => today()->toDateString(),
    ]);

    $progress = app(BookProgressService::class)->getOverallProgress($user);

    expect($progress['total_books'])->toBe(66)
        ->and($progress['in_progress_books'])->toBe(0)
        ->and($progress['not_started_books'])->toBe(66);
});

it('excludes Daniel 13 from canonical progress when disabled', function () {
    $user = User::factory()->create();

    ReadingLog::factory()->for($user)->create([
        'book_id' => 27,
        'chapter' => 13,
        'passage_text' => 'Daniel 13',
        'date_read' => today()->toDateString(),
    ]);

    $progress = app(BookProgressService::class)->getOverallProgress($user);

    $oldTestament = $progress['old_testament'];
    $daniel = $oldTestament['processed_books']->firstWhere('name', 'Daniel');

    expect($progress['total_books'])->toBe(66)
        ->and($progress['in_progress_books'])->toBe(0)
        ->and($progress['not_started_books'])->toBe(66)
        ->and($progress['first_coverage_percent'])->toBe(0.0)
        ->and($daniel['chapter_count'])->toBe(12)
        ->and($daniel['chapters_read'])->toBe(0)
        ->and($daniel['percentage'])->toBe(0.0)
        ->and($daniel['status'])->toBe('not-started')
        ->and($oldTestament['in_progress_books'])->toBe(0);
});

it('derives Bible and book completion counts from dated chapter readings', function () {
    $user = User::factory()->create();
    $bibleReferenceService = app(BibleReferenceService::class);
    $firstReadDate = now()->subYear()->startOfDay();
    $timestamps = now();
    $rows = [];

    foreach ($bibleReferenceService->listBibleBooks() as $book) {
        foreach (range(1, $book['chapters']) as $chapter) {
            $rows[] = [
                'user_id' => $user->id,
                'book_id' => $book['id'],
                'chapter' => $chapter,
                'passage_text' => "{$book['name']} {$chapter}",
                'date_read' => $firstReadDate->toDateString(),
                'created_at' => $timestamps,
                'updated_at' => $timestamps,
            ];
        }
    }

    $genesis = $bibleReferenceService->getBibleBook(1);
    foreach (range(1, 4) as $pass) {
        foreach (range(1, $genesis['chapters']) as $chapter) {
            $rows[] = [
                'user_id' => $user->id,
                'book_id' => 1,
                'chapter' => $chapter,
                'passage_text' => "Genesis {$chapter}",
                'date_read' => $firstReadDate->copy()->addDays($pass)->toDateString(),
                'created_at' => $timestamps,
                'updated_at' => $timestamps,
            ];
        }
    }

    foreach (array_chunk($rows, 200) as $batch) {
        DB::table('reading_logs')->insert($batch);
    }

    $progressService = app(BookProgressService::class);
    $progress = $progressService->getOverallProgress($user);
    $genesisProgress = $progress['old_testament']['processed_books']->firstWhere('book_id', 1);
    $totalChapters = $bibleReferenceService->getChapterCount();

    expect($progress['bible_completions'])->toBe(1)
        ->and($progress['next_completion_chapters'])->toBe($genesis['chapters'])
        ->and($progress['next_completion_target'])->toBe($totalChapters)
        ->and($progress['next_completion_progress_percent'])->toBe(round(($genesis['chapters'] / $totalChapters) * 100, 1))
        ->and($progress['first_coverage_percent'])->toBe(100.0)
        ->and($genesisProgress['completed_count'])->toBe(5)
        ->and($genesisProgress['next_completion_chapters'])->toBe(0);

    app(AchievementService::class)->evaluateAndAward($user);
    $firstMonth = config('achievements.definitions.first_month');
    UserAchievement::factory()->for($user)->create([
        'achievement_key' => 'first_month',
        'context_key' => 'reading-days:30',
        'category' => $firstMonth['category'],
        'display_name' => $firstMonth['display_name'],
        'description' => $firstMonth['description'],
        'icon' => $firstMonth['icon'],
        'style' => $firstMonth['style'],
        'sort_order' => $firstMonth['sort_order'],
    ]);
    $milestone = app(AchievementService::class)->getDashboardMilestone($user)['milestone'];

    expect($milestone)->toMatchArray([
        'achievement_key' => 'bible_completion',
        'context_key' => 'bible:completion:2',
        'display_name' => 'Complete the Bible again',
        'current' => $genesis['chapters'],
        'target' => $totalChapters,
    ]);

    DB::table('reading_logs')
        ->where('user_id', $user->id)
        ->where('book_id', 2)
        ->where('chapter', 1)
        ->delete();

    expect($progressService->getOverallProgress($user)['bible_completions'])->toBe(0)
        ->and($user->achievements()
            ->where('achievement_key', 'book_completed')
            ->where('context_key', 'book:2')
            ->exists())->toBeTrue();
});

it('applies the current canon to completion totals while retaining dated readings', function () {
    $user = User::factory()->create();
    ReadingLog::factory()->for($user)->create([
        'book_id' => 67,
        'chapter' => 1,
        'passage_text' => 'Tobit 1',
        'date_read' => today()->toDateString(),
    ]);
    $bibleReferenceService = app(BibleReferenceService::class);
    $canonical = app(BookProgressService::class)->getOverallProgress($user);

    $user->forceFill(['deuterocanonical_books_enabled_at' => now()])->save();
    $catholic = app(BookProgressService::class)->getOverallProgress($user);

    expect($canonical['first_coverage_chapters'])->toBe(0)
        ->and($canonical['next_completion_target'])->toBe($bibleReferenceService->getChapterCount())
        ->and($catholic['first_coverage_chapters'])->toBe(1)
        ->and($catholic['next_completion_target'])->toBe($bibleReferenceService->getChapterCount(true))
        ->and($catholic['deuterocanonical']['processed_books']->firstWhere('book_id', 67)['chapters_read'])->toBe(1);
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

it('updates book progress from existing logs using the user canon', function () {
    $user = User::factory()->create();
    $readingLogService = app(ReadingLogService::class);

    foreach (range(1, 13) as $chapter) {
        $log = ReadingLog::factory()->for($user)->create([
            'book_id' => 27,
            'chapter' => $chapter,
            'passage_text' => "Daniel {$chapter}",
            'date_read' => today()->toDateString(),
        ]);

        $readingLogService->updateBookProgressFromLog($log);
    }

    $progress = $user->bookProgress()->where('book_id', 27)->first();

    expect($progress)->not->toBeNull()
        ->and($progress->total_chapters)->toBe(12)
        ->and($progress->chapters_read)->toBe(range(1, 12))
        ->and((float) $progress->completion_percent)->toBe(100.0)
        ->and($progress->is_completed)->toBeTrue();
});

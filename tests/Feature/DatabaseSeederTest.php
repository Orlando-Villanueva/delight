<?php

use App\Models\ReadingLog;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-05-10 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('seeds focused reading history with current achievements for the primary sample reader', function () {
    $existingUser = User::factory()->create([
        'email' => 'existing.reader@example.com',
    ]);

    ReadingLog::factory()->for($existingUser)->create([
        'book_id' => 1,
        'chapter' => 1,
        'passage_text' => 'Genesis 1',
        'date_read' => today()->toDateString(),
    ]);

    $this->seed(DatabaseSeeder::class);

    $seedUser = User::query()->where('email', 'seed.user@example.com')->firstOrFail();
    $seedUserTwo = User::query()->where('email', 'seed.user2@example.com')->firstOrFail();
    $newSeedUser = User::query()->where('email', 'seed.user.new@example.com')->firstOrFail();
    $genesisChapterReadings = $seedUser->readingLogs()->where('book_id', 1)->get()->groupBy('chapter');
    $exodusChapterReadings = $seedUser->readingLogs()->where('book_id', 2)->get()->groupBy('chapter');
    $genesisAchievement = $seedUser->achievements()
        ->where('achievement_key', 'book_completed')
        ->where('context_key', 'book:1')
        ->first();
    $genesisRepeatAchievement = $seedUser->achievements()
        ->where('achievement_key', 'book_completed')
        ->where('context_key', 'book:1:completion:2')
        ->first();
    $booksTouched = $seedUser->readingLogs()->distinct()->count('book_id');

    expect($seedUser->achievements()->count())->toBeGreaterThan(0)
        ->and($seedUserTwo->achievements()->count())->toBeGreaterThan(0)
        ->and($newSeedUser->achievements()->count())->toBe(0)
        ->and($existingUser->achievements()->count())->toBe(0)
        ->and($genesisChapterReadings->count())->toBe(50)
        ->and($genesisChapterReadings->every(fn ($readings): bool => $readings
            ->pluck('date_read')
            ->map(fn ($date): string => $date->toDateString())
            ->unique()
            ->count() === 2))->toBeTrue()
        ->and($genesisAchievement)->not->toBeNull()
        ->and($genesisRepeatAchievement)->not->toBeNull()
        ->and($exodusChapterReadings->count())->toBe(40)
        ->and($exodusChapterReadings->every(fn ($readings): bool => $readings
            ->pluck('date_read')
            ->map(fn ($date): string => $date->toDateString())
            ->unique()
            ->count() === 1))->toBeTrue()
        ->and($booksTouched)->toBeLessThan(10);

    expect($genesisAchievement->completed_on)->not->toBeNull()
        ->and($genesisAchievement->metadata['completion_number'])->toBe(1)
        ->and($genesisRepeatAchievement->completed_on)->not->toBeNull()
        ->and($genesisRepeatAchievement->metadata['completion_number'])->toBe(2);
});

it('continues seeding an incomplete book when existing logs include rereads', function () {
    $seedUser = User::factory()->create([
        'email' => 'seed.user@example.com',
    ]);

    foreach (range(1, 149) as $chapter) {
        ReadingLog::factory()->for($seedUser)->create([
            'book_id' => 19,
            'chapter' => $chapter,
            'passage_text' => "Psalms {$chapter}",
            'date_read' => '2025-08-01',
        ]);
    }

    ReadingLog::factory()->for($seedUser)->create([
        'book_id' => 19,
        'chapter' => 1,
        'passage_text' => 'Psalms 1',
        'date_read' => '2025-08-02',
    ]);

    $this->seed(DatabaseSeeder::class);

    expect($seedUser->readingLogs()->where('book_id', 19)->distinct()->count('chapter'))->toBe(150)
        ->and($seedUser->achievements()
            ->where('achievement_key', 'book_completed')
            ->where('context_key', 'book:19')
            ->exists())->toBeTrue();
});

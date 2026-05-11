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

it('backfills achievements only for seeded users', function () {
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

    expect($seedUser->achievements()->count())->toBeGreaterThan(0)
        ->and($seedUserTwo->achievements()->count())->toBeGreaterThan(0)
        ->and($newSeedUser->achievements()->count())->toBe(0)
        ->and($existingUser->achievements()->count())->toBe(0);
});

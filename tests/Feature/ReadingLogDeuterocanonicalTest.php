<?php

use App\Models\ReadingLog;
use App\Models\User;
use App\Services\ReadingLogService;
use App\Services\WeeklyGoalService;
use Carbon\Carbon;

const TOBIT_CHAPTER_ONE = 'Tobit 1';

it('does not show deuterocanonical books on the log form by default', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('logs.create'));

    $response->assertSuccessful()
        ->assertDontSee('Tobit');
});

it('shows deuterocanonical books on the log form for opted-in users', function () {
    $user = User::factory()->create([
        'deuterocanonical_books_enabled_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('logs.create'));

    $response->assertSuccessful()
        ->assertSee('Tobit')
        ->assertSee('Deuterocanonical')
        ->assertSee('Daniel 3 includes the Prayer of Azariah and Song of the Three Young Men')
        ->assertSee('Esther 11-16 are the Greek additions');
});

it('prevents disabled users from logging deuterocanonical books', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from(route('logs.create'))
        ->post(route('logs.store'), [
            'book_id' => 67,
            'start_chapter' => 1,
            'date_read' => today()->toDateString(),
        ]);

    $response->assertSuccessful()
        ->assertSee('The selected book id is invalid.');

    $this->assertDatabaseMissing('reading_logs', [
        'user_id' => $user->id,
        'book_id' => 67,
        'chapter' => 1,
    ]);
});

it('allows opted-in users to log deuterocanonical books and additions', function () {
    $user = User::factory()->create([
        'deuterocanonical_books_enabled_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('logs.store'), [
            'book_id' => 67,
            'start_chapter' => 1,
            'date_read' => today()->toDateString(),
        ])
        ->assertSuccessful()
        ->assertSee(TOBIT_CHAPTER_ONE);

    $this->actingAs($user)
        ->post(route('logs.store'), [
            'book_id' => 27,
            'start_chapter' => 13,
            'date_read' => today()->toDateString(),
        ])
        ->assertSuccessful()
        ->assertSee('Daniel 13');
});

it('keeps existing deuterocanonical logs visible and counted after disabling', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-28 09:00:00'));

    $user = User::factory()->create([
        'deuterocanonical_books_enabled_at' => now(),
    ]);

    ReadingLog::factory()->for($user)->create([
        'book_id' => 67,
        'chapter' => 1,
        'passage_text' => TOBIT_CHAPTER_ONE,
        'date_read' => today()->subDay()->toDateString(),
    ]);

    $user->forceFill(['deuterocanonical_books_enabled_at' => null])->save();

    $this->actingAs($user)
        ->get(route('logs.index'))
        ->assertSuccessful()
        ->assertSee(TOBIT_CHAPTER_ONE);

    expect(app(ReadingLogService::class)->calculateCurrentStreak($user))->toBe(1)
        ->and(app(WeeklyGoalService::class)->getThisWeekReadingDays($user))->toBe(1);

    Carbon::setTestNow();
});

<?php

use App\Models\ReadingLog;
use App\Models\User;
use Carbon\Carbon;

it('hides the day chapter count when the day total is one', function () {
    $user = User::factory()->create();
    $date = Carbon::parse('2026-08-11');

    ReadingLog::factory()->for($user)->create([
        'book_id' => 19,
        'chapter' => 123,
        'passage_text' => 'Psalms 123',
        'date_read' => $date->toDateString(),
        'notes_text' => 'A quiet psalm.',
        'created_at' => $date->copy()->setTime(8, 15),
    ]);

    $response = $this->actingAs($user)->get(route('logs.index'));

    $response->assertSuccessful()
        ->assertSee('Aug 11, 2026', false)
        ->assertSee('Psalms 123', false)
        ->assertSee('A quiet psalm.', false)
        ->assertSee('Logged at 8:15 AM', false)
        ->assertSee('aria-label="Edit note"', false)
        ->assertSee('aria-label="Delete reading"', false)
        ->assertDontSee('1 chapter', false)
        ->assertDontSee('chapters', false)
        ->assertDontSee('bg-primary-100 text-primary-800', false);
});

it('shows a muted day total for a multi-chapter range and repeats it on the passage line', function () {
    $user = User::factory()->create();
    $date = Carbon::parse('2026-08-11');
    $loggedAt = $date->copy()->setTime(7, 30);

    foreach (range(112, 114) as $chapter) {
        ReadingLog::factory()->for($user)->create([
            'book_id' => 19,
            'chapter' => $chapter,
            'passage_text' => "Psalms {$chapter}",
            'date_read' => $date->toDateString(),
            'notes_text' => null,
            'created_at' => $loggedAt,
            'updated_at' => $loggedAt,
        ]);
    }

    $response = $this->actingAs($user)->get(route('logs.index'));

    $response->assertSuccessful()
        ->assertSee('Aug 11, 2026', false)
        ->assertSee('Psalms 112-114', false)
        ->assertSee('3 chapters', false)
        ->assertSee('text-xs font-medium text-gray-500 dark:text-gray-400', false)
        ->assertSee('shrink-0 text-nowrap text-xs font-normal text-gray-500 dark:text-gray-400', false)
        ->assertSee('Logged at 7:30 AM', false)
        ->assertDontSee('1 chapter', false)
        ->assertDontSee('bg-primary-100 text-primary-800', false);

    expect(preg_match_all('/\b3 chapters\b/', $response->getContent()))->toBeGreaterThanOrEqual(2);
});

it('keeps a day total for mixed single-chapter entries and omits per-card counts', function () {
    $user = User::factory()->create();
    $date = Carbon::parse('2026-08-11');

    ReadingLog::factory()->for($user)->create([
        'book_id' => 19,
        'chapter' => 123,
        'passage_text' => 'Psalms 123',
        'date_read' => $date->toDateString(),
        'notes_text' => null,
        'created_at' => $date->copy()->setTime(8, 0),
    ]);

    ReadingLog::factory()->for($user)->create([
        'book_id' => 43,
        'chapter' => 4,
        'passage_text' => 'John 4',
        'date_read' => $date->toDateString(),
        'notes_text' => 'The woman at the well.',
        'created_at' => $date->copy()->setTime(9, 45),
    ]);

    $response = $this->actingAs($user)->get(route('logs.index'));

    $response->assertSuccessful()
        ->assertSee('Aug 11, 2026', false)
        ->assertSee('2 chapters', false)
        ->assertSee('Psalms 123', false)
        ->assertSee('John 4', false)
        ->assertSee('The woman at the well.', false)
        ->assertSee('Logged at 8:00 AM', false)
        ->assertSee('Logged at 9:45 AM', false)
        ->assertDontSee('1 chapter', false)
        ->assertDontSee('3 chapters', false);

    expect(substr_count($response->getContent(), '2 chapters'))->toBe(1)
        ->and(substr_count($response->getContent(), 'chapters'))->toBe(1);
});

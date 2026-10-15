<?php

use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-14 02:00:00', 'UTC'));
});

it('requires authentication for a JSON web submission', function () {
    $this->postJson(route('logs.store'), [])->assertUnauthorized();
    $this->assertDatabaseCount('reading_logs', 0);
});

it('returns fresh form defaults and server celebrations after saving a chapter range', function () {
    $user = User::factory()->create(['reading_timezone' => 'America/Toronto']);
    $otherUser = User::factory()->create();
    $otherUser->readingLogs()->create([
        'book_id' => 1,
        'chapter' => 1,
        'passage_text' => 'Genesis 1',
        'date_read' => '2026-10-13',
    ]);

    $response = $this->actingAs($user)->postJson(route('logs.store'), [
        'book_id' => 43,
        'start_chapter' => '1',
        'end_chapter' => '3',
        'date_read' => '2026-10-12',
        'notes_text' => 'Keep this reflection with the reading.',
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'John 1-3 recorded for Oct 12, 2026')
        ->assertJsonPath('reading', ['passage_text' => 'John 1-3', 'date_read' => '2026-10-12'])
        ->assertJsonPath('form.dates', ['today' => '2026-10-13', 'yesterday' => '2026-10-12'])
        ->assertJsonPath('form.values', [
            'book_id' => '', 'start_chapter' => '', 'end_chapter' => '',
            'date_read' => '2026-10-13', 'notes_text' => '',
        ])
        ->assertJsonCount(1, 'form.recent_books')
        ->assertJsonPath('form.recent_books.0.id', 43)
        ->assertJsonPath('celebration.reading.passage', 'John 1-3');

    expect(collect($response->json('form.books'))->pluck('id'))->toContain(43)->not->toContain(67);
    expect(collect($response->json('celebration.earned'))->pluck('display_name'))->toContain('First reading');
    expect($user->fresh()->celebrated_first_reading_at)->not->toBeNull();
    expect($user->readingLogs()->orderBy('chapter')->pluck('chapter')->all())->toBe([1, 2, 3]);
    $this->assertDatabaseHas('reading_logs', [
        'user_id' => $user->id, 'book_id' => 43, 'chapter' => 1,
        'date_read' => '2026-10-12 00:00:00', 'notes_text' => 'Keep this reflection with the reading.',
    ]);
});

it('saves an opted-in addition as a single chapter and returns the available canon', function () {
    $user = User::factory()->create([
        'reading_timezone' => 'America/Toronto',
        'deuterocanonical_books_enabled_at' => now(),
    ]);

    $response = $this->actingAs($user)->postJson(route('logs.store'), [
        'book_id' => 27, 'start_chapter' => 13, 'date_read' => '2026-10-13',
    ]);

    $response->assertOk()->assertJsonPath('reading.passage_text', 'Daniel 13');
    expect(collect($response->json('form.books'))->pluck('id'))->toContain(67);
    $this->assertDatabaseCount('reading_logs', 1);
    $this->assertDatabaseHas('reading_logs', ['user_id' => $user->id, 'book_id' => 27, 'chapter' => 13]);
});

it('returns 422 field errors without writing invalid JSON submissions', function (array $values, array $fields) {
    $user = User::factory()->create(['reading_timezone' => 'America/Toronto']);

    $response = $this->actingAs($user)->postJson(route('logs.store'), array_replace([
        'book_id' => 43, 'start_chapter' => 1, 'date_read' => '2026-10-13',
    ], $values));

    $response->assertUnprocessable()->assertJsonValidationErrors($fields);
    $this->assertDatabaseCount('reading_logs', 0);
})->with([
    'required fields' => [['book_id' => null, 'start_chapter' => null, 'date_read' => null], ['book_id', 'start_chapter', 'date_read']],
    'malformed arrays' => [['book_id' => [43], 'start_chapter' => [1], 'notes_text' => ['reflection']], ['book_id', 'start_chapter', 'notes_text']],
    'reversed range' => [['start_chapter' => 3, 'end_chapter' => 1], ['start_chapter']],
    'out-of-book range' => [['start_chapter' => 22], ['start_chapter']],
    'excluded canon' => [['book_id' => 67], ['book_id']],
    'unavailable date' => [['date_read' => '2026-10-14'], ['date_read']],
    'overlong note' => [['notes_text' => str_repeat('a', 1001)], ['notes_text']],
]);

it('returns the existing range message as a JSON field error', function () {
    $user = User::factory()->create(['reading_timezone' => 'America/Toronto']);

    $this->actingAs($user)->postJson(route('logs.store'), [
        'book_id' => 43, 'start_chapter' => 3, 'end_chapter' => 1, 'date_read' => '2026-10-13',
    ])->assertUnprocessable()->assertJsonPath('errors.start_chapter.0', 'Invalid chapter range for the selected book.');
    $this->assertDatabaseCount('reading_logs', 0);
});

it('returns 422 for a repeated save without adding chapters or repeating celebrations', function () {
    $user = User::factory()->create(['reading_timezone' => 'America/Toronto']);
    $values = ['book_id' => 43, 'start_chapter' => 1, 'date_read' => '2026-10-12'];

    $this->actingAs($user)->postJson(route('logs.store'), $values)->assertOk();
    $achievementCount = $user->achievements()->count();

    $this->postJson(route('logs.store'), $values)->assertUnprocessable()
        ->assertJsonPath('errors.start_chapter.0', 'You have already logged one or more of these chapters for yesterday.')
        ->assertJsonMissingPath('celebration');

    $this->assertDatabaseCount('reading_logs', 1);
    expect($user->achievements()->count())->toBe($achievementCount);
});

it('keeps AJAX HTMX submissions on the HTML response contract', function () {
    $user = User::factory()->create(['reading_timezone' => 'America/Toronto']);

    $this->actingAs($user)->post(route('logs.store'), [
        'book_id' => 43, 'start_chapter' => 1, 'date_read' => '2026-10-13',
    ], ['HX-Request' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => '*/*'])
        ->assertOk()->assertHeader('HX-Trigger', 'readingLogAdded')
        ->assertSee('id="reading-log-form-container"', false)->assertSee('John 1 recorded');
    $this->assertDatabaseCount('reading_logs', 1);
});

it('returns only base field errors before evaluating chapter ranges', function () {
    $user = User::factory()->create(['reading_timezone' => 'America/Toronto']);

    $this->actingAs($user)->postJson(route('logs.store'), [
        'book_id' => 43, 'start_chapter' => 3, 'end_chapter' => 1, 'date_read' => '2026-10-14',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('date_read')
        ->assertJsonMissingValidationErrors(['start_chapter', 'end_chapter']);
    $this->assertDatabaseCount('reading_logs', 0);
});

it('preserves the submitted draft in HTML after form request validation fails', function (array $headers, bool $fragment) {
    $user = User::factory()->create(['reading_timezone' => 'America/Toronto']);

    $response = $this->actingAs($user)->post(route('logs.store'), [
        'book_id' => 43, 'start_chapter' => 3, 'end_chapter' => 1,
        'date_read' => '2026-10-12', 'notes_text' => 'Keep this draft.',
    ], $headers);

    $response->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8')
        ->assertSee('Invalid chapter range for the selected book.')
        ->assertSee("initialBookId: '43'", false)
        ->assertSee('value="3"', false)->assertSee('value="1"', false)
        ->assertSee('Keep this draft.');
    expect($response->getContent())->toMatch('/<input[^>]+id="yesterday"[^>]+checked/s');
    expect(str_contains($response->getContent(), '<!DOCTYPE html>'))->toBe(! $fragment);
    $this->assertDatabaseCount('reading_logs', 0);
})->with([
    'direct web submission' => [[], false],
    'htmx AJAX submission' => [['HX-Request' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => '*/*'], true],
]);

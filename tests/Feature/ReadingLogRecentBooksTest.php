<?php

use App\Models\ReadingLog;
use App\Models\User;
use Carbon\Carbon;

if (! function_exists('createRecentBookLog')) {
    function createRecentBookLog(User $user, int $bookId, string $dateRead, string $createdAt, int $chapter = 1): ReadingLog
    {
        return ReadingLog::factory()->for($user)->create([
            'book_id' => $bookId,
            'chapter' => $chapter,
            'passage_text' => "Book {$bookId} {$chapter}",
            'date_read' => $dateRead,
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ]);
    }
}

it('rejects malformed array inputs in the JSON reader form', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson(route('logs.store'), [
        'book_id' => [43], 'start_chapter' => ['10'], 'end_chapter' => ['5'],
        'date_read' => today()->toDateString(), 'notes_text' => ['Reflection'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['book_id', 'start_chapter', 'end_chapter', 'notes_text']);
    $this->assertDatabaseCount('reading_logs', 0);
});

it('does not render recent book suggestions when the user has no reading history', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('logs.create'))
        ->assertSuccessful();
    expect(readingLogPageProps($this->get(route('logs.create')))['recentBooks'])->toBe([]);
});

it('renders up to three distinct recent books ordered by latest reading recency', function () {
    $user = User::factory()->create();

    createRecentBookLog($user, 19, '2026-06-20', '2026-06-20 08:00:00');
    createRecentBookLog($user, 43, '2026-06-23', '2026-06-23 09:00:00');
    createRecentBookLog($user, 40, '2026-06-23', '2026-06-23 10:00:00');
    createRecentBookLog($user, 1, '2026-06-22', '2026-06-22 08:00:00');
    createRecentBookLog($user, 1, '2026-06-24', '2026-06-24 08:00:00', 2);

    $response = $this->actingAs($user)->get(route('logs.create'));

    $response->assertSuccessful();
    $props = readingLogPageProps($response);
    expect(array_column($props['recentBooks'], 'id'))->toBe([1, 40, 43]);
    expect($props['values']['book_id'])->toBe('');
    $response->assertDontSee('data-reading-log-island', false);

});

it('renders only the available recent books when fewer than three distinct books exist', function () {
    $user = User::factory()->create();

    createRecentBookLog($user, 1, '2026-06-23', '2026-06-23 08:00:00');
    createRecentBookLog($user, 43, '2026-06-24', '2026-06-24 08:00:00');

    $response = $this->actingAs($user)->get(route('logs.create'));

    $response->assertSuccessful();
    expect(array_column(readingLogPageProps($response)['recentBooks'], 'id'))->toBe([43, 1]);

});

it('filters deuterocanonical recent books by the current canon preference before limiting', function () {
    $user = User::factory()->create();

    createRecentBookLog($user, 67, '2026-06-24', '2026-06-24 12:00:00');
    createRecentBookLog($user, 1, '2026-06-23', '2026-06-23 12:00:00');
    createRecentBookLog($user, 40, '2026-06-22', '2026-06-22 12:00:00');
    createRecentBookLog($user, 43, '2026-06-21', '2026-06-21 12:00:00');

    $response = $this->actingAs($user)->get(route('logs.create'))->assertSuccessful();
    expect(array_column(readingLogPageProps($response)['recentBooks'], 'id'))->toBe([1, 40, 43]);

    $user->forceFill(['deuterocanonical_books_enabled_at' => now()])->save();
    $response = $this->actingAs($user)->get(route('logs.create'))->assertSuccessful();
    expect(array_column(readingLogPageProps($response)['recentBooks'], 'id'))->toBe([67, 1, 40]);

});

it('returns validation errors without writing a failed reading', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('logs.store'), [
        'book_id' => 43, 'start_chapter' => '10', 'end_chapter' => '5',
        'date_read' => today()->toDateString(), 'notes_text' => 'Keep this reflection visible.',
    ])->assertUnprocessable()->assertJsonValidationErrors('start_chapter');
    $this->assertDatabaseCount('reading_logs', 0);
});

it('refreshes suggestions and resets chapter and note inputs after a successful JSON submission', function () {
    $user = User::factory()->create();
    createRecentBookLog($user, 1, '2026-06-22', '2026-06-22 08:00:00');
    createRecentBookLog($user, 43, '2026-06-23', '2026-06-23 08:00:00');

    $response = $this->actingAs($user)->postJson(route('logs.store'), [
        'book_id' => 40, 'start_chapter' => '4', 'date_read' => today()->toDateString(),
        'notes_text' => 'Do not carry this forward.',
    ]);

    $response->assertOk()->assertJsonPath('reading.passage_text', 'Matthew 4')
        ->assertJsonPath('form.values.start_chapter', '')->assertJsonPath('form.values.notes_text', '');
    expect(array_column($response->json('form.recent_books'), 'id'))->toBe([40, 43, 1]);
});

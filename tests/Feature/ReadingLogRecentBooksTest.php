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

it('drops malformed array inputs when preserving values after htmx validation replacement', function () {
    $user = User::factory()->create();

    createRecentBookLog($user, 43, '2026-06-24', '2026-06-24 08:00:00');

    $response = $this->actingAs($user)->post(route('logs.store'), [
        'book_id' => [43],
        'start_chapter' => ['10'],
        'end_chapter' => ['5'],
        'date_read' => today()->toDateString(),
        'notes_text' => ['Keep this reflection visible.'],
    ], ['HX-Request' => 'true']);

    $response->assertSuccessful()
        ->assertSee('data-reading-log-island', false)
        ->assertDontSee('Array');

    expect($response->getContent())->toMatch('/<input[^>]+name="start_chapter"[^>]+value=""/s')
        ->and($response->getContent())->toMatch('/<input[^>]+name="end_chapter"[^>]+value=""/s');
});

it('does not render recent book suggestions when the user has no reading history', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('logs.create'))
        ->assertSuccessful()
        ->assertSee('data-reading-log-island', false)
        ->assertSee('&quot;recentBooks&quot;:[]', false);
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
    $props = readingLogIslandProps($response);
    expect(array_column($props['recentBooks'], 'id'))->toBe([1, 40, 43]);
    expect($props['values']['book_id'])->toBe('');
    $response->assertSee('data-reading-log-island', false)
        ->assertSee('x-ignore hx-disable hx-history="false"', false)
        ->assertDontSee('readingLogForm(');

});

it('renders only the available recent books when fewer than three distinct books exist', function () {
    $user = User::factory()->create();

    createRecentBookLog($user, 1, '2026-06-23', '2026-06-23 08:00:00');
    createRecentBookLog($user, 43, '2026-06-24', '2026-06-24 08:00:00');

    $response = $this->actingAs($user)->get(route('logs.create'));

    $response->assertSuccessful();
    expect(array_column(readingLogIslandProps($response)['recentBooks'], 'id'))->toBe([43, 1]);

});

it('filters deuterocanonical recent books by the current canon preference before limiting', function () {
    $user = User::factory()->create();

    createRecentBookLog($user, 67, '2026-06-24', '2026-06-24 12:00:00');
    createRecentBookLog($user, 1, '2026-06-23', '2026-06-23 12:00:00');
    createRecentBookLog($user, 40, '2026-06-22', '2026-06-22 12:00:00');
    createRecentBookLog($user, 43, '2026-06-21', '2026-06-21 12:00:00');

    $response = $this->actingAs($user)->get(route('logs.create'))->assertSuccessful();
    expect(array_column(readingLogIslandProps($response)['recentBooks'], 'id'))->toBe([1, 40, 43]);

    $user->forceFill(['deuterocanonical_books_enabled_at' => now()])->save();
    $response = $this->actingAs($user)->get(route('logs.create'))->assertSuccessful();
    expect(array_column(readingLogIslandProps($response)['recentBooks'], 'id'))->toBe([67, 1, 40]);

});

it('preserves selected book, chapter input, notes, and suggestions after htmx validation replacement', function () {
    $user = User::factory()->create();

    createRecentBookLog($user, 1, '2026-06-23', '2026-06-23 08:00:00');
    createRecentBookLog($user, 43, '2026-06-24', '2026-06-24 08:00:00');

    $response = $this->actingAs($user)->post(route('logs.store'), [
        'book_id' => 43,
        'start_chapter' => '10',
        'end_chapter' => '5',
        'date_read' => today()->toDateString(),
        'notes_text' => 'Keep this reflection visible.',
    ], ['HX-Request' => 'true']);

    $response->assertSuccessful()
        ->assertSee('Invalid chapter range')
        ->assertSee('value="10"', false)
        ->assertSee('value="5"', false)
        ->assertSee('Keep this reflection visible.');
});

it('refreshes suggestions and resets chapter and note inputs after a successful htmx submission', function () {
    $user = User::factory()->create();

    createRecentBookLog($user, 1, '2026-06-22', '2026-06-22 08:00:00');
    createRecentBookLog($user, 43, '2026-06-23', '2026-06-23 08:00:00');

    $response = $this->actingAs($user)->post(route('logs.store'), [
        'book_id' => 40,
        'start_chapter' => '4',
        'date_read' => today()->toDateString(),
        'notes_text' => 'Do not carry this forward.',
    ], ['HX-Request' => 'true']);

    $response->assertSuccessful()
        ->assertHeader('HX-Trigger', 'readingLogAdded')
        ->assertSee('Matthew 4 recorded')
        ->assertDontSee('Do not carry this forward.');

    expect(array_column(readingLogIslandProps($response)['recentBooks'], 'id'))->toBe([40, 43, 1]);
    expect($response->getContent())->toMatch('/<input[^>]+name="start_chapter"[^>]+value=""/s');
});

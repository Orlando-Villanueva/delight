<?php

use App\Models\ReadingLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Testing\TestResponse;

const READING_HISTORY_DATE = '2026-08-11';
const READING_HISTORY_DATE_LABEL = 'Aug 11, 2026';
const READING_HISTORY_PSALMS_123 = 'Psalms 123';
const READING_HISTORY_JOHN_4 = 'John 4';
const READING_HISTORY_ONE_CHAPTER = '1 chapter';
const READING_HISTORY_THREE_CHAPTERS = '3 chapters';
const READING_HISTORY_FOUR_CHAPTERS = '4 chapters';
const READING_HISTORY_PSALMS_BOOK_ID = 19;
const READING_HISTORY_JOHN_BOOK_ID = 43;

function readingHistoryDate(): Carbon
{
    return Carbon::parse(READING_HISTORY_DATE);
}

function createReadingHistoryLog(User $user, array $attributes): ReadingLog
{
    return ReadingLog::factory()->for($user)->create(array_merge([
        'date_read' => READING_HISTORY_DATE,
        'notes_text' => null,
    ], $attributes));
}

function createReadingHistoryRange(User $user, int $bookId, string $bookName, int $startChapter, int $endChapter, Carbon $loggedAt): void
{
    foreach (range($startChapter, $endChapter) as $chapter) {
        createReadingHistoryLog($user, [
            'book_id' => $bookId,
            'chapter' => $chapter,
            'passage_text' => "{$bookName} {$chapter}",
            'created_at' => $loggedAt,
            'updated_at' => $loggedAt,
        ]);
    }
}

function getReadingHistory(User $user): TestResponse
{
    return test()->actingAs($user)->get(route('logs.index'));
}

function readingHistoryDayHeaderCountPattern(?string $countLabel = null): string
{
    $date = preg_quote(READING_HISTORY_DATE_LABEL, '/');

    if ($countLabel === null) {
        return '/<time[^>]*>\s*'.$date.'\s*<\/time>\s*<span/';
    }

    return '/<time[^>]*>\s*'.$date.'\s*<\/time>\s*<span[^>]*>\s*'.preg_quote($countLabel, '/').'\s*<\/span>/';
}

it('hides the day chapter count when the day total is one', function () {
    $user = User::factory()->create();

    createReadingHistoryLog($user, [
        'book_id' => READING_HISTORY_PSALMS_BOOK_ID,
        'chapter' => 123,
        'passage_text' => READING_HISTORY_PSALMS_123,
        'notes_text' => 'A quiet psalm.',
        'created_at' => readingHistoryDate()->setTime(8, 15),
    ]);

    $response = getReadingHistory($user);

    $response->assertSuccessful()
        ->assertSee(READING_HISTORY_DATE_LABEL, false)
        ->assertSee(READING_HISTORY_PSALMS_123, false)
        ->assertSee('A quiet psalm.', false)
        ->assertSee('Logged at 8:15 AM', false)
        ->assertSee('aria-label="Edit note"', false)
        ->assertSee('aria-label="Delete reading"', false)
        ->assertDontSee(READING_HISTORY_ONE_CHAPTER, false)
        ->assertDontSee('chapters', false)
        ->assertDontSee('bg-primary-100 text-primary-800', false);

    expect($response->getContent())->not->toMatch(readingHistoryDayHeaderCountPattern());
});

it('puts a multi-chapter range count on the passage line and omits the day total', function () {
    $user = User::factory()->create();

    createReadingHistoryRange(
        $user,
        READING_HISTORY_PSALMS_BOOK_ID,
        'Psalms',
        112,
        114,
        readingHistoryDate()->setTime(7, 30),
    );

    $response = getReadingHistory($user);

    $response->assertSuccessful()
        ->assertSee(READING_HISTORY_DATE_LABEL, false)
        ->assertSee('Psalms 112-114', false)
        ->assertSee(READING_HISTORY_THREE_CHAPTERS, false)
        ->assertSee('shrink-0 text-nowrap text-xs font-normal text-gray-500 dark:text-gray-400', false)
        ->assertSee('Logged at 7:30 AM', false)
        ->assertDontSee(READING_HISTORY_ONE_CHAPTER, false)
        ->assertDontSee('bg-primary-100 text-primary-800', false);

    expect($response->getContent())->not->toMatch(
        readingHistoryDayHeaderCountPattern(READING_HISTORY_THREE_CHAPTERS)
    );
});

it('keeps a day total for mixed single-chapter entries and omits per-card counts', function () {
    $user = User::factory()->create();

    createReadingHistoryLog($user, [
        'book_id' => READING_HISTORY_PSALMS_BOOK_ID,
        'chapter' => 123,
        'passage_text' => READING_HISTORY_PSALMS_123,
        'created_at' => readingHistoryDate()->setTime(8, 0),
    ]);

    createReadingHistoryLog($user, [
        'book_id' => READING_HISTORY_JOHN_BOOK_ID,
        'chapter' => 4,
        'passage_text' => READING_HISTORY_JOHN_4,
        'notes_text' => 'The woman at the well.',
        'created_at' => readingHistoryDate()->setTime(9, 45),
    ]);

    $response = getReadingHistory($user);

    $response->assertSuccessful()
        ->assertSee(READING_HISTORY_DATE_LABEL, false)
        ->assertSee('2 chapters', false)
        ->assertSee(READING_HISTORY_PSALMS_123, false)
        ->assertSee(READING_HISTORY_JOHN_4, false)
        ->assertSee('The woman at the well.', false)
        ->assertSee('Logged at 8:00 AM', false)
        ->assertSee('Logged at 9:45 AM', false)
        ->assertDontSee(READING_HISTORY_ONE_CHAPTER, false)
        ->assertDontSee(READING_HISTORY_THREE_CHAPTERS, false);

    expect($response->getContent())
        ->toMatch(readingHistoryDayHeaderCountPattern('2 chapters'))
        ->and(substr_count($response->getContent(), '2 chapters'))->toBe(1)
        ->and(substr_count($response->getContent(), 'chapters'))->toBe(1);
});

it('shows a day total for mixed entries and a passage-line count only on the range', function () {
    $user = User::factory()->create();

    createReadingHistoryRange(
        $user,
        READING_HISTORY_PSALMS_BOOK_ID,
        'Psalms',
        112,
        114,
        readingHistoryDate()->setTime(7, 30),
    );

    createReadingHistoryLog($user, [
        'book_id' => READING_HISTORY_JOHN_BOOK_ID,
        'chapter' => 4,
        'passage_text' => READING_HISTORY_JOHN_4,
        'created_at' => readingHistoryDate()->setTime(9, 45),
    ]);

    $response = getReadingHistory($user);

    $response->assertSuccessful()
        ->assertSee(READING_HISTORY_DATE_LABEL, false)
        ->assertSee(READING_HISTORY_FOUR_CHAPTERS, false)
        ->assertSee('Psalms 112-114', false)
        ->assertSee(READING_HISTORY_THREE_CHAPTERS, false)
        ->assertSee(READING_HISTORY_JOHN_4, false)
        ->assertSee('text-xs font-medium text-gray-500 dark:text-gray-400', false)
        ->assertSee('shrink-0 text-nowrap text-xs font-normal text-gray-500 dark:text-gray-400', false)
        ->assertDontSee(READING_HISTORY_ONE_CHAPTER, false);

    expect($response->getContent())
        ->toMatch(readingHistoryDayHeaderCountPattern(READING_HISTORY_FOUR_CHAPTERS))
        ->and(substr_count($response->getContent(), READING_HISTORY_FOUR_CHAPTERS))->toBe(1);
});

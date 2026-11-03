<?php

use App\Models\ReadingLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Testing\TestResponse;

const READING_HISTORY_DATE = '2026-08-11';
const READING_HISTORY_PSALMS_123 = 'Psalms 123';
const READING_HISTORY_JOHN_4 = 'John 4';
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

it('provides typed group data for a single reading and its note', function () {
    $user = User::factory()->create();
    $log = createReadingHistoryLog($user, [
        'book_id' => READING_HISTORY_PSALMS_BOOK_ID,
        'chapter' => 123,
        'passage_text' => READING_HISTORY_PSALMS_123,
        'notes_text' => 'A quiet psalm.',
        'created_at' => readingHistoryDate()->setTime(8, 15),
    ]);

    getReadingHistory($user)->assertInertia(fn ($page) => $page
        ->component('History')
        ->where('today', now()->toDateString())
        ->where('hasLogs', true)
        ->has('days', 1)
        ->where('days.0.date', READING_HISTORY_DATE)
        ->where('days.0.label', 'Aug 11, 2026')
        ->has('days.0.groups', 1)
        ->where('days.0.groups.0.id', $log->id)
        ->where('days.0.groups.0.passage', READING_HISTORY_PSALMS_123)
        ->where('days.0.groups.0.notes', 'A quiet psalm.')
        ->where('days.0.groups.0.timeLabel', '8:15 AM')
        ->where('days.0.groups.0.chapterCount', 1)
        ->where('days.0.groups.0.chapters.0.id', $log->id)
        ->where('days.0.groups.0.chapters.0.chapter', 123)
        ->where('days.0.groups.0.noteUrl', route('logs.notes.update', $log))
        ->where('days.0.groups.0.deleteUrl', route('logs.destroy', $log))
        ->where('days.0.groups.0.deleteChaptersUrl', route('logs.chapters.destroy', $log))
    );
});

it('groups a contiguous multi-chapter range into one History group', function () {
    $user = User::factory()->create();
    createReadingHistoryRange(
        $user,
        READING_HISTORY_PSALMS_BOOK_ID,
        'Psalms',
        112,
        114,
        readingHistoryDate()->setTime(7, 30),
    );

    getReadingHistory($user)->assertInertia(fn ($page) => $page
        ->component('History')
        ->has('days.0.groups', 1)
        ->where('days.0.groups.0.passage', 'Psalms 112-114')
        ->where('days.0.groups.0.chapterCount', 3)
        ->has('days.0.groups.0.chapters', 3)
        ->where('days.0.groups.0.chapters.0.chapter', 112)
        ->where('days.0.groups.0.chapters.2.chapter', 114)
    );
});

it('preserves separate groups and their notes within the same day', function () {
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

    getReadingHistory($user)->assertInertia(fn ($page) => $page
        ->component('History')
        ->has('days.0.groups', 2)
        ->where('days.0.groups.0.passage', READING_HISTORY_JOHN_4)
        ->where('days.0.groups.0.notes', 'The woman at the well.')
        ->where('days.0.groups.1.passage', READING_HISTORY_PSALMS_123)
        ->where('days.0.groups.0.chapterCount', 1)
        ->where('days.0.groups.1.chapterCount', 1)
    );
});

it('keeps the grouped chapter count when a day mixes a range and a separate reading', function () {
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

    getReadingHistory($user)->assertInertia(fn ($page) => $page
        ->component('History')
        ->has('days.0.groups', 2)
        ->where('days.0.groups.0.passage', READING_HISTORY_JOHN_4)
        ->where('days.0.groups.0.chapterCount', 1)
        ->where('days.0.groups.1.passage', 'Psalms 112-114')
        ->where('days.0.groups.1.chapterCount', 3)
        ->has('days.0.groups.1.chapters', 3)
    );
});

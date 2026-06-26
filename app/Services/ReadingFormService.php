<?php

namespace App\Services;

use App\Models\ReadingLog;
use App\Models\User;

class ReadingFormService
{
    private const RECENT_BOOK_LIMIT = 3;

    public function __construct(
        private BibleReferenceService $bibleReferenceService
    ) {}

    /**
     * Check if the user has read today.
     */
    public function hasReadToday(User $user): bool
    {
        return $user->readingLogs()
            ->whereDate('date_read', today())
            ->exists();
    }

    /**
     * Get yesterday availability logic for the form.
     * Yesterday is always available for missed-log recovery.
     */
    public function getFormContextData(User $user): array
    {
        return [
            'allowYesterday' => true,
            'recentBooks' => $this->getRecentBooksForForm($user),
        ];
    }

    /**
     * Get the user's recent distinct books for the reading form.
     *
     * @return array<int, array{id:int,name:string,chapters:int,testament:string}>
     */
    public function getRecentBooksForForm(User $user): array
    {
        $books = collect($this->bibleReferenceService->listBibleBooks(
            includeDeuterocanonical: $user->includesDeuterocanonicalBooks()
        ))->keyBy('id');

        if ($books->isEmpty()) {
            return [];
        }

        $rankedReadings = ReadingLog::query()
            ->select(['book_id', 'date_read', 'created_at', 'id'])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY book_id ORDER BY date_read DESC, created_at DESC, id DESC) AS book_rank')
            ->where('user_id', $user->id)
            ->whereIn('book_id', $books->keys()->all());

        $recentBookIds = ReadingLog::query()
            ->fromSub($rankedReadings, 'ranked_reading_logs')
            ->where('book_rank', 1)
            ->orderByDesc('date_read')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_BOOK_LIMIT)
            ->pluck('book_id')
            ->map(fn ($bookId) => (int) $bookId);

        return $recentBookIds
            ->map(fn (int $bookId) => $books->get($bookId))
            ->filter()
            ->values()
            ->all();
    }
}

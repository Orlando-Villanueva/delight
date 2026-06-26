<?php

namespace App\Services;

use App\Models\ReadingLog;
use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;

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

        $recentBookIds = ReadingLog::query()
            ->where('user_id', $user->id)
            ->whereIn('book_id', $books->keys()->all())
            ->whereNotExists(function (QueryBuilder $query): void {
                $this->constrainNewerReadingForSameBookSubquery($query);
            })
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

    private function constrainNewerReadingForSameBookSubquery(QueryBuilder $query): void
    {
        $query->select('newer_reading_logs.id')
            ->from('reading_logs as newer_reading_logs')
            ->whereColumn('newer_reading_logs.user_id', 'reading_logs.user_id')
            ->whereColumn('newer_reading_logs.book_id', 'reading_logs.book_id')
            ->where(function (QueryBuilder $query): void {
                $query->whereColumn('newer_reading_logs.date_read', '>', 'reading_logs.date_read')
                    ->orWhere(function (QueryBuilder $query): void {
                        $query->whereColumn('newer_reading_logs.date_read', 'reading_logs.date_read')
                            ->whereColumn('newer_reading_logs.created_at', '>', 'reading_logs.created_at');
                    })
                    ->orWhere(function (QueryBuilder $query): void {
                        $query->whereColumn('newer_reading_logs.date_read', 'reading_logs.date_read')
                            ->whereColumn('newer_reading_logs.created_at', 'reading_logs.created_at')
                            ->whereColumn('newer_reading_logs.id', '>', 'reading_logs.id');
                    });
            });
    }
}

<?php

namespace App\Services;

use App\Models\ReadingLog;
use App\Models\User;

class BookProgressSyncService
{
    private ReadingLogService $readingLogService;

    public function __construct(
        ReadingLogService $readingLogService
    ) {
        $this->readingLogService = $readingLogService;
    }

    /**
     * Sync book progress for a user based on their reading logs.
     * This ensures seeded reading logs are properly reflected in book progress.
     * Uses an efficient batch processing approach.
     */
    public function syncBookProgressForUser(User $user): array
    {
        // Get all reading logs for the user
        $readingLogs = $user->readingLogs()->get();

        // Track stats for reporting
        $stats = [
            'processed_logs' => $readingLogs->count(),
            'updated_books' => [],
            'created_books' => 0,
            'updated_existing_books' => 0,
        ];

        if ($readingLogs->isEmpty()) {
            return $stats;
        }

        // Group logs by book_id for efficient processing
        $logsByBook = $readingLogs->groupBy('book_id');

        // Get book information from the Bible service
        $bibleService = app(BibleReferenceService::class);

        // Process each book's logs in bulk
        foreach ($logsByBook as $bookId => $bookLogs) {
            // Get book information
            $book = $bibleService->getBibleBook($bookId, includeDeuterocanonical: true);
            if (! $book) {
                continue; // Skip invalid book IDs
            }

            // Get the localized book name
            $bookName = $bibleService->getLocalizedBookName($bookId, includeDeuterocanonical: true);

            // Extract all chapters read for this book
            $chaptersRead = $bookLogs->pluck('chapter')->unique()->values()->toArray();
            sort($chaptersRead);

            // Calculate completion percentage
            $totalChapters = $book['chapters'];
            $completionPercent = $totalChapters > 0
                ? round((count($chaptersRead) / $totalChapters) * 100, 2)
                : 0;

            // Determine if book is completed
            $isCompleted = count($chaptersRead) >= $totalChapters;

            // Update or create book progress record
            $bookProgress = $user->bookProgress()->updateOrCreate(
                ['book_id' => $bookId],
                [
                    'book_name' => $bookName,
                    'total_chapters' => $totalChapters,
                    'chapters_read' => $chaptersRead,
                    'completion_percent' => $completionPercent,
                    'is_completed' => $isCompleted,
                    'last_updated' => now(),
                ]
            );

            // Track statistics
            if ($bookProgress->wasRecentlyCreated) {
                $stats['created_books']++;
            } else {
                $stats['updated_existing_books']++;
            }

            $stats['updated_books'][] = $bookId;
        }

        $stats['updated_books_count'] = count($stats['updated_books']);

        return $stats;
    }

    /**
     * Sync book progress for all users.
     */
    public function syncBookProgressForAllUsers(): array
    {
        $userIds = ReadingLog::select('user_id')
            ->distinct()
            ->pluck('user_id');

        $stats = [
            'users_processed' => 0,
            'total_logs_processed' => 0,
            'total_books_updated' => 0,
        ];

        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if ($user) {
                $userStats = $this->syncBookProgressForUser($user);
                $stats['users_processed']++;
                $stats['total_logs_processed'] += $userStats['processed_logs'];
                $stats['total_books_updated'] += $userStats['updated_books_count'];
            }
        }

        return $stats;
    }
}

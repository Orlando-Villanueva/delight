<?php

namespace App\Services;

use App\Models\ReadingLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class ReadingLogService
{
    private BibleReferenceService $bibleService;

    public function __construct(BibleReferenceService $bibleService)
    {
        $this->bibleService = $bibleService;
    }

    /**
     * Log a new Bible reading entry for a user (supports single chapter or chapter ranges).
     *
     * Expected data format:
     * - For single chapter: ['book_id' => int, 'chapter' => int, ...]
     * - For chapter ranges: ['book_id' => int, 'chapters' => [int, int, ...], ...]
     *
     * Note: The controller parses 'chapter_input' from forms and converts it to the appropriate format.
     */
    public function logReading(User $user, array $data): ReadingLog
    {
        // Validate and format the Bible reference
        $this->validateBibleReference($data['book_id'], $data);

        // Format passage text if not provided
        if (! isset($data['passage_text'])) {
            $data['passage_text'] = $this->formatPassageText($data['book_id'], $data);
        }

        $dateRead = $data['date_read'] ?? now()->toDateString();

        // Check if user has already read today BEFORE creating the new reading
        $hasReadToday = $user->readingLogs()
            ->whereDate('date_read', $dateRead)
            ->exists();

        // Handle multiple chapters if provided
        if (isset($data['chapters']) && is_array($data['chapters'])) {
            return $this->logMultipleChapters($user, $data, $hasReadToday);
        }

        // Single chapter logging
        $readingLog = $user->readingLogs()->create([
            'book_id' => $data['book_id'],
            'chapter' => $data['chapter'],
            'passage_text' => $data['passage_text'],
            'date_read' => $dateRead,
            'notes_text' => $data['notes_text'] ?? null,
        ]);

        // Update book progress
        $this->updateBookProgress($user, $data['book_id'], $data['chapter']);

        // Invalidate user statistics cache with knowledge of whether this is first reading of the day
        $this->invalidateUserStatisticsCache($user, ! $hasReadToday);

        // Server-side state updated - HTMX will handle UI updates

        return $readingLog;
    }

    /**
     * Log multiple chapters as separate reading log entries.
     */
    private function logMultipleChapters(User $user, array $data, bool $hasReadToday): ReadingLog
    {
        $chapters = $data['chapters'];
        $firstLog = null;

        foreach ($chapters as $chapter) {
            $readingLog = $user->readingLogs()->create([
                'book_id' => $data['book_id'],
                'chapter' => $chapter,
                'passage_text' => $data['passage_text'], // Range text like "John 1-3"
                'date_read' => $data['date_read'] ?? now()->toDateString(),
                'notes_text' => $data['notes_text'] ?? null,
            ]);

            // Update book progress for each chapter
            $this->updateBookProgress($user, $data['book_id'], $chapter);

            // Return the first log for response consistency
            if ($firstLog === null) {
                $firstLog = $readingLog;
            }
        }

        // Invalidate user statistics cache after logging multiple chapters
        // Only the first reading of the day affects streaks and weekly goals
        $this->invalidateUserStatisticsCache($user, ! $hasReadToday);

        return $firstLog;
    }

    /**
     * Get recent reading logs for a user (quick access method).
     */
    public function getRecentLogs(User $user, int $limit = 10): Collection
    {
        return $user->readingLogs()
            ->recentFirst()
            ->limit($limit)
            ->get();
    }

    /**
     * Get reading history for a user with optional filtering.
     */
    public function getReadingHistory(User $user, ?int $limit = null, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $query = $user->readingLogs()->recentFirst();

        if ($startDate) {
            $query->dateRange($startDate, $endDate);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Calculate the current reading streak for a user.
     */
    public function calculateCurrentStreak(User $user): int
    {
        // Get all unique reading dates as Carbon objects, normalized to start of day
        $readingDates = $user->readingLogs()
            ->select('date_read')
            ->distinct()
            ->orderBy('date_read', 'desc')
            ->pluck('date_read')
            ->map(fn ($date) => Carbon::parse($date)->startOfDay())
            ->unique()
            ->values();

        if ($readingDates->isEmpty()) {
            return 0;
        }

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        // Check if user has read recently (today or yesterday - grace period)
        $hasRecentReading = $readingDates->contains(fn ($date) => $date->equalTo($today) || $date->equalTo($yesterday)
        );

        if (! $hasRecentReading) {
            return 0;
        }

        // Convert to array of date strings for easier lookup
        $readingDateStrings = $readingDates->map(fn ($date) => $date->toDateString())->toArray();

        // Start streak calculation from today or yesterday (whichever has a reading)
        $streak = 0;
        $checkDate = $today->copy();

        // If no reading today but reading yesterday, start from yesterday
        if (! in_array($today->toDateString(), $readingDateStrings) &&
            in_array($yesterday->toDateString(), $readingDateStrings)) {
            $checkDate = $yesterday->copy();
        }

        // Count consecutive days backwards from the starting date
        while (in_array($checkDate->toDateString(), $readingDateStrings)) {
            $streak++;
            $checkDate->subDay();
        }

        return $streak;
    }

    /**
     * Calculate the longest streak ever for a user.
     */
    public function calculateLongestStreak(User $user): int
    {
        // Get all unique reading dates, normalized and sorted ascending
        $readingDates = $user->readingLogs()
            ->select('date_read')
            ->distinct()
            ->orderBy('date_read', 'asc')
            ->pluck('date_read')
            ->map(fn ($date) => Carbon::parse($date)->startOfDay())
            ->unique()
            ->values();

        if ($readingDates->isEmpty()) {
            return 0;
        }

        $longestStreak = 1;
        $currentStreak = 1;
        $previousDate = $readingDates->first();

        foreach ($readingDates->skip(1) as $date) {
            // Cast to int as diffInDays may return float depending on Carbon version
            $daysDifference = (int) $previousDate->diffInDays($date);

            // Consecutive days should have exactly 1 day difference
            if ($daysDifference === 1) {
                $currentStreak++;
                $longestStreak = max($longestStreak, $currentStreak);
            } else {
                // Gap found, reset current streak
                $currentStreak = 1;
            }

            $previousDate = $date;
        }

        return $longestStreak;
    }

    /**
     * Validate Bible reference using BibleReferenceService.
     */
    private function validateBibleReference(int $bookId, array $data): void
    {
        if (! $this->bibleService->validateBookId($bookId)) {
            throw new InvalidArgumentException("Invalid book ID: {$bookId}");
        }

        if (isset($data['chapter'])) {
            if (! $this->bibleService->validateChapterNumber($bookId, $data['chapter'])) {
                throw new InvalidArgumentException("Invalid chapter number for book ID: {$bookId}");
            }
        }

        if (isset($data['chapters']) && is_array($data['chapters'])) {
            foreach ($data['chapters'] as $chapter) {
                if (! $this->bibleService->validateChapterNumber($bookId, $chapter)) {
                    throw new InvalidArgumentException("Invalid chapter number {$chapter} for book ID: {$bookId}");
                }
            }
        }
    }

    /**
     * Format passage text using BibleReferenceService.
     */
    private function formatPassageText(int $bookId, array $data): string
    {
        if (isset($data['chapters']) && is_array($data['chapters'])) {
            $startChapter = min($data['chapters']);
            $endChapter = max($data['chapters']);

            return $this->bibleService->formatBibleReferenceRange($bookId, $startChapter, $endChapter);
        }

        return $this->bibleService->formatBibleReference($bookId, $data['chapter']);
    }

    // Event handling removed - HTMX manages state updates via server responses

    /**
     * Update book progress when a chapter is read.
     */
    private function updateBookProgress(User $user, int $bookId, int $chapter): void
    {
        // Get book information from BibleReferenceService
        $book = $this->bibleService->getBibleBook($bookId);
        if (! $book) {
            throw new InvalidArgumentException("Invalid book ID: {$bookId}");
        }

        // Get the localized book name (string) instead of the array
        $bookName = $this->bibleService->getLocalizedBookName($bookId);

        $bookProgress = $user->bookProgress()->firstOrCreate(
            ['book_id' => $bookId],
            [
                'book_name' => $bookName,
                'total_chapters' => $book['chapters'],
                'chapters_read' => [],
                'completion_percent' => 0,
                'is_completed' => false,
            ]
        );

        // Get current chapters read
        $chaptersRead = $bookProgress->chapters_read ?? [];

        // Add new chapter if not already recorded
        if (! in_array($chapter, $chaptersRead)) {
            $chaptersRead[] = $chapter;
            sort($chaptersRead); // Keep chapters sorted

            // Update book progress
            $bookProgress->chapters_read = $chaptersRead;
            $bookProgress->completion_percent = round((count($chaptersRead) / $book['chapters']) * 100, 2);
            $bookProgress->is_completed = count($chaptersRead) >= $book['chapters'];
            $bookProgress->save();
        }
    }

    /**
     * Update book progress from an existing reading log.
     * This is useful for syncing book progress with seeded reading logs.
     */
    public function updateBookProgressFromLog(ReadingLog $log): void
    {
        $this->updateBookProgress($log->user, $log->book_id, $log->chapter);
    }

    /**
     * Update book progress after deleting a chapter.
     */
    private function updateBookProgressAfterDeletion(User $user, int $bookId, int $chapter): void
    {
        $bookProgress = $user->bookProgress()->where('book_id', $bookId)->first();

        if (! $bookProgress) {
            return;
        }

        // Get current chapters read
        $chaptersRead = $bookProgress->chapters_read ?? [];

        // Remove the deleted chapter
        $chaptersRead = array_values(array_filter($chaptersRead, fn ($ch) => $ch !== $chapter));

        // Get book information for recalculation
        $book = $this->bibleService->getBibleBook($bookId);

        // Update book progress
        $bookProgress->chapters_read = $chaptersRead;
        $bookProgress->completion_percent = count($chaptersRead) > 0
            ? round((count($chaptersRead) / $book['chapters']) * 100, 2)
            : 0;
        $bookProgress->is_completed = count($chaptersRead) >= $book['chapters'];
        $bookProgress->save();
    }

    /**
     * Invalidate user statistics cache when reading logs change.
     * Uses smart invalidation to minimize expensive recalculations.
     */
    private function invalidateUserStatisticsCache(User $user, bool $isFirstReadingOfDay = true): void
    {
        $currentYear = now()->year;
        $previousYear = $currentYear - 1;
        $currentMonth = now()->format('Y-m');

        // Always invalidate - these change on every reading
        Cache::forget("user_dashboard_stats_{$user->id}");
        Cache::forget("user_calendar_{$user->id}_{$currentYear}");
        Cache::forget("user_calendar_{$user->id}_{$previousYear}");
        Cache::forget("user_monthly_calendar_{$user->id}_{$currentMonth}");
        Cache::forget("user_total_reading_days_{$user->id}");
        Cache::forget("user_avg_chapters_per_day_{$user->id}");

        // Smart invalidation - only invalidate on first reading of the day
        if ($isFirstReadingOfDay) {
            // First reading of the day - streak and weekly goal will change
            $weekStart = now()->startOfWeek(Carbon::SUNDAY)->toDateString();
            Cache::forget("user_weekly_goal_{$user->id}_{$weekStart}");
            Cache::forget("user_weekly_streak_{$user->id}_{$weekStart}");
            Cache::forget("user_current_streak_{$user->id}");

            // Longest streak - only invalidate if current streak might exceed it
            $cachedLongest = Cache::get("user_longest_streak_{$user->id}");
            if ($cachedLongest === null) {
                // No cached longest streak, need to calculate
                Cache::forget("user_longest_streak_{$user->id}");
            } else {
                // Check if current streak + 1 (after today's reading) might exceed longest
                $cachedCurrent = Cache::get("user_current_streak_{$user->id}");
                if ($cachedCurrent === null || ($cachedCurrent + 1) > $cachedLongest) {
                    Cache::forget("user_longest_streak_{$user->id}");
                }
            }
        }
        // If not first reading of the day, streak and weekly goal won't change
        // so skip expensive invalidations
    }

    /**
     * Delete a reading log and invalidate related caches.
     */
    public function deleteReadingLog(ReadingLog $readingLog): bool
    {
        $user = $readingLog->user;
        $bookId = $readingLog->book_id;
        $chapter = $readingLog->chapter;

        $deleted = $readingLog->delete();

        if ($deleted) {
            // Update book progress to remove the deleted chapter
            $this->updateBookProgressAfterDeletion($user, $bookId, $chapter);

            // For deletions, we can't easily determine if this was the only reading of the day
            // so we invalidate all caches to be safe
            $this->invalidateUserStatisticsCache($user, true);
        }

        return $deleted;
    }

    /**
     * Update a reading log and invalidate related caches.
     */
    public function updateReadingLog(ReadingLog $readingLog, array $data): ReadingLog
    {
        $user = $readingLog->user;
        $readingLog->update($data);

        // For updates, we can't easily determine the impact on daily reading status
        // so we invalidate all caches to be safe
        $this->invalidateUserStatisticsCache($user, true);

        return $readingLog;
    }

    /**
     * Render reading log cards HTML for infinite scroll responses.
     * Takes a collection of grouped logs and returns concatenated card HTML.
     * Includes infinite scroll sentinel if there are more pages.
     */
    public function renderReadingLogCardsHtml($logs): string
    {
        $cardsHtml = '';

        foreach ($logs as $logsForDay) {
            if ($logsForDay->count() === 1) {
                // Single reading: use individual card
                $cardsHtml .= view('components.bible.reading-log-card', [
                    'log' => $logsForDay->first(),
                ])->render();
            } else {
                // Multiple readings: use daily grouped card
                $cardsHtml .= view('components.bible.daily-reading-card', [
                    'logsForDay' => $logsForDay,
                ])->render();
            }
        }

        // Add infinite scroll sentinel if there are more pages
        if ($logs->hasMorePages()) {
            $cardsHtml .= view('partials.infinite-scroll-sentinel', [
                'logs' => $logs,
            ])->render();
        }

        return $cardsHtml;
    }
}

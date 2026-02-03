<?php

namespace App\Services;

use App\Models\ChurnRecoveryEmail;
use App\Models\ReadingLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

        // Check if this is the first reading ever (for celebration)
        // Wrapped in transaction to prevent race conditions where multiple requests
        // could trigger the celebration simultaneously
        $shouldCelebrate = DB::transaction(function () use ($user) {
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();

            if (! $lockedUser->hasEverCelebratedFirstReading()
                && ! $lockedUser->readingLogs()->exists()) {
                $user->update(['celebrated_first_reading_at' => now()]);

                return true;
            }

            return false;
        });

        // Handle multiple chapters if provided
        if (isset($data['chapters']) && is_array($data['chapters'])) {
            $log = $this->logMultipleChapters($user, $data, $hasReadToday);

            return $log;
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

        // Check if churn recovery status needs to be reset
        $this->maybeResetChurnRecovery($user);

        return $readingLog;
    }

    /**
     * Check if user has re-engaged enough to reset their churn recovery status.
     *
     * Reset occurs if:
     * 1. User has read on 3+ distinct days in the last 7 days
     * 2. It has been at least 90 days since the last churn recovery email
     */
    public function maybeResetChurnRecovery(User $user): void
    {
        // Check 1: Sustained activity (3+ distinct days in last 7 days)
        $activeDays = $user->readingLogs()
            ->where('date_read', '>=', now()->subDays(7))
            ->distinct('date_read')
            ->count('date_read');

        if ($activeDays < 3) {
            return; // Not sufficiently re-engaged
        }

        // Check 2: Cooldown period (90 days since last email)
        $lastEmail = ChurnRecoveryEmail::where('user_id', $user->id)
            ->latest('sent_at')
            ->first();

        // If no active email sequence, nothing to reset
        if (! $lastEmail) {
            return;
        }

        if ($lastEmail->sent_at > now()->subDays(90)) {
            return; // Too soon for reset
        }

        // Perform soft reset
        ChurnRecoveryEmail::where('user_id', $user->id)
            ->delete();
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

        $today = today();
        $yesterday = today()->subDay();

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
     * Build the per-day timeline for the active streak (date -> readings count).
     *
     * Returns an array sorted from streak start to the most recent day:
     * [
     *     ['date' => '2025-01-01', 'count' => 2],
     *     ...
     * ]
     */
    public function getCurrentStreakSeries(User $user): array
    {
        $dailyCounts = $user->readingLogs()
            ->select('date_read')
            ->orderBy('date_read', 'desc')
            ->get()
            ->groupBy(function ($row) {
                return Carbon::parse($row->date_read)->startOfDay()->toDateString();
            })
            ->map(function ($group) {
                return $group->count();
            });

        if ($dailyCounts->isEmpty()) {
            return [];
        }

        $today = today();
        $yesterday = today()->subDay();

        $todayKey = $today->toDateString();
        $yesterdayKey = $yesterday->toDateString();

        $hasRecentReading = $dailyCounts->has($todayKey) || $dailyCounts->has($yesterdayKey);

        if (! $hasRecentReading) {
            return [];
        }

        $series = [];
        $checkDate = $dailyCounts->has($todayKey) ? $today->copy() : $yesterday->copy();

        while ($dailyCounts->has($checkDate->toDateString())) {
            $dateKey = $checkDate->toDateString();

            $series[] = [
                'date' => $dateKey,
                'count' => $dailyCounts->get($dateKey),
            ];

            $checkDate->subDay();
        }

        return array_reverse($series);
    }

    /**
     * Calculate the longest streak ever for a user.
     */
    public function calculateLongestStreak(User $user): int
    {
        $readingDates = $this->getNormalizedReadingDates($user);

        return $this->computeLongestStreakFromDates($readingDates);
    }

    /**
     * Calculate the longest streak prior to the supplied date (exclusive).
     */
    public function calculateLongestStreakBeforeDate(User $user, Carbon|string $beforeDate): int
    {
        $normalizedDate = $beforeDate instanceof Carbon
            ? $beforeDate->copy()->startOfDay()
            : Carbon::parse($beforeDate)->startOfDay();

        $readingDates = $this->getNormalizedReadingDates($user, $normalizedDate);

        return $this->computeLongestStreakFromDates($readingDates);
    }

    /**
     * Fetch normalized reading dates optionally filtered before a date.
     */
    private function getNormalizedReadingDates(User $user, ?Carbon $beforeDate = null): Collection
    {
        $query = $user->readingLogs()
            ->select('date_read')
            ->distinct()
            ->orderBy('date_read', 'asc');

        if ($beforeDate) {
            $query->where('date_read', '<', $beforeDate->toDateString());
        }

        return $query->pluck('date_read')
            ->map(fn ($date) => Carbon::parse($date)->startOfDay())
            ->unique()
            ->values();
    }

    /**
     * Compute the longest streak from an ordered list of reading dates.
     */
    private function computeLongestStreakFromDates(Collection $readingDates): int
    {
        if ($readingDates->isEmpty()) {
            return 0;
        }

        $longestStreak = 1;
        $currentStreak = 1;
        $previousDate = $readingDates->first();

        foreach ($readingDates->skip(1) as $date) {
            $daysDifference = (int) $previousDate->diffInDays($date);

            if ($daysDifference === 1) {
                $currentStreak++;
                $longestStreak = max($longestStreak, $currentStreak);
            } else {
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

        $hasRemainingLogs = $user->readingLogs()
            ->where('book_id', $bookId)
            ->where('chapter', $chapter)
            ->exists();

        if ($hasRemainingLogs) {
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
        Cache::forget("user_current_streak_series_{$user->id}");
        Cache::forget(AnnualRecapService::cacheKeyFor($user, $currentYear));

        // Smart invalidation - only invalidate on first reading of the day
        if ($isFirstReadingOfDay) {
            // First reading of the day - streak and weekly goal will change
            $weekStart = now()->startOfWeek(Carbon::SUNDAY)->toDateString();
            Cache::forget("user_weekly_goal_v2_{$user->id}_{$weekStart}");
            Cache::forget("user_weekly_goal_{$user->id}_{$weekStart}");
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
        return view('partials.reading-log-items', [
            'logs' => $logs,
            'includeEmptyToday' => false,
        ])->render()
        .view('partials.reading-log-modals', [
            'logs' => $logs,
            'modalsOutOfBand' => true,
            'swapMethod' => 'beforeend',
        ])->render();
    }

    public function getPaginatedDayGroupsFor(Request $request, UserStatisticsService $statisticsService, int $perPage = 8): LengthAwarePaginator
    {
        $user = $request->user();
        $currentPage = max(1, (int) $request->get('page', 1));
        $basePath = $request->routeIs('logs.index') ? $request->url() : route('logs.index');

        // Step 1: Paginate the unique dates first
        // This is much more efficient than loading all logs into memory
        $dateQuery = $user->readingLogs()
            ->select('date_read')
            ->distinct()
            ->orderBy('date_read', 'desc');

        $totalDates = (clone $dateQuery)->count('date_read');

        $paginatedDates = (clone $dateQuery)
            ->forPage($currentPage, $perPage)
            ->get();

        // Step 2: Get the logs for these specific dates
        $dates = collect($paginatedDates)->map(function ($item) {
            // Ensure we handle both Model objects (with casting) and stdClass objects (raw)
            $date = $item->date_read;

            if ($date instanceof Carbon) {
                return $date->format('Y-m-d');
            }

            // Fallback for raw strings or other formats - force standard Y-m-d
            return Carbon::parse($date)->format('Y-m-d');
        });

        if ($dates->isEmpty()) {
            $groupedLogs = collect();
        } else {
            $logs = $user->readingLogs()
                ->whereIn(DB::raw('date(date_read)'), $dates)
                ->orderBy('date_read', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            // Step 3: Group and prepare logs for display
            $groupedLogs = $logs
                ->groupBy(fn ($log) => $log->date_read->format('Y-m-d'))
                ->map(fn ($logsForDay) => $this->prepareDisplayLogs($logsForDay, $statisticsService))
                ->sortByDesc(fn ($logsForDay, $date) => $date);
        }

        // Step 4: Create Paginator preserving the original dates pagination meta
        $paginator = new LengthAwarePaginator(
            $groupedLogs,
            $totalDates,
            $perPage,
            $currentPage,
            ['path' => $basePath, 'pageName' => 'page']
        );

        $paginator->appends($request->query());

        return $paginator;
    }

    /**
     * Resolve the collection of logs that should be updated when editing notes.
     */
    public function getLogsForNoteUpdate(User $user, ReadingLog $primaryLog, array $logIds = []): Collection
    {
        $ids = collect($logIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->push($primaryLog->id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect([$primaryLog]);
        }

        $logs = ReadingLog::where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->orderBy('chapter')
            ->get();

        return $logs->isEmpty() ? collect([$primaryLog]) : $logs;
    }

    public function getPreparedLogsForDate(User $user, string $date, UserStatisticsService $statisticsService): ?Collection
    {
        $logsForDay = $user->readingLogs()
            ->whereDate('date_read', $date)
            ->get();

        if ($logsForDay->isEmpty()) {
            return null;
        }

        return $this->prepareDisplayLogs($logsForDay, $statisticsService);
    }

    public function getPreparedLogsForDates(User $user, array $dates, UserStatisticsService $statisticsService): array
    {
        $uniqueDates = collect($dates)
            ->filter()
            ->unique()
            ->values();

        if ($uniqueDates->isEmpty()) {
            return [];
        }

        return $uniqueDates->mapWithKeys(function ($date) use ($user, $statisticsService) {
            $logsForDay = $user->readingLogs()
                ->whereDate('date_read', $date)
                ->get();

            if ($logsForDay->isEmpty()) {
                return [$date => null];
            }

            return [$date => $this->prepareDisplayLogs($logsForDay, $statisticsService)];
        })->all();
    }

    public function userHasAnyLogs(User $user): bool
    {
        return $user->readingLogs()->exists();
    }

    /**
     * @deprecated Use getPaginatedDayGroupsFor directly for better performance.
     */
    public function buildGroupedLogsForUser(User $user, UserStatisticsService $statisticsService): Collection
    {
        return $user->readingLogs()->recentFirst()
            ->get()
            ->groupBy(fn ($log) => $log->date_read->format('Y-m-d'))
            ->map(fn ($logsForDay) => $this->prepareDisplayLogs($logsForDay, $statisticsService))
            ->sortByDesc(fn ($logsForDay, $date) => $date);
    }

    /**
     * @deprecated Use getPaginatedDayGroupsFor directly.
     */
    public function paginateGroupedLogs(Collection $groupedLogs, int $currentPage, int $perPage, string $path): LengthAwarePaginator
    {
        $currentPage = max(1, $currentPage);
        $offset = ($currentPage - 1) * $perPage;
        $paginatedDays = $groupedLogs->slice($offset, $perPage);

        return new LengthAwarePaginator(
            $paginatedDays,
            $groupedLogs->count(),
            $perPage,
            $currentPage,
            ['path' => $path, 'pageName' => 'page']
        );
    }

    /**
     * Partition a collection of reading logs into contiguous chapter segments.
     */
    public function segmentReadingLogs(Collection $logs): Collection
    {
        if ($logs->isEmpty()) {
            return collect();
        }

        $sorted = $logs->sortBy('chapter')->values();

        $segments = collect();
        $currentSegment = collect([$sorted->first()]);

        for ($i = 1; $i < $sorted->count(); $i++) {
            $previous = $sorted[$i - 1];
            $current = $sorted[$i];

            if ($current->chapter === $previous->chapter + 1) {
                $currentSegment->push($current);

                continue;
            }

            $segments->push($currentSegment);
            $currentSegment = collect([$current]);
        }

        $segments->push($currentSegment);

        return $segments;
    }

    /**
     * Generate a human-readable passage label for a reading log segment.
     */
    public function formatSegmentPassage(Collection $segment, ?string $locale = null): string
    {
        if ($segment->isEmpty()) {
            throw new InvalidArgumentException('Segment cannot be empty.');
        }

        $bookId = $segment->first()->book_id;
        $chapters = $segment->pluck('chapter')->all();

        return $this->bibleService->formatBibleChapterList($bookId, $chapters, $locale);
    }

    private function prepareDisplayLogs(Collection $logsForDay, UserStatisticsService $statisticsService): Collection
    {
        $sessions = $logsForDay->groupBy(function ($log) {
            return implode('|', [
                $log->user_id,
                $log->book_id,
                $log->date_read->format('Y-m-d'),
                $log->created_at->format('Y-m-d H:i:s'),
            ]);
        });

        $displayLogs = $sessions->flatMap(function (Collection $sessionLogs) {
            $segments = $this->segmentReadingLogs($sessionLogs);

            return $segments->map(function (Collection $segment) {
                $displayLog = $segment->first();
                $displayLog->all_logs = $segment->values();
                $displayLog->display_passage_text = $this->formatSegmentPassage($segment);
                $displayLog->chapters_count = $segment->count();

                return $displayLog;
            });
        });

        return $displayLogs
            ->map(function ($log) use ($statisticsService) {
                $log->time_ago = $statisticsService->calculateSmartTimeAgo($log);
                $log->logged_time_ago = $statisticsService->formatTimeAgo($log->created_at);

                return $log;
            })
            ->sortByDesc('created_at')
            ->values();
    }
}

<?php

namespace App\Services;

use App\Contracts\ReadingLogInterface;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class UserStatisticsService
{
    public function __construct(
        private ReadingLogService $readingLogService,
        private WeeklyGoalService $weeklyGoalService,
        private WeeklyJourneyService $weeklyJourneyService
    ) {}

    /**
     * Get comprehensive dashboard statistics for a user.
     */
    public function getDashboardStatistics(User $user): array
    {
        return Cache::remember(
            "user_dashboard_stats_{$user->id}",
            300, // 5 minutes TTL
            function () use ($user) {
                $weeklyGoal = $this->getWeeklyGoalStatistics($user);

                return [
                    'streaks' => $this->getStreakStatistics($user),
                    'reading_summary' => $this->getReadingSummary($user),
                    'book_progress' => $this->getBookProgressSummary($user),
                    'recent_activity' => $this->getRecentActivity($user),
                    'weekly_goal' => $weeklyGoal,
                    'weekly_journey' => $weeklyGoal['journey'] ?? null,
                ];
            }
        );
    }

    /**
     * Get streak-related statistics.
     */
    public function getStreakStatistics(User $user): array
    {
        $currentStreak = Cache::remember(
            "user_current_streak_{$user->id}",
            3600, // 60 minutes TTL - expensive walking calculation
            fn() => $this->readingLogService->calculateCurrentStreak($user)
        );

        $longestStreak = Cache::remember(
            "user_longest_streak_{$user->id}",
            3600, // 60 minutes TTL - most expensive full history analysis
            fn() => $this->readingLogService->calculateLongestStreak($user)
        );

        $currentStreakSeries = Cache::remember(
            "user_current_streak_series_{$user->id}",
            3600,
            fn() => $this->readingLogService->getCurrentStreakSeries($user)
        );

        $currentStreakStartDate = null;
        if (! empty($currentStreakSeries)) {
            $firstEntry = $currentStreakSeries[0];
            if (! empty($firstEntry['date'])) {
                $currentStreakStartDate = Carbon::parse($firstEntry['date'])->startOfDay();
            }
        }

        $previousLongest = 0;
        if ($currentStreak > 0 && $currentStreakStartDate) {
            $previousLongest = $this->readingLogService->calculateLongestStreakBeforeDate($user, $currentStreakStartDate);
        } else {
            $previousLongest = $longestStreak;
        }

        $recordStatus = 'none';
        if ($currentStreak > 0) {
            if ($currentStreak > $previousLongest) {
                $recordStatus = 'record';
            } elseif ($currentStreak === $previousLongest && $previousLongest > 0) {
                $recordStatus = 'tied';
            }
        }

        $recordJustBroken = $recordStatus === 'record' && $currentStreak === ($previousLongest + 1);

        return [
            'current_streak' => $currentStreak,
            'longest_streak' => $longestStreak,
            'current_streak_series' => $currentStreakSeries,
            'record_previous_best' => $previousLongest,
            'record_status' => $recordStatus,
            'record_just_broken' => $recordJustBroken,
            'current_streak_started_at' => $currentStreakStartDate?->toDateString(),
        ];
    }

    /**
     * Get weekly goal statistics.
     */
    public function getWeeklyGoalStatistics(User $user): array
    {
        $weekStart = now()->startOfWeek(Carbon::SUNDAY)->toDateString();

        return Cache::remember(
            "user_weekly_goal_v2_{$user->id}_{$weekStart}",
            900, // 15 minutes TTL - light query with date range filter
            function () use ($user) {
                $weeklyGoalData = $this->weeklyGoalService->getWeeklyGoalData($user);

                return array_merge(
                    $weeklyGoalData,
                    [
                        'journey' => $this->weeklyJourneyService->getWeeklyJourneyData(
                            $user,
                            $weeklyGoalData['current_progress'] ?? null
                        ),
                    ]
                );
            }
        );
    }

    /**
     * Get reading summary statistics.
     */
    public function getReadingSummary(User $user): array
    {
        $summary = $user->readingLogs()
            ->selectRaw('count(*) as total, min(date_read) as first_date, max(date_read) as last_date')
            ->first();

        $totalReadings = (int) ($summary->total ?? 0);
        $firstReadingDate = $summary->first_date;
        $lastReadingDate = $summary->last_date;

        $daysSinceFirst = $firstReadingDate
            ? Carbon::parse($firstReadingDate)->diffInDays(now()) + 1
            : 0;

        return [
            'total_readings' => $totalReadings,
            'first_reading_date' => $firstReadingDate,
            'last_reading_date' => $lastReadingDate,
            'days_since_first_reading' => $daysSinceFirst,
            'total_reading_days' => $this->getTotalReadingDays($user),
            'average_chapters_per_day' => $this->getAverageChaptersPerDay($user, $totalReadings, $daysSinceFirst),
            'this_month_days' => $this->getThisMonthReadingDays($user),
            'this_week_days' => $this->weeklyGoalService->getThisWeekReadingDays($user),
        ];
    }

    /**
     * Get total unique reading days (cached).
     */
    private function getTotalReadingDays(User $user): int
    {
        return Cache::remember(
            "user_total_reading_days_{$user->id}",
            3600, // 60 minutes TTL - expensive distinct count query
            fn() => $user->readingLogs()->distinct('date_read')->count('date_read')
        );
    }

    /**
     * Get average chapters per day since first reading (cached).
     */
    private function getAverageChaptersPerDay(User $user, int $totalReadings, int $daysSinceFirst): float
    {
        return Cache::remember(
            "user_avg_chapters_per_day_{$user->id}",
            3600, // 60 minutes TTL - calculation based on cached values
            function () use ($totalReadings, $daysSinceFirst) {
                if ($totalReadings === 0 || $daysSinceFirst === 0) {
                    return 0.0;
                }

                return round($totalReadings / $daysSinceFirst, 2);
            }
        );
    }

    /**
     * Get reading days count for current month.
     */
    private function getThisMonthReadingDays(User $user): int
    {
        return $user->readingLogs()
            ->whereMonth('date_read', now()->month)
            ->whereYear('date_read', now()->year)
            ->distinct('date_read')
            ->count('date_read');
    }

    /**
     * Get book progress summary.
     */
    public function getBookProgressSummary(User $user): array
    {
        // Eager load book progress data with a single query instead of lazy loading
        $bookProgress = $user->bookProgress()->get();

        $completed = $bookProgress->where('is_completed', true)->count();
        $inProgress = $bookProgress->where('is_completed', false)
            ->where('completion_percent', '>', 0)
            ->count();
        $notStarted = 66 - $completed - $inProgress; // Total Bible books minus started

        // Calculate overall progress using the already loaded collection
        $totalChapters = 1189; // Total chapters in the Bible
        $chaptersRead = $bookProgress->sum(function ($progress) {
            return count($progress->chapters_read ?? []);
        });
        $overallProgressPercent = $totalChapters > 0 ? round(($chaptersRead / $totalChapters) * 100, 2) : 0;

        return [
            'books_completed' => $completed,
            'books_in_progress' => $inProgress,
            'books_not_started' => max(0, $notStarted),
            'total_bible_books' => 66,
            'overall_progress_percent' => $overallProgressPercent,
        ];
    }

    /**
     * Get recent reading activity.
     */
    public function getRecentActivity(User $user, int $limit = 5): array
    {
        // Apply limit at database level to avoid loading all records into memory
        // Use a more efficient query with specific index
        $recentReadings = $user->readingLogs()
            ->select('id', 'passage_text', 'date_read', 'notes_text', 'created_at')
            ->orderBy('date_read', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit * 3) // Get more records to account for potential duplicates
            ->get()
            ->unique(function ($log) {
                // Use a safer separator that won't appear in passage text
                return $log->passage_text . '::' . $log->date_read;
            })
            ->take($limit);

        return $recentReadings->map(function ($reading) {
            return [
                'id' => $reading->id,
                'passage_text' => $reading->passage_text,
                'date_read' => $reading->date_read,
                'notes_text' => $reading->notes_text,
                'time_ago' => $this->calculateSmartTimeAgo($reading),
            ];
        })->values()->toArray();
    }

    /**
     * Get calendar data for visualization (similar to GitHub contribution graph).
     */
    public function getCalendarData(User $user, ?string $year = null): array
    {
        $year = $year ?? now()->year;

        return Cache::remember(
            "user_calendar_{$user->id}_{$year}",
            3600, // 60 minutes TTL - processes full year of data
            function () use ($user, $year) {
                $startDate = Carbon::create($year, 1, 1)->startOfDay();
                $endDate = Carbon::create($year, 12, 31)->endOfDay();

                // Group by date and count readings per date using database-level grouping
                $readingData = $user->readingLogs()
                    ->whereBetween('date_read', [$startDate, $endDate])
                    ->selectRaw('date_read, count(*) as count')
                    ->groupBy('date_read')
                    ->get()
                    ->mapWithKeys(function ($item) {
                        $date = $item->date_read;
                        $dateString = ($date instanceof Carbon) ? $date->toDateString() : Carbon::parse($date)->toDateString();
                        return [$dateString => (int) $item->count];
                    })
                    ->toArray();

                $calendar = [];
                $currentDate = $startDate->copy();

                while ($currentDate->lte($endDate)) {
                    $dateString = $currentDate->toDateString();
                    $readingCount = $readingData[$dateString] ?? 0;

                    $calendar[$dateString] = [
                        'date' => $dateString,
                        'reading_count' => $readingCount,
                        'has_reading' => $readingCount > 0,
                    ];
                    $currentDate->addDay();
                }

                return $calendar;
            }
        );
    }

    /**
     * Get monthly calendar data with grid layout and statistics.
     */
    public function getMonthlyCalendarData(User $user, ?int $year = null, ?int $month = null): array
    {
        $currentDate = now();
        $year = $year ?? $currentDate->year;
        $month = $month ?? $currentDate->month;

        $targetDate = Carbon::create($year, $month, 1);
        $monthKey = $targetDate->format('Y-m');

        return Cache::remember(
            "user_monthly_calendar_{$user->id}_{$monthKey}",
            900, // 15 minutes TTL - lighter than full year
            fn() => $this->buildMonthlyCalendarData($user, $year, $month, $targetDate)
        );
    }

    /**
     * Build monthly calendar data with grid layout and statistics.
     */
    private function buildMonthlyCalendarData(User $user, int $year, int $month, Carbon $targetDate): array
    {
        $monthName = $targetDate->format('F Y');
        $calendarData = $this->getCalendarData($user, (string) $year);

        $calendar = $this->generateMonthlyCalendarGrid($calendarData, $targetDate);
        $statistics = $this->calculateMonthlyStatistics($calendar, $year, $month);

        return array_merge([
            'calendar' => $calendar,
            'monthName' => $monthName,
            'year' => $year,
            'month' => $month,
        ], $statistics);
    }

    /**
     * Generate calendar grid for the month with reading data.
     */
    private function generateMonthlyCalendarGrid(array $calendarData, Carbon $targetDate): array
    {
        $firstDay = $targetDate->copy()->startOfMonth();
        $lastDay = $targetDate->copy()->endOfMonth();
        $daysInMonth = $lastDay->day;
        $startingDayOfWeek = $firstDay->dayOfWeek; // 0 = Sunday, 6 = Saturday

        $calendar = [];

        // Add empty cells for days before month starts
        for ($i = 0; $i < $startingDayOfWeek; $i++) {
            $calendar[] = null;
        }

        // Add days of the month with reading data
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = $firstDay->copy()->addDays($day - 1);
            $dateStr = $date->toDateString();

            // Get reading data for this day from the cached calendar data
            $dayData = $calendarData[$dateStr] ?? null;
            $readingCount = $dayData ? $dayData['reading_count'] : 0;
            $hasReading = $dayData ? $dayData['has_reading'] : false;

            $calendar[] = [
                'day' => $day,
                'date' => $date,
                'hasReading' => $hasReading,
                'readingCount' => $readingCount,
                'isToday' => $date->isToday(),
                'dateString' => $dateStr,
            ];
        }

        return $calendar;
    }

    /**
     * Calculate monthly statistics from calendar data.
     */
    private function calculateMonthlyStatistics(array $calendar, int $year, int $month): array
    {
        $thisMonthReadings = 0;
        $thisMonthChapters = 0;

        foreach ($calendar as $day) {
            if ($day !== null && $day['hasReading']) {
                $thisMonthReadings++;
                $thisMonthChapters += $day['readingCount'];
            }
        }

        // Calculate success rate based on days passed in month
        $today = now();
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $daysPassedInMonth = $today->month === $month && $today->year === $year
            ? min($daysInMonth, $today->day)
            : $daysInMonth;
        $successRate = $daysPassedInMonth > 0 ? round(($thisMonthReadings / $daysPassedInMonth) * 100) : 0;

        return [
            'thisMonthReadings' => $thisMonthReadings,
            'thisMonthChapters' => $thisMonthChapters,
            'successRate' => $successRate,
            'daysInMonth' => $daysInMonth,
            'daysPassedInMonth' => $daysPassedInMonth,
        ];
    }

    /**
     * Calculate smart time ago that considers the context of when reading was done vs when it was logged.
     */
    public function calculateSmartTimeAgo(ReadingLogInterface $reading): string
    {
        // Handle null date_read by falling back to created_at
        $dateRead = $reading->getDateRead();
        if (is_null($dateRead)) {
            return $this->formatTimeAgo($reading->getCreatedAt());
        }

        $dateReadCarbon = Carbon::parse($dateRead);
        $createdAt = $reading->getCreatedAt();

        // If the reading was done today, use created_at for more accurate "hours/minutes ago"
        if ($dateReadCarbon->isToday()) {
            return $this->formatTimeAgo($createdAt);
        }

        // If the reading was done yesterday, always show "1 day ago" regardless of when logged
        if ($dateReadCarbon->isYesterday()) {
            return '1 day ago';
        }

        // For older readings, use date_read to show accurate day count
        return $this->formatTimeAgo($dateReadCarbon);
    }

    /**
     * Format time difference with proper labels.
     */
    public function formatTimeAgo(Carbon $date): string
    {
        $now = now();
        $diffInSeconds = (int) $date->diffInSeconds($now);
        $diffInMinutes = (int) $date->diffInMinutes($now);
        $diffInHours = (int) $date->diffInHours($now);
        $diffInDays = (int) $date->diffInDays($now);

        // Just now (less than 1 minute)
        if ($diffInSeconds < 60) {
            return 'just now';
        }

        // Minutes ago (1-59 minutes)
        if ($diffInMinutes < 60) {
            return $diffInMinutes === 1 ? '1 minute ago' : "{$diffInMinutes} minutes ago";
        }

        // Hours ago (1-23 hours, or same day but 24+ hours)
        if ($diffInHours < 24) {
            return $diffInHours === 1 ? '1 hour ago' : "{$diffInHours} hours ago";
        }

        // Days ago (1+ days)
        if ($diffInDays === 1) {
            return '1 day ago';
        } else {
            return "{$diffInDays} days ago";
        }
    }

    /**
     * Invalidate all cached statistics for a user.
     */
    public function invalidateUserCache(User $user): void
    {
        $currentYear = now()->year;
        $previousYear = $currentYear - 1;
        $currentMonth = now()->format('Y-m');
        $weekStart = now()->startOfWeek(Carbon::SUNDAY)->toDateString();

        // Clear all user-specific caches
        Cache::forget("user_dashboard_stats_{$user->id}");
        Cache::forget("user_current_streak_{$user->id}");
        Cache::forget("user_longest_streak_{$user->id}");
        Cache::forget("user_weekly_goal_v2_{$user->id}_{$weekStart}");
        Cache::forget("user_weekly_goal_{$user->id}_{$weekStart}");
        Cache::forget("user_calendar_{$user->id}_{$currentYear}");
        Cache::forget("user_calendar_{$user->id}_{$previousYear}");
        Cache::forget("user_monthly_calendar_{$user->id}_{$currentMonth}");
        Cache::forget("user_total_reading_days_{$user->id}");
        Cache::forget("user_avg_chapters_per_day_{$user->id}");
    }

    /**
     * Invalidate specific cache keys for a user.
     */
    public function invalidateSpecificCache(User $user, array $cacheKeys): void
    {
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }
}

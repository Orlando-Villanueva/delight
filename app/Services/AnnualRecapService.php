<?php

namespace App\Services;

use App\Models\AnnualRecap;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;

class AnnualRecapService
{
    public function __construct(
        private BibleReferenceService $bibleService,
        private ReadingCalendarService $readingCalendar
    ) {}

    /**
     * Get the full annual recap for a user for a specific year.
     */
    public function getRecap(User $user, int $year): array
    {
        $accountNow = $this->readingCalendar->nowFor($user);

        if ($year >= $accountNow->year) {
            return $this->getLiveRecap($user, $year, $accountNow);
        }

        $existingRecap = AnnualRecap::query()
            ->where('user_id', $user->id)
            ->where('year', $year)
            ->first();

        if ($existingRecap) {
            return $this->normalizeRecap($existingRecap->snapshot ?? []);
        }

        $recap = $this->calculateRecap($user, $year, $accountNow);

        if (! empty($recap)) {
            AnnualRecap::create([
                'user_id' => $user->id,
                'year' => $year,
                'snapshot' => $recap,
                'generated_at' => now(),
            ]);
        }

        return $this->normalizeRecap($recap);
    }

    /**
     * Invalidate derived recap data for the supplied years.
     */
    public function invalidateForYears(User $user, int ...$years): void
    {
        foreach (array_unique($years) as $year) {
            Cache::forget(self::cacheKeyFor($user, $year));

            AnnualRecap::query()
                ->where('user_id', $user->id)
                ->where('year', $year)
                ->delete();
        }
    }

    public static function cacheKeyFor(User $user, int $year): string
    {
        return "user_annual_recap_{$user->id}_{$year}";
    }

    /**
     * Get the seasonal dashboard card state for the annual recap.
     */
    public function getDashboardCardState(?CarbonInterface $now = null): array
    {
        $now = $now?->copy() ?? now();
        $year = $now->year;
        $timezone = $now->getTimezone();
        $start = Carbon::create($year, 12, 1, 0, 0, 0, $timezone)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31, 0, 0, 0, $timezone)->endOfDay();
        $windowEnd = $yearEnd->copy()->addWeek();

        if (! $now->between($start, $windowEnd)) {
            $previousYear = $year - 1;
            $previousStart = Carbon::create($previousYear, 12, 1, 0, 0, 0, $timezone)->startOfDay();
            $previousYearEnd = Carbon::create($previousYear, 12, 31, 0, 0, 0, $timezone)->endOfDay();
            $previousWindowEnd = $previousYearEnd->copy()->addWeek();

            if ($now->between($previousStart, $previousWindowEnd)) {
                $year = $previousYear;
                $start = $previousStart;
                $yearEnd = $previousYearEnd;
                $windowEnd = $previousWindowEnd;
            }
        }

        $isInWindow = $now->between($start, $windowEnd);
        $viewExists = View::exists("annual-recap.{$year}.show");
        $labelEnd = $now->lt($yearEnd) ? $now : $yearEnd;
        $isFinal = $now->gt($yearEnd);

        return [
            'show' => $isInWindow && $viewExists,
            'year' => $year,
            'end_label' => $labelEnd->format('M j, Y'),
            'is_final' => $isFinal,
        ];
    }

    private function getLiveRecap(User $user, int $year, CarbonInterface $accountNow): array
    {
        if ($year !== $accountNow->year) {
            return $this->normalizeRecap($this->calculateRecap($user, $year, $accountNow));
        }

        $cacheKey = self::cacheKeyFor($user, $year);
        $ttl = $accountNow->endOfDay();

        return $this->normalizeRecap(
            Cache::remember($cacheKey, $ttl, fn () => $this->calculateRecap($user, $year, $accountNow))
        );
    }

    private function calculateRecap(User $user, int $year, CarbonInterface $accountNow): array
    {
        $timezone = $accountNow->getTimezone();
        $yearStart = Carbon::create($year, 1, 1, 0, 0, 0, $timezone)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31, 0, 0, 0, $timezone)->endOfDay();
        $userStart = Carbon::parse($user->created_at)->setTimezone($timezone)->startOfDay();
        $today = $accountNow->copy()->startOfDay();
        $firstLogDate = $user->readingLogs()
            ->whereBetween('date_read', [$yearStart, $yearEnd])
            ->min('date_read');

        if (! $firstLogDate) {
            return [];
        }

        $logStart = Carbon::parse($firstLogDate, $timezone)->startOfDay();
        $earliestStart = $logStart->lt($userStart) ? $logStart : $userStart;
        $effectiveStart = $earliestStart->gt($yearStart) ? $earliestStart : $yearStart;
        $effectiveEnd = $year === $today->year ? $today : $yearEnd;

        if ($effectiveEnd->lt($effectiveStart)) {
            return [];
        }

        // Get all logs for the recap window
        $logs = $user->readingLogs()
            ->whereBetween('date_read', [$effectiveStart, $effectiveEnd])
            ->get();

        if ($logs->isEmpty()) {
            return [];
        }

        return [
            'year' => $year,
            'total_chapters_read' => $logs->count(),
            'active_days_count' => $logs->pluck('date_read')->unique()->count(),
            'yearly_streak' => $this->calculateYearlyStreak($logs),
            'top_books' => $this->calculateTopBooks($user, $logs),
            'books_completed_count' => $this->calculateBooksCompleted($user, $year, $effectiveStart, $effectiveEnd),
            'reader_personality' => $this->determineReaderPersonality($logs, $year, $effectiveStart, $effectiveEnd),
            'heatmap_data' => $this->generateHeatmapData($logs),
            'first_reading' => $logs->sortBy('date_read')->first()?->date_read,
            'last_reading' => $logs->sortByDesc('date_read')->first()?->date_read,
        ];
    }

    private function normalizeRecap(array $recap): array
    {
        if (empty($recap)) {
            return [];
        }

        $recap['top_books'] = collect($recap['top_books'] ?? []);

        return $recap;
    }

    /**
     * Calculate the longest streak within the given logs.
     * Assumes logs are already filtered by the target year.
     * Returns ['count' => int, 'start' => ?string, 'end' => ?string]
     */
    private function calculateYearlyStreak(Collection $logs): array
    {
        $dates = $logs->pluck('date_read')
            ->map(fn ($date) => Carbon::parse($date)->startOfDay()->format('Y-m-d'))
            ->unique()
            ->sort()
            ->values()
            ->map(fn ($date) => Carbon::parse($date));

        if ($dates->isEmpty()) {
            return [
                'count' => 0,
                'start' => null,
                'end' => null,
            ];
        }

        $maxStreak = 1;
        $currentStreak = 1;

        $currentStreakStart = $dates->first();
        $currentStreakEnd = $dates->first();

        $bestStreakStart = $dates->first();
        $bestStreakEnd = $dates->first();

        $previousDate = $dates->first();

        foreach ($dates->skip(1) as $date) {
            if ((int) $previousDate->diffInDays($date) === 1) {
                $currentStreak++;
                $currentStreakEnd = $date;
            } else {
                // Check if previous streak was the best so far
                if ($currentStreak > $maxStreak) {
                    $maxStreak = $currentStreak;
                    $bestStreakStart = $currentStreakStart;
                    $bestStreakEnd = $currentStreakEnd;
                }

                // Reset
                $currentStreak = 1;
                $currentStreakStart = $date;
                $currentStreakEnd = $date;
            }
            $previousDate = $date;
        }

        // Final check after loop
        if ($currentStreak > $maxStreak) {
            $maxStreak = $currentStreak;
            $bestStreakStart = $currentStreakStart;
            $bestStreakEnd = $currentStreakEnd;
        }

        return [
            'count' => $maxStreak,
            'start' => $bestStreakStart?->format('M j'),
            'end' => $bestStreakEnd?->format('M j'),
        ];
    }

    /**
     * Identify the books with the most chapters read.
     */
    private function calculateTopBooks(User $user, Collection $logs, int $limit = 3): Collection
    {
        if ($logs->isEmpty()) {
            return collect();
        }

        $includeDeuterocanonical = $user->includesDeuterocanonicalBooks();
        $visibleLogs = $logs->filter(
            fn ($log): bool => $this->bibleService->validateChapterNumber(
                (int) $log->book_id,
                (int) $log->chapter,
                $includeDeuterocanonical
            )
        );

        return $visibleLogs->groupBy('book_id')
            ->sortByDesc(fn ($group) => $group->count())
            ->take($limit)
            ->map(function ($group, $bookId) {
                return [
                    'id' => $bookId,
                    'name' => $this->bibleService->getLocalizedBookName($bookId, includeDeuterocanonical: true),
                    'count' => $group->count(),
                ];
            })
            ->values();
    }

    /**
     * Calculate how many books were completed in the recap window.
     * Uses BookProgress but filters by last_updated in the effective window.
     * Note: This is an approximation as BookProgress only stores "last_updated",
     * but for a recap it's a "good enough" proxy for recent achievements.
     */
    private function calculateBooksCompleted(
        User $user,
        int $year,
        CarbonInterface $effectiveStart,
        CarbonInterface $effectiveEnd
    ): int {
        $databaseTimezone = config('app.timezone');

        return $user->bookProgress()
            ->where('is_completed', true)
            ->whereBetween('last_updated', [
                $effectiveStart->copy()->setTimezone($databaseTimezone),
                $effectiveEnd->copy()->setTimezone($databaseTimezone),
            ])
            ->count();
    }

    /**
     * Determine a fun personality type based on reading habits.
     * Uses percentage-based thresholds to account for partial years.
     */
    private function determineReaderPersonality(
        Collection $logs,
        int $year,
        CarbonInterface $effectiveStart,
        CarbonInterface $effectiveEnd
    ): array {
        // Calculate available days based on the effective window (signup or year start).
        $yearStart = Carbon::create($year, 1, 1, 0, 0, 0, $effectiveStart->getTimezone());
        $availableDays = $effectiveEnd->lt($effectiveStart)
            ? 0
            : $effectiveStart->diffInDays($effectiveEnd) + 1;

        $windowedCount = $logs->count();
        $windowedUniqueDays = $logs->pluck('date_read')->unique()->count();

        // Calculate consistency rate and chapters per day within the effective window.
        $consistencyRate = $availableDays > 0 ? $windowedUniqueDays / $availableDays : 0;
        $chaptersPerDay = $windowedUniqueDays > 0 ? $windowedCount / $windowedUniqueDays : 0;

        $yearEnd = Carbon::create($year, 12, 31, 0, 0, 0, $effectiveEnd->getTimezone());
        $isPartialYear = $effectiveStart->gt($yearStart) || $effectiveEnd->lt($yearEnd);
        $monthsAvailable = $isPartialYear ? (int) ceil($availableDays / 30) : 12;

        // 1. Daily Devotee: ≥80% consistency
        if ($consistencyRate >= 0.80) {
            $name = 'Daily Devotee';
            $description = $isPartialYear
                ? "In just {$monthsAvailable} months, you made the Word a daily habit."
                : 'Your consistency is inspiring. You made the Word a daily habit.';
            $stats = round($consistencyRate * 100).'% consistency';
        } elseif ($consistencyRate >= 0.55) {
            // 2. Faithful Follower: ≥55% consistency
            $name = 'Faithful Follower';
            $description = $isPartialYear
                ? 'You showed up consistently since you started tracking. Well done!'
                : 'You showed up consistently throughout the year. Well done!';
            $stats = round($consistencyRate * 100).'% consistency';
        } elseif ($chaptersPerDay >= 2.0) {
            // 3. Deep Diver: ≥2 chapters per active day
            $name = 'Deep Diver';
            $description = 'When you read, you go deep. You cover a lot of ground in each sitting.';
            $stats = round($chaptersPerDay, 1).' chapters / day';
        } else {
            // 4. Weekend Warrior: Reads mostly on Sat/Sun
            $weekendReads = $logs->filter(function ($log) {
                $dayOfWeek = Carbon::parse($log->date_read)->dayOfWeek;

                return $dayOfWeek === Carbon::SATURDAY || $dayOfWeek === Carbon::SUNDAY;
            })->count();

            if ($windowedCount > 20 && ($weekendReads / $windowedCount) > 0.4) {
                $name = 'Weekend Warrior';
                $description = 'You prefer to spend your weekends soaking in the Scriptures.';
                $stats = $weekendReads.' weekend chapters';
            } else {
                // Default: Steady Seeker
                $name = 'Steady Seeker';
                $description = 'You are on a journey, seeking God at your own steady pace.';
                $stats = $windowedUniqueDays.' active days';
            }
        }

        return [
            'name' => $name,
            'description' => $description,
            'stats' => $stats,
        ];
    }

    /**
     * Generate simple daily counts for a heatmap.
     */
    private function generateHeatmapData(Collection $logs): array
    {
        return $logs->groupBy(fn ($log) => Carbon::parse($log->date_read)->format('Y-m-d'))
            ->map(fn ($group) => $group->count())
            ->toArray();
    }
}

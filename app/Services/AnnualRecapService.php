<?php

namespace App\Services;

use App\Models\AnnualRecap;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;

class AnnualRecapService
{
    /**
     * The date the app launched. Used to calculate available reading days for partial years.
     */
    public const LAUNCH_DATE = '2025-08-01';

    public function __construct(
        private BibleReferenceService $bibleService
    ) {}

    /**
     * Get the full annual recap for a user for a specific year.
     */
    public function getRecap(User $user, int $year): array
    {
        if ($year >= now()->year) {
            return $this->getLiveRecap($user, $year);
        }

        $existingRecap = AnnualRecap::query()
            ->where('user_id', $user->id)
            ->where('year', $year)
            ->first();

        if ($existingRecap) {
            return $this->normalizeRecap($existingRecap->snapshot ?? []);
        }

        $recap = $this->calculateRecap($user, $year);

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

    public static function cacheKeyFor(User $user, int $year): string
    {
        return "user_annual_recap_{$user->id}_{$year}";
    }

    /**
     * Get the seasonal dashboard card state for the annual recap.
     */
    public function getDashboardCardState(?Carbon $now = null): array
    {
        $now = $now?->copy() ?? now();
        $year = $now->year;
        $start = Carbon::create($year, 12, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->endOfDay();

        $isInWindow = $now->between($start, $end);
        $viewExists = View::exists("annual-recap.{$year}.show");

        return [
            'show' => $isInWindow && $viewExists,
            'year' => $year,
            'end_label' => $end->format('M j, Y'),
        ];
    }

    private function getLiveRecap(User $user, int $year): array
    {
        if ($year !== now()->year) {
            return $this->normalizeRecap($this->calculateRecap($user, $year));
        }

        $cacheKey = self::cacheKeyFor($user, $year);
        $ttl = now()->endOfDay();

        return $this->normalizeRecap(
            Cache::remember($cacheKey, $ttl, fn () => $this->calculateRecap($user, $year))
        );
    }

    private function calculateRecap(User $user, int $year): array
    {
        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        // Get all logs for the year
        $logs = $user->readingLogs()
            ->whereBetween('date_read', [$startDate, $endDate])
            ->get();

        if ($logs->isEmpty()) {
            return [];
        }

        return [
            'year' => $year,
            'total_chapters_read' => $logs->count(),
            'active_days_count' => $logs->pluck('date_read')->unique()->count(),
            'yearly_streak' => $this->calculateYearlyStreak($logs),
            'top_books' => $this->calculateTopBooks($logs),
            'books_completed_count' => $this->calculateBooksCompleted($user, $year),
            'reader_personality' => $this->determineReaderPersonality($logs, $year),
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
    private function calculateTopBooks(Collection $logs, int $limit = 3): Collection
    {
        if ($logs->isEmpty()) {
            return collect();
        }

        return $logs->groupBy('book_id')
            ->sortByDesc(fn ($group) => $group->count())
            ->take($limit)
            ->map(function ($group, $bookId) {
                return [
                    'id' => $bookId,
                    'name' => $this->bibleService->getLocalizedBookName($bookId),
                    'count' => $group->count(),
                ];
            })
            ->values();
    }

    /**
     * Calculate how many books were completed in the given year.
     * Uses BookProgress but filters by last_updated in the target year.
     * Note: This is an approximation as BookProgress only stores "last_updated",
     * but for a recap it's a "good enough" proxy for recent achievements.
     */
    private function calculateBooksCompleted(User $user, int $year): int
    {
        return $user->bookProgress()
            ->where('is_completed', true)
            ->whereYear('last_updated', $year)
            ->count();
    }

    /**
     * Determine a fun personality type based on reading habits.
     * Uses percentage-based thresholds to account for partial years (e.g., 2025 launch).
     */
    private function determineReaderPersonality(Collection $logs, int $year): array
    {
        $count = $logs->count();
        $uniqueDays = $logs->pluck('date_read')->unique()->count();

        // Calculate available days based on launch date or start of year
        $yearStart = Carbon::create($year, 1, 1);
        $launchDate = Carbon::parse(self::LAUNCH_DATE);
        $effectiveStart = $launchDate->year === $year && $launchDate->gt($yearStart)
            ? $launchDate
            : $yearStart;
        $yearEnd = Carbon::create($year, 12, 31);
        $today = Carbon::today();
        $effectiveEnd = $year === $today->year ? $today : $yearEnd;
        $availableDays = $effectiveEnd->lt($effectiveStart)
            ? 0
            : $effectiveStart->diffInDays($effectiveEnd) + 1;

        // Calculate consistency rate and chapters per day
        $consistencyRate = $availableDays > 0 ? $uniqueDays / $availableDays : 0;
        $chaptersPerDay = $uniqueDays > 0 ? $count / $uniqueDays : 0;

        // Determine if this is the launch year for special messaging
        $isLaunchYear = $launchDate->year === $year;
        $monthsAvailable = $isLaunchYear ? (int) ceil($availableDays / 30) : 12;

        // 1. Daily Devotee: ≥80% consistency
        if ($consistencyRate >= 0.80) {
            $name = 'Daily Devotee';
            $description = $isLaunchYear
                ? "In just {$monthsAvailable} months, you made the Word a daily habit."
                : 'Your consistency is inspiring. You made the Word a daily habit.';
            $stats = round($consistencyRate * 100).'% consistency';
        } elseif ($consistencyRate >= 0.55) {
            // 2. Faithful Follower: ≥55% consistency
            $name = 'Faithful Follower';
            $description = $isLaunchYear
                ? 'You showed up consistently since we launched. Well done!'
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

            if ($count > 20 && ($weekendReads / $count) > 0.4) {
                $name = 'Weekend Warrior';
                $description = 'You prefer to spend your weekends soaking in the Scriptures.';
                $stats = $weekendReads.' weekend chapters';
            } else {
                // Default: Steady Seeker
                $name = 'Steady Seeker';
                $description = 'You are on a journey, seeking God at your own steady pace.';
                $stats = $uniqueDays.' active days';
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

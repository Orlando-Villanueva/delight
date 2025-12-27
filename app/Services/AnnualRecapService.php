<?php

namespace App\Services;

use App\Models\AnnualRecap;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AnnualRecapService
{
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
            return $existingRecap->snapshot ?? [];
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

        return $recap;
    }

    public static function cacheKeyFor(User $user, int $year): string
    {
        return "user_annual_recap_{$user->id}_{$year}";
    }

    private function getLiveRecap(User $user, int $year): array
    {
        if ($year !== now()->year) {
            return $this->calculateRecap($user, $year);
        }

        $cacheKey = self::cacheKeyFor($user, $year);
        $ttl = now()->endOfDay();

        return Cache::remember($cacheKey, $ttl, fn () => $this->calculateRecap($user, $year));
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
            'reader_personality' => $this->determineReaderPersonality($logs),
            'heatmap_data' => $this->generateHeatmapData($logs),
            'first_reading' => $logs->sortBy('date_read')->first()?->date_read,
            'last_reading' => $logs->sortByDesc('date_read')->first()?->date_read,
        ];
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
     */
    /**
     * Determine a fun personality type based on reading habits.
     */
    private function determineReaderPersonality(Collection $logs): array
    {
        $count = $logs->count();
        $uniqueDays = $logs->pluck('date_read')->unique()->count();

        // 1. Daily Devotee: Very high consistency
        if ($uniqueDays > 300) {
            $name = 'Daily Devotee';
            $description = 'Your consistency is inspiring. You made the Word a daily habit.';
            $stats = $uniqueDays.' days read';
        } elseif ($uniqueDays > 200) {
            // 2. Faithful Follower: High consistency
            $name = 'Faithful Follower';
            $description = 'You showed up consistently throughout the year. Well done!';
            $stats = $uniqueDays.' days read';
        } elseif ($count > 300) {
            // 3. Deep Diver: High volume but perhaps bunched up (batch reader)
            $name = 'Deep Diver';
            $description = 'When you read, you go deep. You cover a lot of ground in each sitting.';
            $stats = round($count / max($uniqueDays, 1), 1).' chapters / day';
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

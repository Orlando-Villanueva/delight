<?php

namespace App\Services;

use App\Models\ReadingLog;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class WeeklyGoalService
{
    /**
     * Default weekly reading goal (4 days per week).
     */
    private const int DEFAULT_WEEKLY_GOAL = 4;

    private const int FIRST_DAY_OF_WEEK = Carbon::SUNDAY;

    private const int LAST_DAY_OF_WEEK = Carbon::SATURDAY;

    public function __construct()
    {
        // Self-contained service with no dependencies
    }

    /**
     * Generate consistent cache keys for weekly goal related data
     */
    private function getCacheKey(string $type, User $user, string $suffix = ''): string
    {
        $baseKey = "weekly_goal_{$type}_{$user->id}";

        return $suffix ? "{$baseKey}_{$suffix}" : $baseKey;
    }

    /**
     * Get complete weekly goal data structure for a user.
     */
    public function getWeeklyGoalData(User $user): array
    {
        if (! $user || ! $user->id) {
            throw new InvalidArgumentException('Valid user with ID required');
        }

        try {
            $weekStart = now()->startOfWeek(self::FIRST_DAY_OF_WEEK);
            $currentProgress = $this->calculateWeekProgress($user, now());
            $weeklyTarget = self::DEFAULT_WEEKLY_GOAL;

            return [
                'current_progress' => $currentProgress,
                'weekly_target' => $weeklyTarget,
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekStart->copy()->endOfWeek(self::LAST_DAY_OF_WEEK)->toDateString(),
                'is_goal_achieved' => $currentProgress >= $weeklyTarget,
                'progress_percentage' => $weeklyTarget > 0 ? round(($currentProgress / $weeklyTarget) * 100, 2) : 0,
                'message' => $this->getProgressMessage($currentProgress, $weeklyTarget),
            ];
        } catch (Exception $e) {
            Log::error('Error getting weekly goal data', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->getDefaultWeeklyGoalData();
        }
    }

    /**
     * Calculate reading progress for a specific week.
     * Returns the number of distinct days with readings in the specified week.
     */
    public function calculateWeekProgress(User $user, Carbon $referenceDate): int
    {
        try {
            $weekStart = $referenceDate->copy()->startOfWeek(self::FIRST_DAY_OF_WEEK);
            $weekEnd = $referenceDate->copy()->endOfWeek(self::LAST_DAY_OF_WEEK);

            // Count distinct dates using Eloquent model with scopes
            return ReadingLog::forUser($user->id)
                ->dateRange($weekStart->toDateString(), $weekEnd->toDateString())
                ->distinct()
                ->count('date_read');
        } catch (Exception $e) {
            Log::error('Error calculating week progress', [
                'user_id' => $user->id,
                'reference_date' => $referenceDate->toDateString(),
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get reading days count for current week (Sunday to Saturday).
     * Convenience method that uses calculateWeekProgress with current date.
     */
    public function getThisWeekReadingDays(User $user): int
    {
        return $this->calculateWeekProgress($user, now());
    }

    /**
     * Get a motivational message based on progress.
     */
    private function getProgressMessage(int $currentProgress, int $weeklyTarget): string
    {
        if ($currentProgress >= $weeklyTarget) {
            return 'Great job! You\'ve achieved your weekly goal!';
        } elseif ($currentProgress > 0) {
            $remaining = $weeklyTarget - $currentProgress;

            return "You're making progress! {$remaining} more day".($remaining === 1 ? '' : 's').' to reach your goal.';
        } else {
            return 'Start your week strong with your first reading!';
        }
    }

    /**
     * Check if a user achieved their weekly goal for a specific week.
     * Returns true if the user read on 4 or more days during the specified week.
     */
    public function isWeekGoalAchieved(User $user, Carbon $weekStart): bool
    {
        try {
            $daysRead = $this->calculateWeekProgress($user, $weekStart);

            return $daysRead >= self::DEFAULT_WEEKLY_GOAL;
        } catch (Exception $e) {
            Log::error('Error checking if week goal is achieved', [
                'user_id' => $user->id,
                'week_start' => $weekStart->toDateString(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get default weekly goal data for error fallback.
     */
    private function getDefaultWeeklyGoalData(): array
    {
        $weekStart = now()->startOfWeek(self::FIRST_DAY_OF_WEEK);
        $weeklyTarget = self::DEFAULT_WEEKLY_GOAL;

        return [
            'current_progress' => 0,
            'weekly_target' => $weeklyTarget,
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekStart->copy()->endOfWeek(self::LAST_DAY_OF_WEEK)->toDateString(),
            'is_goal_achieved' => false,
            'progress_percentage' => 0,
            'message' => 'Start your week strong with your first reading!',
        ];
    }
}

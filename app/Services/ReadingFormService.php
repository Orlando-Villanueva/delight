<?php

namespace App\Services;

use App\Models\User;

class ReadingFormService
{
    public function __construct(
        private ReadingLogService $readingLogService
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
     * Get yesterday availability logic and user reading status for the form.
     * Yesterday is always available for missed-log recovery.
     */
    public function getFormContextData(User $user): array
    {
        $hasReadToday = $this->hasReadToday($user);

        $hasReadYesterday = $user->readingLogs()
            ->whereDate('date_read', today()->subDay())
            ->exists();

        $hasReadingTwoDaysAgo = $user->readingLogs()
            ->whereDate('date_read', today()->subDays(2))
            ->exists();

        $currentStreak = $this->readingLogService->calculateCurrentStreak($user);

        $allowYesterday = true;

        return [
            'allowYesterday' => $allowYesterday,
            'hasReadToday' => $hasReadToday,
            'hasReadYesterday' => $hasReadYesterday,
            'hasReadingTwoDaysAgo' => $hasReadingTwoDaysAgo,
            'currentStreak' => $currentStreak,
        ];
    }
}

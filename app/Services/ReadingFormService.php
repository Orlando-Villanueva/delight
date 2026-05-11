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
     * This determines if the "yesterday" option should be available based on streak preservation.
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

        // Check if user is new (created today) to prevent logging for yesterday before they existed
        $isNewUser = $user->created_at->isToday();

        // Only surface yesterday when it can repair the active streak bridge between
        // today and the day before yesterday. Otherwise the date choice adds noise.
        $allowYesterday = ! $hasReadYesterday && ! $isNewUser && $hasReadingTwoDaysAgo;

        return [
            'allowYesterday' => $allowYesterday,
            'hasReadToday' => $hasReadToday,
            'hasReadYesterday' => $hasReadYesterday,
            'hasReadingTwoDaysAgo' => $hasReadingTwoDaysAgo,
            'currentStreak' => $currentStreak,
        ];
    }
}

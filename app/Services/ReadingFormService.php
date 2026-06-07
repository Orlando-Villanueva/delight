<?php

namespace App\Services;

use App\Models\User;

class ReadingFormService
{
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
     * Get yesterday availability logic for the form.
     * Yesterday is always available for missed-log recovery.
     */
    public function getFormContextData(User $user): array
    {
        return [
            'allowYesterday' => true,
        ];
    }
}

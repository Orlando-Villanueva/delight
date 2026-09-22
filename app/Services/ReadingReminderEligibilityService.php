<?php

namespace App\Services;

use App\Enums\ReadingReminderType;
use App\Models\User;
use Carbon\CarbonInterface;

class ReadingReminderEligibilityService
{
    public const string DAILY_TIME = ReadingReminderConditionService::DAILY_TIME;

    public const string STREAK_WARNING_TIME = ReadingReminderConditionService::STREAK_WARNING_TIME;

    public function __construct(
        private ReadingReminderConditionService $conditions,
    ) {}

    public function isEligible(User $user, string $reminderType, ?CarbonInterface $referenceTime = null): bool
    {
        $hasPushSubscription = $user->relationLoaded('pushSubscriptions')
            ? $user->pushSubscriptions->isNotEmpty()
            : $user->pushSubscriptions()->exists();

        if (! $hasPushSubscription) {
            return false;
        }

        if (! $this->conditions->isEligible($user, $reminderType, $referenceTime)) {
            return false;
        }

        return match ($reminderType) {
            ReadingReminderType::DailyReading->value => $user->hasDailyReadingReminderEnabled(),
            ReadingReminderType::StreakRisk->value => $user->hasStreakWarningEnabled(),
            default => false,
        };
    }

    public function reminderDateFor(User $user, ?CarbonInterface $referenceTime = null): string
    {
        return $this->conditions->reminderDateFor($user, $referenceTime);
    }
}

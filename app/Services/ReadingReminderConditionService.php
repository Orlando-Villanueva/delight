<?php

namespace App\Services;

use App\Enums\ReadingReminderType;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Shared account-calendar rules for reminder timing and reading state.
 *
 * Channel-specific services decide whether a user has opted in and has a
 * usable destination. This service only answers whether a reminder is due.
 */
class ReadingReminderConditionService
{
    public const string DAILY_TIME = '09:00';

    public const string STREAK_WARNING_TIME = '18:00';

    public function __construct(private ReadingCalendarService $readingCalendar) {}

    public function isEligible(User $user, string $reminderType, ?CarbonInterface $referenceTime = null): bool
    {
        if (! $this->readingCalendar->hasEstablishedTimezone($user)) {
            return false;
        }

        $localNow = $this->readingCalendar->nowFor($user, $referenceTime);

        return match ($reminderType) {
            ReadingReminderType::DailyReading->value => $this->isDailyEligible($user, $localNow),
            ReadingReminderType::StreakRisk->value => $this->isStreakEligible($user, $localNow),
            default => false,
        };
    }

    public function reminderDateFor(User $user, ?CarbonInterface $referenceTime = null): string
    {
        return $this->readingCalendar->nowFor($user, $referenceTime)->toDateString();
    }

    private function isDailyEligible(User $user, CarbonInterface $localNow): bool
    {
        return $this->isAfterLocalTime($localNow, self::DAILY_TIME)
            && ! $this->hasReadOnLocalDate($user, $localNow->toDateString());
    }

    private function isStreakEligible(User $user, CarbonInterface $localNow): bool
    {
        $today = $localNow->toDateString();

        return $this->isAfterLocalTime($localNow, self::STREAK_WARNING_TIME)
            && ! $this->hasReadOnLocalDate($user, $today)
            && $this->hasReadOnLocalDate($user, $localNow->copy()->subDay()->toDateString());
    }

    private function isAfterLocalTime(CarbonInterface $localNow, string $time): bool
    {
        return $localNow->greaterThanOrEqualTo($localNow->copy()->setTimeFromTimeString($time));
    }

    private function hasReadOnLocalDate(User $user, string $date): bool
    {
        return $user->readingLogs()->whereDate('date_read', $date)->exists();
    }
}

<?php

namespace App\Services;

use App\Models\PushReminderDelivery;
use App\Models\User;
use Carbon\CarbonInterface;
use DateTimeZone;
use Throwable;

class ReadingReminderEligibilityService
{
    public const string DAILY_TIME = '09:00';

    public const string STREAK_WARNING_TIME = '18:00';

    public function isEligible(User $user, string $reminderType, ?CarbonInterface $referenceTime = null): bool
    {
        $hasPushSubscription = $user->relationLoaded('pushSubscriptions')
            ? $user->pushSubscriptions->isNotEmpty()
            : $user->pushSubscriptions()->exists();

        if (! $hasPushSubscription) {
            return false;
        }

        return match ($reminderType) {
            PushReminderDelivery::TYPE_DAILY_READING => $this->isDailyReadingReminderEligible($user, $referenceTime),
            PushReminderDelivery::TYPE_STREAK_RISK => $this->isStreakRiskReminderEligible($user, $referenceTime),
            default => false,
        };
    }

    public function reminderDateFor(User $user, ?CarbonInterface $referenceTime = null): string
    {
        return $this->localNow($user, $referenceTime)->toDateString();
    }

    private function isDailyReadingReminderEligible(User $user, ?CarbonInterface $referenceTime = null): bool
    {
        if (! $user->hasDailyReadingReminderEnabled()) {
            return false;
        }

        $localNow = $this->localNow($user, $referenceTime);

        return $this->isAfterLocalTime($localNow, self::DAILY_TIME)
            && ! $this->hasReadOnLocalDate($user, $localNow->toDateString());
    }

    private function isStreakRiskReminderEligible(User $user, ?CarbonInterface $referenceTime = null): bool
    {
        if (! $user->hasStreakWarningEnabled()) {
            return false;
        }

        $localNow = $this->localNow($user, $referenceTime);
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
        return $user->readingLogs()
            ->whereDate('date_read', $date)
            ->exists();
    }

    private function localNow(User $user, ?CarbonInterface $referenceTime = null): CarbonInterface
    {
        try {
            $timezone = new DateTimeZone($user->pushNotificationTimezone());
        } catch (Throwable) {
            $timezone = new DateTimeZone(config('app.timezone'));
        }

        return ($referenceTime ?? now())->copy()->setTimezone($timezone);
    }
}

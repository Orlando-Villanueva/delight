<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;

class ReadingCalendarService
{
    public function timezoneFor(User $user): string
    {
        foreach ([$user->reading_timezone, $user->push_notification_timezone] as $timezone) {
            if ($this->isValidTimezone($timezone)) {
                return $timezone;
            }
        }

        return config('app.timezone');
    }

    /**
     * Establish a persisted account's calendar once, without saving the provisional fallback.
     */
    public function establishTimezone(User $user, ?string $reportedTimezone = null): string
    {
        $user->refresh();

        if ($user->reading_timezone === null) {
            $timezone = $this->isValidTimezone($user->push_notification_timezone)
                ? $user->push_notification_timezone
                : $reportedTimezone;

            if ($this->isValidTimezone($timezone)) {
                User::query()->whereKey($user->id)->whereNull('reading_timezone')
                    ->update(['reading_timezone' => $timezone]);

                $user->refresh();
            }
        }

        return $this->timezoneFor($user);
    }

    public function nowFor(User $user, ?CarbonInterface $referenceTime = null): CarbonImmutable
    {
        return CarbonImmutable::instance($referenceTime ?? now())->setTimezone($this->timezoneFor($user));
    }

    public function todayFor(User $user, ?CarbonInterface $referenceTime = null): CarbonImmutable
    {
        return $this->nowFor($user, $referenceTime)->startOfDay();
    }

    public function yesterdayFor(User $user, ?CarbonInterface $referenceTime = null): CarbonImmutable
    {
        return $this->todayFor($user, $referenceTime)->subDay();
    }

    public function cacheKey(User $user, string $key): string
    {
        return $key.':'.$this->timezoneFor($user).':'.$this->todayFor($user)->toDateString();
    }

    private function isValidTimezone(?string $timezone): bool
    {
        return $timezone !== null && in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true);
    }
}

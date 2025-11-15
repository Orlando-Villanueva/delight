<?php

namespace App\Enums;

use Carbon\Carbon;

enum WeeklyJourneyDayState: string
{
    case COMPLETE = 'complete';
    case MISSED = 'missed';
    case TODAY = 'today';
    case UPCOMING = 'upcoming';

    public static function resolve(Carbon $date, Carbon $today, bool $hasRead): self
    {
        $state = self::UPCOMING;

        if ($hasRead) {
            $state = self::COMPLETE;
        } elseif ($date->isSameDay($today)) {
            $state = self::TODAY;
        } elseif ($date->lessThan($today)) {
            $state = self::MISSED;
        }

        return $state;
    }

    public function description(): string
    {
        return match ($this) {
            self::COMPLETE => 'reading logged',
            self::MISSED => 'missed reading day',
            self::TODAY => 'today (not logged yet)',
            self::UPCOMING => 'not logged yet',
        };
    }
}

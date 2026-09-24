<?php

namespace App\Enums;

enum ReadingReminderType: string
{
    case DailyReading = 'daily_reading';

    case StreakRisk = 'streak_risk';
}

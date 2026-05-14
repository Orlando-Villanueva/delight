<?php

namespace App\Services;

use App\Models\ReadingLog;
use Illuminate\Support\Collection;

class ReadingLogResult
{
    /**
     * @param  Collection<int, \App\Models\UserAchievement>  $awardedAchievements
     */
    public function __construct(
        public readonly ReadingLog $log,
        public readonly Collection $awardedAchievements,
        public readonly bool $isFirstReadingOfDay = false
    ) {}
}

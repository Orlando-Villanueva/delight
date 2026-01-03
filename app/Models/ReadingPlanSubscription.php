<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingPlanSubscription extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'reading_plan_id',
        'started_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'date',
        ];
    }

    /**
     * Get the user that owns this subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the reading plan for this subscription.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(ReadingPlan::class, 'reading_plan_id');
    }

    /**
     * Get the current day number based on start date.
     * Day 1 is the subscription start date.
     */
    public function getDayNumber(?Carbon $forDate = null): int
    {
        $date = $forDate ?? Carbon::today();
        $dayNumber = $this->started_at->diffInDays($date) + 1;

        // Clamp to plan length
        $maxDays = $this->plan->getDaysCount();

        return min($dayNumber, $maxDays);
    }

    /**
     * Get today's reading from the plan.
     */
    public function getTodaysReading(?Carbon $forDate = null): ?array
    {
        $dayNumber = $this->getDayNumber($forDate);

        return $this->plan->getDayReading($dayNumber);
    }

    /**
     * Get the progress percentage (0-100).
     * Progress is based on completed days (days before today).
     */
    public function getProgress(): float
    {
        $totalDays = $this->plan->getDaysCount();

        if ($totalDays === 0) {
            return 0;
        }

        // Completed days = current day - 1 (Day 1 = 0 completed, Day 2 = 1 completed, etc.)
        $completedDays = max(0, $this->getDayNumber() - 1);

        return round(($completedDays / $totalDays) * 100, 1);
    }

    /**
     * Check if the subscription is complete.
     */
    public function isComplete(): bool
    {
        return $this->getDayNumber() >= $this->plan->getDaysCount();
    }
}

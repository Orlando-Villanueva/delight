<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReadingPlanSubscription extends Model
{
    use HasFactory;

    /**
     * Cached completed days count for the current request.
     */
    protected ?int $completedDaysCount = null;

    /**
     * Reset the cached completed days count for the current request.
     */
    public function resetCompletedDaysCountCache(): void
    {
        $this->completedDaysCount = null;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'reading_plan_id',
        'started_at',
        'is_active',
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
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active subscriptions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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
     * Alias for factory for() method.
     */
    public function readingPlan(): BelongsTo
    {
        return $this->plan();
    }

    /**
     * Get the day completions for this subscription.
     */
    public function dayCompletions(): HasMany
    {
        return $this->hasMany(ReadingPlanDayCompletion::class);
    }

    /**
     * Get the current day number based on completed plan days.
     * Day 1 is the first incomplete day.
     */
    public function getDayNumber(): int
    {
        $maxDays = $this->plan->getDaysCount();

        if ($maxDays === 0) {
            return 0;
        }

        $completedDays = $this->getCompletedDaysCount();

        return min($completedDays + 1, $maxDays);
    }

    /**
     * Get reading for the current day (or a specific day).
     */
    public function getTodaysReading(?int $dayNumber = null): ?array
    {
        $dayNumber = $dayNumber ?? $this->getDayNumber();

        return $this->plan->getDayReading($dayNumber);
    }

    /**
     * Get the number of completed days from the start of the plan.
     */
    public function getCompletedDaysCount(): int
    {
        if ($this->completedDaysCount !== null) {
            return $this->completedDaysCount;
        }

        $totalDays = $this->plan->getDaysCount();

        if ($totalDays === 0) {
            return $this->completedDaysCount = 0;
        }

        // Query completions from junction table with associated reading logs
        $completions = $this->dayCompletions()
            ->with('readingLog:id,book_id,chapter')
            ->get();

        $logsByDay = $completions
            ->filter(fn ($completion) => $completion->readingLog !== null)
            ->groupBy('reading_plan_day')
            ->map(fn ($dayCompletions) => $dayCompletions->map(fn ($c) => $c->readingLog));

        $completedDays = 0;

        for ($day = 1; $day <= $totalDays; $day++) {
            $reading = $this->plan->getDayReading($day);

            if (! $reading) {
                break;
            }

            $chapters = $reading['chapters'] ?? [];

            if (count($chapters) === 0) {
                $completedDays++;

                continue;
            }

            $expectedKeys = array_map(function ($chapter) {
                return $chapter['book_id'].'-'.$chapter['chapter'];
            }, $chapters);

            $loggedKeys = $logsByDay->get($day, collect())
                ->map(fn ($log) => $log->book_id.'-'.$log->chapter)
                ->unique()
                ->toArray();

            if (count(array_diff($expectedKeys, $loggedKeys)) === 0) {
                $completedDays++;
            } else {
                break;
            }
        }

        return $this->completedDaysCount = $completedDays;
    }

    /**
     * Get the progress percentage (0-100).
     * Progress is based on completed days.
     */
    public function getProgress(): float
    {
        $totalDays = $this->plan->getDaysCount();

        if ($totalDays === 0) {
            return 0;
        }

        $completedDays = $this->getCompletedDaysCount();

        return round(($completedDays / $totalDays) * 100, 1);
    }

    /**
     * Check if the subscription is complete.
     */
    public function isComplete(): bool
    {
        return $this->getCompletedDaysCount() >= $this->plan->getDaysCount();
    }
}

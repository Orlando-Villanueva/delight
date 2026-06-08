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
     * Cached completed plan day numbers for the current request.
     *
     * @var array<int, int>|null
     */
    protected ?array $completedDayNumbers = null;

    /**
     * Reset the cached completed days count for the current request.
     */
    public function resetCompletedDaysCountCache(): void
    {
        $this->completedDaysCount = null;
        $this->completedDayNumbers = null;
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
        'start_day',
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
            'start_day' => 'integer',
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
     * Alias for the plan() relationship.
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
     * Get the first incomplete day from where tracking started.
     */
    public function getDayNumber(): int
    {
        $trackedDayNumbers = $this->getTrackedDayNumbers();

        if ($trackedDayNumbers === []) {
            return 0;
        }

        $completedDayNumbers = $this->getCompletedDayNumbers();

        foreach ($trackedDayNumbers as $dayNumber) {
            if (! in_array($dayNumber, $completedDayNumbers, true)) {
                return $dayNumber;
            }
        }

        return $trackedDayNumbers[array_key_last($trackedDayNumbers)];
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
     * Get the number of completed days within the tracked range.
     */
    public function getCompletedDaysCount(): int
    {
        if ($this->completedDaysCount !== null) {
            return $this->completedDaysCount;
        }

        return $this->completedDaysCount = count($this->getCompletedDayNumbers());
    }

    /**
     * Get the number of plan days included in this subscription's tracked range.
     */
    public function getTrackedDaysCount(): int
    {
        return count($this->getTrackedDayNumbers());
    }

    /**
     * Determine whether a plan day is before this subscription began tracking.
     */
    public function isBeforeTracking(int $dayNumber): bool
    {
        return $dayNumber < $this->getStartDay();
    }

    /**
     * Get the progress percentage (0-100).
     * Progress is based on completed days.
     */
    public function getProgress(): float
    {
        $trackedDays = $this->getTrackedDaysCount();

        if ($trackedDays === 0) {
            return 0;
        }

        $completedDays = $this->getCompletedDaysCount();

        return round(($completedDays / $trackedDays) * 100, 1);
    }

    /**
     * Check if the subscription is complete.
     */
    public function isComplete(): bool
    {
        return $this->getCompletedDaysCount() >= $this->getTrackedDaysCount();
    }

    private function getStartDay(): int
    {
        return $this->plan->getValidDayNumber($this->start_day ?? null, $this->plan->getFirstDayNumber());
    }

    /**
     * Get tracked plan day numbers for this subscription.
     *
     * @return array<int, int>
     */
    private function getTrackedDayNumbers(): array
    {
        $startDay = $this->getStartDay();

        if ($startDay === 0) {
            return [];
        }

        return $this->plan->getDayNumbersFrom($startDay);
    }

    /**
     * Get fully completed plan day numbers within the tracked range.
     *
     * @return array<int, int>
     */
    private function getCompletedDayNumbers(): array
    {
        if ($this->completedDayNumbers !== null) {
            return $this->completedDayNumbers;
        }

        $trackedDayNumbers = $this->getTrackedDayNumbers();

        if ($trackedDayNumbers === []) {
            return $this->completedDayNumbers = [];
        }

        $logsByDay = $this->dayCompletions()
            ->with('readingLog:id,book_id,chapter')
            ->get()
            ->filter(fn ($completion) => $completion->readingLog !== null)
            ->groupBy('reading_plan_day')
            ->map(fn ($dayCompletions) => $dayCompletions->map(fn ($completion) => $completion->readingLog));

        $completedDayNumbers = [];

        foreach ($trackedDayNumbers as $dayNumber) {
            $reading = $this->plan->getDayReading($dayNumber);

            if (! $reading) {
                continue;
            }

            $expectedKeys = collect($reading['chapters'] ?? [])
                ->map(fn ($chapter) => $chapter['book_id'].'-'.$chapter['chapter'])
                ->all();
            $loggedKeys = $logsByDay->get($dayNumber, collect())
                ->map(fn ($log) => $log->book_id.'-'.$log->chapter)
                ->unique()
                ->all();

            if (count(array_diff($expectedKeys, $loggedKeys)) === 0) {
                $completedDayNumbers[] = $dayNumber;
            }
        }

        return $this->completedDayNumbers = $completedDayNumbers;
    }
}

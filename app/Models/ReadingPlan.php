<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReadingPlan extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'slug',
        'name',
        'description',
        'days',
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
            'days' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the subscriptions for this plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(ReadingPlanSubscription::class);
    }

    /**
     * Scope to only active plans.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to plans available for the user's selected canon.
     */
    public function scopeAvailableTo(Builder $query, User $user): Builder
    {
        if ($user->includesDeuterocanonicalBooks()) {
            return $query;
        }

        return $query->where('slug', '!=', 'catholic-canonical');
    }

    /**
     * Determine whether the plan is available for the user's selected canon.
     */
    public function isAvailableTo(User $user): bool
    {
        return $this->slug !== 'catholic-canonical' || $user->includesDeuterocanonicalBooks();
    }

    /**
     * Get the total number of days in this plan.
     */
    public function getDaysCount(): int
    {
        return count($this->days ?? []);
    }

    /**
     * Get the valid plan day numbers in their reading order.
     *
     * @return array<int, int>
     */
    public function getOrderedDayNumbers(): array
    {
        return collect($this->days ?? [])
            ->pluck('day')
            ->filter(fn ($dayNumber): bool => is_numeric($dayNumber))
            ->map(fn ($dayNumber): int => (int) $dayNumber)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get the first valid plan day number.
     */
    public function getFirstDayNumber(): int
    {
        return $this->getOrderedDayNumbers()[0] ?? 0;
    }

    /**
     * Get the last valid plan day number.
     */
    public function getLastDayNumber(): int
    {
        $dayNumbers = $this->getOrderedDayNumbers();

        return $dayNumbers[array_key_last($dayNumbers)] ?? 0;
    }

    /**
     * Get the previous valid plan day number.
     */
    public function getPreviousDayNumber(int $dayNumber): ?int
    {
        $previousDay = null;

        foreach ($this->getOrderedDayNumbers() as $validDayNumber) {
            if ($validDayNumber === $dayNumber) {
                return $previousDay;
            }

            $previousDay = $validDayNumber;
        }

        return null;
    }

    /**
     * Get the next valid plan day number.
     */
    public function getNextDayNumber(int $dayNumber): ?int
    {
        $dayNumbers = $this->getOrderedDayNumbers();
        $count = count($dayNumbers);

        for ($index = 0; $index < $count; $index++) {
            if ($dayNumbers[$index] === $dayNumber) {
                return $dayNumbers[$index + 1] ?? null;
            }
        }

        return null;
    }

    /**
     * Resolve a requested plan day to a valid day number.
     */
    public function getValidDayNumber(?int $dayNumber = null, ?int $fallbackDayNumber = null): int
    {
        $dayNumbers = $this->getOrderedDayNumbers();

        if ($dayNumbers === []) {
            return 0;
        }

        if ($dayNumber !== null && in_array($dayNumber, $dayNumbers, true)) {
            return $dayNumber;
        }

        if ($fallbackDayNumber !== null && in_array($fallbackDayNumber, $dayNumbers, true)) {
            return $fallbackDayNumber;
        }

        if ($dayNumber !== null) {
            foreach ($dayNumbers as $validDayNumber) {
                if ($validDayNumber >= $dayNumber) {
                    return $validDayNumber;
                }
            }
        }

        return $dayNumbers[array_key_last($dayNumbers)];
    }

    /**
     * Get valid plan day numbers from the provided starting day.
     *
     * @return array<int, int>
     */
    public function getDayNumbersFrom(int $startDay): array
    {
        return collect($this->getOrderedDayNumbers())
            ->filter(fn (int $dayNumber): bool => $dayNumber >= $startDay)
            ->values()
            ->all();
    }

    /**
     * Get the concise name used when the reading-plan context is already clear.
     */
    public function getShortName(): string
    {
        return preg_replace('/ Reading Plan$/', '', $this->name) ?? $this->name;
    }

    /**
     * Get the reading for a specific day number.
     */
    public function getDayReading(int $dayNumber): ?array
    {
        $days = $this->days ?? [];

        foreach ($days as $day) {
            if ($day['day'] === $dayNumber) {
                return $day;
            }
        }

        return null;
    }
}

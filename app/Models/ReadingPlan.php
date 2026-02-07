<?php

namespace App\Models;

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
     * Get the total number of days in this plan.
     */
    public function getDaysCount(): int
    {
        return count($this->days ?? []);
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingPlanDayCompletion extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reading_log_id',
        'reading_plan_subscription_id',
        'reading_plan_day',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reading_plan_day' => 'integer',
        ];
    }

    /**
     * Get the reading log this completion belongs to.
     */
    public function readingLog(): BelongsTo
    {
        return $this->belongsTo(ReadingLog::class);
    }

    /**
     * Get the subscription this completion belongs to.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(ReadingPlanSubscription::class, 'reading_plan_subscription_id');
    }
}

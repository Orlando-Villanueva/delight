<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushReminderDelivery extends Model
{
    use HasFactory;

    public const string TYPE_DAILY_READING = 'daily_reading';

    public const string TYPE_STREAK_RISK = 'streak_risk';

    protected $fillable = [
        'user_id',
        'reminder_type',
        'reminder_date',
        'scheduled_for_at',
        'sent_at',
        'skipped_at',
        'failed_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'reminder_date' => 'date',
            'scheduled_for_at' => 'datetime',
            'sent_at' => 'datetime',
            'skipped_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

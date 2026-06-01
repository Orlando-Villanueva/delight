<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushReminderDeliveryReport extends Model
{
    public const string STATUS_SENT = 'sent';

    public const string STATUS_FAILED = 'failed';

    protected $fillable = [
        'push_reminder_delivery_id',
        'user_id',
        'reminder_type',
        'reminder_date',
        'push_subscription_id',
        'endpoint_host',
        'endpoint_hash',
        'status',
        'http_status',
        'expired',
        'failure_reason',
        'response_body',
        'reported_at',
    ];

    protected function casts(): array
    {
        return [
            'reminder_date' => 'date',
            'expired' => 'boolean',
            'reported_at' => 'datetime',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(PushReminderDelivery::class, 'push_reminder_delivery_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

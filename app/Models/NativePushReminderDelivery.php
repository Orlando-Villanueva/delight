<?php

namespace App\Models;

use App\Enums\NativePushReceiptStatus;
use Database\Factories\NativePushReminderDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NativePushReminderDelivery extends Model
{
    /** @use HasFactory<NativePushReminderDeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'native_push_registration_id',
        'reminder_type',
        'reminder_date',
        'scheduled_for_at',
        'token_hash',
        'expo_ticket_id',
        'expo_receipt_status',
        'expo_receipt_error',
        'expo_receipt_checked_at',
        'expo_retry_count',
        'expo_retry_at',
        'sent_at',
        'skipped_at',
        'failed_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'reminder_date' => 'date',
            'expo_receipt_status' => NativePushReceiptStatus::class,
            'scheduled_for_at' => 'datetime',
            'expo_receipt_checked_at' => 'datetime',
            'expo_retry_count' => 'integer',
            'expo_retry_at' => 'datetime',
            'sent_at' => 'datetime',
            'skipped_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function nativePushRegistration(): BelongsTo
    {
        return $this->belongsTo(NativePushRegistration::class);
    }
}

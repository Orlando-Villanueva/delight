<?php

namespace App\Models;

use Database\Factories\AnnouncementEmailDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementEmailDelivery extends Model
{
    /** @use HasFactory<AnnouncementEmailDeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'announcement_id',
        'user_id',
        'recipient_email',
        'attempt_count',
        'message_id',
        'provider_message_id',
        'sending_at',
        'next_attempt_at',
        'sent_at',
        'skipped_at',
        'failed_at',
        'uncertain_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'sending_at' => 'datetime',
            'next_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
            'skipped_at' => 'datetime',
            'failed_at' => 'datetime',
            'uncertain_at' => 'datetime',
        ];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

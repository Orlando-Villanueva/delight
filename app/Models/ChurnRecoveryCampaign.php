<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChurnRecoveryCampaign extends Model
{
    /** @use HasFactory<\Database\Factories\ChurnRecoveryCampaignFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'campaign_key',
        'cohort',
        'variant',
        'started_at',
        'observed_until',
        'reactivated_at',
        'completed_at',
        'last_touch_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'observed_until' => 'datetime',
            'reactivated_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_touch_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function emails(): HasMany
    {
        return $this->hasMany(ChurnRecoveryEmail::class);
    }
}

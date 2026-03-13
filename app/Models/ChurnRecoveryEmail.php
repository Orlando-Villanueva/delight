<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChurnRecoveryEmail extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'churn_recovery_campaign_id', 'email_number', 'sent_at'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ChurnRecoveryCampaign::class, 'churn_recovery_campaign_id');
    }
}

<?php

namespace App\Models;

use App\Enums\OnboardingStep;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingStepEvent extends Model
{
    /** @use HasFactory<\Database\Factories\OnboardingStepEventFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'step',
        'occurred_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'step' => OnboardingStep::class,
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAchievement extends Model
{
    /** @use HasFactory<\Database\Factories\UserAchievementFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'achievement_key',
        'context_key',
        'category',
        'display_name',
        'description',
        'icon',
        'style',
        'sort_order',
        'metadata',
        'earned_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'earned_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

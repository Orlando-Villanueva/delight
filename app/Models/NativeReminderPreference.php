<?php

namespace App\Models;

use Database\Factories\NativeReminderPreferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

class NativeReminderPreference extends Model
{
    /** @use HasFactory<NativeReminderPreferenceFactory> */
    use HasFactory;

    protected $fillable = ['personal_access_token_id', 'enabled', 'timezone'];

    protected $attributes = ['enabled' => false];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function personalAccessToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

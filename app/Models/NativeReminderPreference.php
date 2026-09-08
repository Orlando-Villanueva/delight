<?php

namespace App\Models;

use Database\Factories\NativeReminderPreferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NativeReminderPreference extends Model
{
    /** @use HasFactory<NativeReminderPreferenceFactory> */
    use HasFactory;

    protected $fillable = ['enabled', 'timezone'];

    protected $attributes = ['enabled' => false];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

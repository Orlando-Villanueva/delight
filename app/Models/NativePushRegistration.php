<?php

namespace App\Models;

use Database\Factories\NativePushRegistrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

class NativePushRegistration extends Model
{
    /** @use HasFactory<NativePushRegistrationFactory> */
    use HasFactory;

    protected $fillable = ['personal_access_token_id', 'expo_push_token', 'token_hash'];

    protected $hidden = ['expo_push_token', 'token_hash'];

    protected function casts(): array
    {
        return ['expo_push_token' => 'encrypted'];
    }

    public function personalAccessToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class);
    }
}

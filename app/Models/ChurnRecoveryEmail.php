<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChurnRecoveryEmail extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'email_number', 'sent_at'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

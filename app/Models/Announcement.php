<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model; // Added this line based on 'use HasFactory;'

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'content',
        'type',
        'starts_at',
        'ends_at',
        'sent_via_email_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'sent_via_email_at' => 'datetime',
    ];

    /**
     * Scope a query to include published announcements even if they are expired,
     * so direct URLs remain available while lists can still hide expired items.
     */
    public function scopePublished($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('starts_at')
                ->orWhere('starts_at', '<=', now());
        });
    }

    /**
     * Scope a query to only include visible announcements (published and not expired).
     */
    public function scopeVisible($query)
    {
        return $this->scopePublished($query)
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });
    }

    /**
     * Alias for visible.
     */
    public function scopeActive($query)
    {
        return $this->scopeVisible($query);
    }

    /**
     * The users that have read the announcement.
     */
    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('read_at')
            ->withTimestamps();
    }
}

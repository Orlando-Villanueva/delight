<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\CustomResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the reading logs for the user.
     */
    public function readingLogs(): HasMany
    {
        return $this->hasMany(ReadingLog::class);
    }

    /**
     * Get the book progress records for the user.
     */
    public function bookProgress(): HasMany
    {
        return $this->hasMany(BookProgress::class);
    }

    /**
     * Get reading logs ordered by date (most recent first).
     */
    public function recentReadingLogs(): HasMany
    {
        return $this->readingLogs()->recentFirst();
    }

    /**
     * Get completed books for the user.
     */
    public function completedBooks(): HasMany
    {
        return $this->bookProgress()->completed();
    }

    /**
     * Get books in progress for the user.
     */
    public function booksInProgress(): HasMany
    {
        return $this->bookProgress()->inProgress();
    }

    /**
     * Send the password reset notification.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CustomResetPasswordNotification($token));
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->email === config('mail.admin_address');
    }

    /**
     * The announcements that the user has read.
     */
    public function announcements()
    {
        return $this->belongsToMany(Announcement::class)
            ->withPivot('read_at')
            ->withTimestamps();
    }

    /**
     * Get unread announcements for the user.
     */
    public function unreadAnnouncements()
    {
        return Announcement::visible()
            ->whereDoesntHave('users', function ($query) {
                $query->where('user_id', $this->id);
            });
    }
}

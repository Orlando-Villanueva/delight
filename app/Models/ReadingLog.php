<?php

namespace App\Models;

use App\Contracts\ReadingLogInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingLog extends Model implements ReadingLogInterface
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'book_id',
        'chapter',
        'passage_text',
        'date_read',
        'notes_text',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_read' => 'date',
            'book_id' => 'integer',
            'chapter' => 'integer',
        ];
    }

    /**
     * Get the user that owns the reading log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include readings for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include readings for a specific date range.
     */
    public function scopeDateRange($query, $startDate, $endDate = null)
    {
        $start = $this->normalizeDateBoundary($startDate);
        $query->whereDate('date_read', '>=', $start);

        if ($endDate) {
            $end = $this->normalizeDateBoundary($endDate);
            $query->whereDate('date_read', '<=', $end);
        }

        return $query;
    }

    /**
     * Scope a query to only include readings for a specific book.
     */
    public function scopeForBook($query, $bookId)
    {
        return $query->where('book_id', $bookId);
    }

    /**
     * Scope a query to order by date read (most recent first).
     */
    public function scopeRecentFirst($query)
    {
        return $query->orderBy('date_read', 'desc');
    }

    /**
     * Get the date when the reading was performed.
     */
    public function getDateRead(): ?string
    {
        return $this->date_read?->toDateString();
    }

    /**
     * Get the timestamp when the reading log was created.
     */
    public function getCreatedAt(): Carbon
    {
        return $this->created_at;
    }

    private function normalizeDateBoundary($value): string
    {
        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        return Carbon::parse($value)->toDateString();
    }
}

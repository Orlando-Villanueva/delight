<?php

namespace App\Services;

use App\Models\ReadingLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DelightRewindService
{
    public function __construct(
        private BibleReferenceService $bibleReferenceService
    ) {}

    public function getRewindStats(User $user, int $year): array
    {
        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        // Base query for the year
        $logsQuery = $user->readingLogs()
            ->whereBetween('date_read', [$startDate, $endDate]);

        $totalChapters = $logsQuery->count();
        $totalBooksRead = $logsQuery->distinct('book_id')->count('book_id');

        // Most Read Book
        $mostReadBookId = $logsQuery->clone()
            ->select('book_id', DB::raw('count(*) as total'))
            ->groupBy('book_id')
            ->orderByDesc('total')
            ->value('book_id');

        $mostReadBook = $mostReadBookId
            ? $this->bibleReferenceService->getBibleBook($mostReadBookId)
            : null;

        // Most Read Testament
        $logsWithBooks = $logsQuery->get(); // We need to iterate to map book_id to testament/genre

        $testamentCounts = ['old' => 0, 'new' => 0];
        $genreCounts = [];
        $dayCounts = [
            'Sunday' => 0, 'Monday' => 0, 'Tuesday' => 0, 'Wednesday' => 0,
            'Thursday' => 0, 'Friday' => 0, 'Saturday' => 0
        ];

        foreach ($logsWithBooks as $log) {
            $book = $this->bibleReferenceService->getBibleBook($log->book_id);
            if ($book) {
                $testamentCounts[$book['testament']]++;
                $genre = $book['genre'] ?? 'Unknown';
                if (!isset($genreCounts[$genre])) {
                    $genreCounts[$genre] = 0;
                }
                $genreCounts[$genre]++;
            }

            $dayName = Carbon::parse($log->date_read)->format('l');
            $dayCounts[$dayName]++;
        }

        $mostReadTestamentKey = array_search(max($testamentCounts), $testamentCounts);
        $mostReadTestament = $this->bibleReferenceService->getTestament($mostReadTestamentKey);

        $mostReadGenre = empty($genreCounts) ? null : array_search(max($genreCounts), $genreCounts);
        $mostActiveDay = array_search(max($dayCounts), $dayCounts);

        // Bible Completion for the Year
        // Defined as chapters read this year / 1189
        $completionPercentage = round(($totalChapters / 1189) * 100, 1);

        // Streak Logic (Longest streak within the year)
        // We can't easily reuse the service which calculates *current* or *all time* streak.
        // We need to calculate the longest streak strictly within the date range.
        $longestStreak = $this->calculateLongestStreakInYear($user, $year);

        return [
            'year' => $year,
            'total_chapters' => $totalChapters,
            'total_books_read' => $totalBooksRead,
            'most_read_book' => $mostReadBook,
            'most_read_testament' => $mostReadTestament,
            'most_read_genre' => $mostReadGenre,
            'most_active_day' => $mostActiveDay,
            'completion_percentage' => $completionPercentage,
            'longest_streak' => $longestStreak,
        ];
    }

    private function calculateLongestStreakInYear(User $user, int $year): int
    {
        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        $dates = $user->readingLogs()
            ->whereBetween('date_read', [$startDate, $endDate])
            ->distinct('date_read')
            ->orderBy('date_read')
            ->pluck('date_read')
            ->map(fn($date) => Carbon::parse($date)->toDateString())
            ->toArray();

        if (empty($dates)) {
            return 0;
        }

        $maxStreak = 1;
        $currentStreak = 1;

        for ($i = 0; $i < count($dates) - 1; $i++) {
            $currentDate = Carbon::parse($dates[$i]);
            $nextDate = Carbon::parse($dates[$i + 1]);

            if ($currentDate->addDay()->toDateString() === $nextDate->toDateString()) {
                $currentStreak++;
            } else {
                $maxStreak = max($maxStreak, $currentStreak);
                $currentStreak = 1;
            }
        }

        return max($maxStreak, $currentStreak);
    }
}

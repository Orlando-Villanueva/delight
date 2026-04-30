<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class BookProgressService
{
    public function __construct(
        private BibleReferenceService $bibleReferenceService
    ) {}

    /**
     * Get testament progress data for dashboard display.
     *
     * @param  string  $testament  'Old' or 'New'
     */
    public function getTestamentProgress(User $user, string $testament = 'Old'): array
    {
        $allBooks = $this->bibleReferenceService->listBibleBooks(includeDeuterocanonical: $user->includesDeuterocanonicalBooks());

        // Filter books by testament (server-side)
        $testamentBooks = collect($allBooks)->filter(function ($book) use ($testament) {
            return ucfirst($book['testament']) === $testament;
        })->values();

        // Get user's book progress
        $booksProgress = $user->bookProgress()->get()->keyBy('book_name');

        // Calculate progress for each book
        $processedBooks = $testamentBooks->map(function ($book) use ($booksProgress) {
            $progress = $booksProgress->get($book['name'], null);

            $chaptersReadArray = $progress ? ($progress->chapters_read ?? []) : [];
            $totalChapters = is_numeric($book['chapters']) ? (int) $book['chapters'] : 0;
            $chaptersRead = is_array($chaptersReadArray)
                ? collect($chaptersReadArray)
                    ->filter(fn (int $chapter): bool => $chapter >= 1 && $chapter <= $totalChapters)
                    ->unique()
                    ->count()
                : 0;
            $percentage = $totalChapters > 0 ? round(($chaptersRead / $totalChapters) * 100, 1) : 0;

            $status = $this->determineBookStatus($chaptersRead, $totalChapters);

            return [
                'name' => $book['name'],
                'chapter_count' => $totalChapters, // Standardize the key name for the component
                'chapters_read' => $chaptersRead,
                'percentage' => $percentage,
                'status' => $status,
            ];
        });

        // Calculate testament statistics
        $stats = $this->calculateTestamentStats($processedBooks);

        return [
            'testament' => $testament,
            'processed_books' => $processedBooks,
            'testament_progress' => $stats['testament_progress'],
            'completed_books' => $stats['completed_books'],
            'in_progress_books' => $stats['in_progress_books'],
            'not_started_books' => $stats['not_started_books'],
        ];
    }

    /**
     * Determine the completion status of a book.
     */
    private function determineBookStatus(int $chaptersRead, int $totalChapters): string
    {
        if ($chaptersRead === $totalChapters && $chaptersRead > 0) {
            return 'completed';
        }

        if ($chaptersRead > 0) {
            return 'in-progress';
        }

        return 'not-started';
    }

    /**
     * Calculate testament-wide statistics.
     */
    private function calculateTestamentStats(Collection $processedBooks): array
    {
        $completedBooks = $processedBooks->where('status', 'completed')->count();
        $inProgressBooks = $processedBooks->where('status', 'in-progress')->count();
        $notStartedBooks = $processedBooks->where('status', 'not-started')->count();

        // Calculate overall testament progress
        $totalChapters = $processedBooks->sum('chapter_count');
        $readChapters = $processedBooks->sum('chapters_read');
        $testamentProgress = $totalChapters > 0 ? round(($readChapters / $totalChapters) * 100, 1) : 0;

        return [
            'testament_progress' => $testamentProgress,
            'completed_books' => $completedBooks,
            'in_progress_books' => $inProgressBooks,
            'not_started_books' => $notStartedBooks,
        ];
    }

    /**
     * Get overall Bible reading progress across both testaments.
     */
    public function getOverallProgress(User $user): array
    {
        $oldTestament = $this->getTestamentProgress($user, 'Old');
        $newTestament = $this->getTestamentProgress($user, 'New');
        $deuterocanonical = $user->includesDeuterocanonicalBooks()
            ? $this->getTestamentProgress($user, 'Deuterocanonical')
            : null;

        $totalBooks = $oldTestament['completed_books'] + $oldTestament['in_progress_books'] + $oldTestament['not_started_books'] +
                     $newTestament['completed_books'] + $newTestament['in_progress_books'] + $newTestament['not_started_books'];

        if ($deuterocanonical !== null) {
            $totalBooks += $deuterocanonical['completed_books'] + $deuterocanonical['in_progress_books'] + $deuterocanonical['not_started_books'];
        }

        $totalCompleted = $oldTestament['completed_books'] + $newTestament['completed_books'];
        $totalInProgress = $oldTestament['in_progress_books'] + $newTestament['in_progress_books'];

        if ($deuterocanonical !== null) {
            $totalCompleted += $deuterocanonical['completed_books'];
            $totalInProgress += $deuterocanonical['in_progress_books'];
        }

        $overallPercentage = $totalBooks > 0 ? round(($totalCompleted / $totalBooks) * 100, 1) : 0;

        return [
            'total_books' => $totalBooks,
            'completed_books' => $totalCompleted,
            'in_progress_books' => $totalInProgress,
            'not_started_books' => $totalBooks - $totalCompleted - $totalInProgress,
            'overall_percentage' => $overallPercentage,
            'old_testament' => $oldTestament,
            'new_testament' => $newTestament,
            'deuterocanonical' => $deuterocanonical,
        ];
    }
}

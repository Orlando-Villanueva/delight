<?php

namespace App\Services;

use App\Enums\BibleTestament;
use App\Models\User;
use Illuminate\Support\Collection;

class BookProgressService
{
    public function __construct(private BibleReferenceService $bibleReferenceService) {}

    /**
     * Determine first-coverage and lifetime completion progress for the user's included Bible.
     *
     * @return array<string, mixed>
     */
    public function getOverallProgress(User $user): array
    {
        $includeDeuterocanonical = $user->includesDeuterocanonicalBooks();
        $oldTestamentBooks = collect($this->bibleReferenceService->listBibleBooks(
            testament: BibleTestament::Old->value,
            includeDeuterocanonical: $includeDeuterocanonical
        ));
        $newTestamentBooks = collect($this->bibleReferenceService->listBibleBooks(
            testament: BibleTestament::New->value,
            includeDeuterocanonical: $includeDeuterocanonical
        ));
        $deuterocanonicalBooks = $includeDeuterocanonical
            ? collect($this->bibleReferenceService->listBibleBooks(
                testament: BibleTestament::Deuterocanonical->value,
                includeDeuterocanonical: true
            ))
            : collect();
        $books = $oldTestamentBooks->concat($newTestamentBooks)->concat($deuterocanonicalBooks)->values();
        $occurrences = $this->chapterOccurrences($user, $books);

        $testaments = [
            'old_testament' => $this->buildTestamentProgress(BibleTestament::Old->label(), $oldTestamentBooks, $occurrences),
            'new_testament' => $this->buildTestamentProgress(BibleTestament::New->label(), $newTestamentBooks, $occurrences),
            'deuterocanonical' => $includeDeuterocanonical
                ? $this->buildTestamentProgress(BibleTestament::Deuterocanonical->label(), $deuterocanonicalBooks, $occurrences)
                : null,
        ];

        $summary = $this->summarizeBooks($books, $occurrences);
        $totalBooks = array_sum(array_filter(array_map(
            fn (?array $testament): ?int => $testament === null ? null : $testament['total_books'],
            $testaments
        )));
        $completedBooks = array_sum(array_filter(array_map(
            fn (?array $testament): ?int => $testament === null ? null : $testament['completed_books'],
            $testaments
        )));
        $inProgressBooks = array_sum(array_filter(array_map(
            fn (?array $testament): ?int => $testament === null ? null : $testament['in_progress_books'],
            $testaments
        )));
        $processedBooks = collect($testaments)
            ->filter(fn (?array $testament): bool => $testament !== null)
            ->flatMap(fn (array $testament): Collection => $testament['processed_books']);
        $bookCompletions = (int) $processedBooks->sum('completed_count');

        return [
            'total_books' => $totalBooks,
            'completed_books' => $completedBooks,
            'in_progress_books' => $inProgressBooks,
            'not_started_books' => max(0, $totalBooks - $completedBooks - $inProgressBooks),
            'overall_percentage' => $totalBooks > 0 ? round(($completedBooks / $totalBooks) * 100, 1) : 0.0,
            'first_coverage_percent' => $summary['first_coverage_percent'],
            'first_coverage_chapters' => $summary['first_coverage_chapters'],
            'bible_completions' => $summary['completions'],
            'book_completions' => $bookCompletions,
            'next_completion_chapters' => $summary['next_completion_chapters'],
            'next_completion_target' => $summary['total_chapters'],
            'next_completion_progress_percent' => $summary['next_completion_progress_percent'],
            ...$testaments,
        ];
    }

    /**
     * Build progress data for one testament from already aggregated log counts.
     *
     * @param  Collection<int, array<string, mixed>>  $books
     * @param  Collection<string, int>  $occurrences
     * @return array<string, mixed>
     */
    private function buildTestamentProgress(string $testamentLabel, Collection $books, Collection $occurrences): array
    {
        $processedBooks = $books->map(fn (array $book): array => $this->bookProgress($book, $occurrences));
        $summary = $this->summarizeBooks($books, $occurrences);

        return [
            'testament' => $testamentLabel,
            'processed_books' => $processedBooks,
            // Kept as first coverage for existing consumers; the dashboard labels its new bar explicitly.
            'testament_progress' => round($summary['first_coverage_percent'], 1),
            'first_coverage_percent' => $summary['first_coverage_percent'],
            'first_coverage_chapters' => $summary['first_coverage_chapters'],
            'testament_completions' => $summary['completions'],
            'next_completion_chapters' => $summary['next_completion_chapters'],
            'next_completion_target' => $summary['total_chapters'],
            'next_completion_progress_percent' => $summary['next_completion_progress_percent'],
            'completed_books' => $summary['completed_books'],
            'in_progress_books' => $summary['in_progress_books'],
            'not_started_books' => $summary['not_started_books'],
            'total_books' => $summary['total_books'],
        ];
    }

    /**
     * Get a book's unique chapter coverage and its completed whole-book pass count.
     *
     * @param  array<string, mixed>  $book
     * @param  Collection<string, int>  $occurrences
     * @return array<string, mixed>
     */
    private function bookProgress(array $book, Collection $occurrences): array
    {
        $totalChapters = (int) $book['chapters'];
        $chapterOccurrences = collect(range(1, $totalChapters))
            ->map(fn (int $chapter): int => (int) $occurrences->get($this->chapterKey((int) $book['id'], $chapter), 0));
        $chaptersRead = $chapterOccurrences->filter(fn (int $count): bool => $count > 0)->count();
        $completedCount = (int) ($chapterOccurrences->min() ?? 0);
        $nextCompletionChapters = $chapterOccurrences->filter(fn (int $count): bool => $count > $completedCount)->count();
        $missingNextCompletionChapters = collect(range(1, $totalChapters))
            ->filter(fn (int $chapter): bool => (int) $occurrences->get($this->chapterKey((int) $book['id'], $chapter), 0) <= $completedCount)
            ->values()
            ->all();
        $percentage = $totalChapters > 0 ? round(($chaptersRead / $totalChapters) * 100, 1) : 0.0;

        return [
            'book_id' => (int) $book['id'],
            'name' => $book['name'],
            'chapter_count' => $totalChapters,
            'chapters_read' => $chaptersRead,
            'percentage' => $percentage,
            'completed_count' => $completedCount,
            'next_completion_chapters' => $nextCompletionChapters,
            'next_completion_missing_chapters' => $missingNextCompletionChapters,
            'next_completion_progress_percent' => $totalChapters > 0 ? round(($nextCompletionChapters / $totalChapters) * 100, 1) : 0.0,
            'status' => $this->determineBookStatus($chaptersRead, $totalChapters),
        ];
    }

    /**
     * Summarize coverage and whole-canon completion progress for the supplied book collection.
     *
     * @param  Collection<int, array<string, mixed>>  $books
     * @param  Collection<string, int>  $occurrences
     * @return array<string, int|float>
     */
    private function summarizeBooks(Collection $books, Collection $occurrences): array
    {
        $processedBooks = $books->map(fn (array $book): array => $this->bookProgress($book, $occurrences));
        $chapterOccurrences = $books->flatMap(function (array $book) use ($occurrences): Collection {
            return collect(range(1, (int) $book['chapters']))
                ->map(fn (int $chapter): int => (int) $occurrences->get($this->chapterKey((int) $book['id'], $chapter), 0));
        });
        $totalChapters = $chapterOccurrences->count();
        $firstCoverageChapters = $processedBooks->sum('chapters_read');
        $completions = (int) ($chapterOccurrences->min() ?? 0);
        $nextCompletionChapters = $chapterOccurrences->filter(fn (int $count): bool => $count > $completions)->count();
        $completedBooks = $processedBooks->where('status', 'completed')->count();
        $inProgressBooks = $processedBooks->where('status', 'in-progress')->count();

        return [
            'total_books' => $books->count(),
            'completed_books' => $completedBooks,
            'in_progress_books' => $inProgressBooks,
            'not_started_books' => $books->count() - $completedBooks - $inProgressBooks,
            'total_chapters' => $totalChapters,
            'first_coverage_chapters' => $firstCoverageChapters,
            'first_coverage_percent' => $totalChapters > 0 ? round(($firstCoverageChapters / $totalChapters) * 100, 2) : 0.0,
            'completions' => $completions,
            'next_completion_chapters' => $nextCompletionChapters,
            'next_completion_progress_percent' => $totalChapters > 0 ? round(($nextCompletionChapters / $totalChapters) * 100, 1) : 0.0,
        ];
    }

    /**
     * Get dated reading-log occurrence counts for the requested Bible books.
     *
     * @param  Collection<int, array<string, mixed>>  $books
     * @return Collection<string, int>
     */
    private function chapterOccurrences(User $user, Collection $books): Collection
    {
        if ($books->isEmpty()) {
            return collect();
        }

        return $user->readingLogs()
            ->select('book_id', 'chapter')
            ->selectRaw('COUNT(*) as occurrences')
            ->whereIn('book_id', $books->pluck('id')->all())
            ->groupBy('book_id', 'chapter')
            ->get()
            ->mapWithKeys(fn ($reading): array => [
                $this->chapterKey((int) $reading->book_id, (int) $reading->chapter) => (int) $reading->occurrences,
            ]);
    }

    private function chapterKey(int $bookId, int $chapter): string
    {
        return "{$bookId}:{$chapter}";
    }

    /**
     * Determine the first-coverage status of a book.
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
}

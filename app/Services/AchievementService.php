<?php

namespace App\Services;

use App\Models\ReadingLog;
use App\Models\User;
use App\Models\UserAchievement;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

class AchievementService
{
    private const int WEEKLY_TARGET_DAYS = 4;

    private const array STREAK_THRESHOLDS = [7, 30, 100, 365];

    private const array BIBLE_PROGRESS_THRESHOLDS = [25, 50, 75, 100];

    public function __construct(
        private BibleReferenceService $bibleReferenceService
    ) {}

    /**
     * @return array{awarded: int, skipped_duplicates: int, would_award: int, candidates: Collection<int, array<string, mixed>>, awarded_achievements: Collection<int, UserAchievement>}
     */
    public function evaluateAndAward(User $user, bool $dryRun = false): array
    {
        $candidates = $this->buildAwardCandidates($user);
        $awardedAchievements = collect();
        $awarded = 0;
        $skippedDuplicates = 0;
        $wouldAward = 0;

        foreach ($candidates as $candidate) {
            if ($dryRun) {
                $exists = $user->achievements()
                    ->where('achievement_key', $candidate['achievement_key'])
                    ->where('context_key', $candidate['context_key'])
                    ->exists();

                if ($exists) {
                    $skippedDuplicates++;

                    continue;
                }

                $wouldAward++;

                continue;
            }

            $exists = $user->achievements()
                ->where('achievement_key', $candidate['achievement_key'])
                ->where('context_key', $candidate['context_key'])
                ->exists();

            if ($exists) {
                $skippedDuplicates++;

                continue;
            }

            try {
                $awardedAchievements->push($user->achievements()->create($candidate));
                $awarded++;
            } catch (UniqueConstraintViolationException $exception) {
                $exists = $user->achievements()
                    ->where('achievement_key', $candidate['achievement_key'])
                    ->where('context_key', $candidate['context_key'])
                    ->exists();

                if (! $exists) {
                    throw $exception;
                }

                $skippedDuplicates++;
            }
        }

        return [
            'awarded' => $awarded,
            'skipped_duplicates' => $skippedDuplicates,
            'would_award' => $wouldAward,
            'candidates' => $candidates,
            'awarded_achievements' => $awardedAchievements,
        ];
    }

    /**
     * @param  Collection<int, UserAchievement>  $awardedAchievements
     * @return array{earned: array<int, array<string, mixed>>, progress: array<int, array<string, mixed>>, record: ?array<string, mixed>, reading: array<string, string>}
     */
    public function getCelebrationPayload(User $user, Collection $awardedAchievements, ReadingLog $log, bool $isFirstReadingOfDay): array
    {
        $earned = $awardedAchievements
            ->sortBy([
                ['sort_order', 'asc'],
                ['earned_at', 'asc'],
            ])
            ->map(fn (UserAchievement $achievement): array => [
                'id' => $achievement->id,
                'display_name' => $achievement->display_name,
                'description' => $achievement->description,
                'icon' => $achievement->icon,
                'style' => $achievement->style,
                'category' => $achievement->category,
                'earned_at' => $achievement->earned_at?->format('M j, Y'),
            ])
            ->values()
            ->all();

        $progress = $this->getLockedAchievements($user, $user->achievements()->get())
            ->filter(fn (array $achievement): bool => (int) $achievement['current'] > 0)
            ->sortByDesc(fn (array $achievement): int|float => $achievement['progress_percent'])
            ->take(3)
            ->map(fn (array $achievement): array => [
                'display_name' => $achievement['display_name'],
                'description' => $achievement['description'],
                'icon' => $achievement['icon'],
                'style' => $achievement['style'],
                'current' => $achievement['current'],
                'target' => $achievement['target'],
                'progress_percent' => $achievement['progress_percent'],
            ])
            ->values()
            ->all();

        return [
            'earned' => $earned,
            'progress' => $progress,
            'record' => $isFirstReadingOfDay ? $this->recordCelebrationPayload($user) : null,
            'reading' => [
                'passage' => $log->passage_text,
                'date' => $log->date_read->format('M j, Y'),
            ],
        ];
    }

    /**
     * @return array{earned: Collection<string, Collection<int, UserAchievement>>, locked: Collection<int, array<string, mixed>>, next_goals: array{books: Collection<int, array<string, mixed>>, progress: Collection<int, array<string, mixed>>}, recent: Collection<int, UserAchievement>}
     */
    public function getShelfData(User $user): array
    {
        $earned = $user->achievements()
            ->orderByDesc('earned_at')
            ->orderBy('sort_order')
            ->get();

        return [
            'earned' => $earned->groupBy('category'),
            'locked' => $this->getLockedAchievements($user, $earned),
            'next_goals' => $this->nextGoals($user, $earned),
            'recent' => $earned->take(3),
        ];
    }

    /**
     * @param  Collection<int, UserAchievement>  $earned
     * @return array{books: Collection<int, array<string, mixed>>, progress: Collection<int, array<string, mixed>>}
     */
    private function nextGoals(User $user, Collection $earned): array
    {
        return [
            'books' => $this->almostFinishedBooks($user),
            'progress' => $this->getLockedAchievements($user, $earned)
                ->filter(fn (array $achievement): bool => (int) $achievement['current'] > 0)
                ->sortByDesc(fn (array $achievement): int|float => $achievement['progress_percent'])
                ->take(4)
                ->values(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function almostFinishedBooks(User $user): Collection
    {
        $includeDeuterocanonical = $user->includesDeuterocanonicalBooks();

        return $user->bookProgress()
            ->inProgress()
            ->get()
            ->filter(fn ($progress): bool => $progress->book_id <= 66 || $includeDeuterocanonical)
            ->map(function ($progress): array {
                $chaptersRead = collect($progress->chapters_read ?? [])
                    ->map(fn ($chapter): int => (int) $chapter)
                    ->filter(fn (int $chapter): bool => $chapter >= 1 && $chapter <= $progress->total_chapters)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();
                $missingChapters = array_values(array_diff(range(1, $progress->total_chapters), $chaptersRead));
                $chaptersReadCount = count($chaptersRead);
                $chaptersRemaining = count($missingChapters);
                $progressPercent = $progress->total_chapters > 0
                    ? round(($chaptersReadCount / $progress->total_chapters) * 100)
                    : 0;

                return [
                    'book_id' => $progress->book_id,
                    'book_name' => $progress->book_name,
                    'chapters_read' => $chaptersReadCount,
                    'total_chapters' => $progress->total_chapters,
                    'chapters_remaining' => $chaptersRemaining,
                    'missing_chapters' => $chaptersRemaining <= 10 ? $missingChapters : [],
                    'progress_percent' => $progressPercent,
                    'icon' => 'book-open',
                    'style' => 'success',
                ];
            })
            ->filter(fn (array $goal): bool => $goal['chapters_remaining'] <= 5 || $goal['progress_percent'] >= 75)
            ->sortBy(fn (array $goal): array => [$goal['chapters_remaining'], -$goal['progress_percent']])
            ->take(3)
            ->values();
    }

    /**
     * @return array{latest: ?UserAchievement, milestone: ?array<string, mixed>}
     */
    public function getDashboardMilestone(User $user): array
    {
        $latest = $user->achievements()
            ->where('achievement_key', '!=', 'personal_best_streak')
            ->latest('earned_at')
            ->orderByDesc('sort_order')
            ->first();

        $earned = $user->achievements()->get();

        return [
            'latest' => $latest,
            'milestone' => $this->dashboardMilestone($user, $earned),
        ];
    }

    /**
     * @param  Collection<int, UserAchievement>  $earned
     * @return array<string, mixed>|null
     */
    private function dashboardMilestone(User $user, Collection $earned): ?array
    {
        $readingDates = $this->readingDates($user);
        $readingDays = $readingDates->count();

        if ($readingDays === 0) {
            return $this->dashboardPayload(
                key: 'first_reading',
                contextKey: 'first-reading',
                displayName: 'First reading',
                description: 'Log your first Bible reading.',
                icon: 'sparkles',
                style: 'success',
                current: 0,
                target: 1,
                priority: 0,
                sortOrder: $this->definitions()['first_reading']['sort_order'] ?? 0
            );
        }

        $earnedContexts = $this->earnedContextLookup($earned);
        $candidates = collect();
        $currentStreak = $this->currentStreak($readingDates);
        $longestStreak = $this->longestStreak($readingDates);
        $bibleProgress = $this->bibleProgress($user);

        $streakMilestone = $this->nextStreakDashboardMilestone($currentStreak, $earnedContexts);
        if ($streakMilestone !== null) {
            $candidates->push($streakMilestone);
        }

        $weeklyRhythmMilestone = $this->weeklyRhythmDashboardMilestone($readingDates);
        if ($weeklyRhythmMilestone !== null) {
            $candidates->push($weeklyRhythmMilestone);
        }

        if (! $earnedContexts->has('first_month|reading-days:30')) {
            $candidates->push($this->dashboardPayload(
                key: 'first_month',
                contextKey: 'reading-days:30',
                displayName: $this->definitions()['first_month']['display_name'],
                description: $this->definitions()['first_month']['description'],
                icon: $this->definitions()['first_month']['icon'],
                style: $this->definitions()['first_month']['style'],
                current: $readingDays,
                target: 30,
                priority: 30,
                sortOrder: $this->definitions()['first_month']['sort_order']
            ));
        }

        $bibleProgressMilestone = $this->nextBibleProgressDashboardMilestone($bibleProgress, $earnedContexts);
        if ($bibleProgressMilestone !== null) {
            $candidates->push($bibleProgressMilestone);
        }

        $this->almostFinishedBooks($user)
            ->each(fn (array $book): mixed => $candidates->push($this->dashboardPayload(
                key: 'book_completed',
                contextKey: 'book:'.$book['book_id'],
                displayName: 'Finish '.$book['book_name'],
                description: $book['chapters_remaining'].' '.str('chapter')->plural($book['chapters_remaining']).' left to complete '.$book['book_name'].'.',
                icon: $book['icon'],
                style: $book['style'],
                current: $book['chapters_read'],
                target: $book['total_chapters'],
                priority: 30,
                sortOrder: 100 + (int) $book['book_id']
            )));

        $this->testamentProgressGoals($user)
            ->each(fn (array $testament): mixed => $candidates->push($this->dashboardPayload(
                key: 'testament_completed',
                contextKey: 'testament:'.$testament['testament'],
                displayName: 'Complete the '.$testament['label'],
                description: $testament['books_remaining'].' '.str('book')->plural($testament['books_remaining']).' left in the '.$testament['label'].'.',
                icon: 'library',
                style: 'warning',
                current: $testament['books_completed'],
                target: $testament['total_books'],
                priority: 30,
                sortOrder: 200
            )));

        if ($candidates->isEmpty()) {
            return $this->getLockedAchievements($user, $earned)
                ->first();
        }

        return $candidates
            ->sort(function (array $a, array $b): int {
                return [
                    $a['priority'],
                    -$a['progress_percent'],
                    $a['remaining'],
                    $a['sort_order'],
                    $a['display_name'],
                ] <=> [
                    $b['priority'],
                    -$b['progress_percent'],
                    $b['remaining'],
                    $b['sort_order'],
                    $b['display_name'],
                ];
            })
            ->first();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildAwardCandidates(User $user): Collection
    {
        $definitions = $this->definitions();
        $readingDates = $this->readingDates($user);
        $distinctReadingDays = $readingDates->count();
        $longestStreak = $this->longestStreak($readingDates);
        $bibleProgress = $this->bibleProgress($user);
        $candidates = collect();

        if ($distinctReadingDays >= 1) {
            $candidates->push($this->candidate('first_reading', 'first-reading', $this->firstReadingMetadata($user)));
        }

        if ($distinctReadingDays >= (int) $definitions['first_month']['threshold']) {
            $candidates->push($this->candidate('first_month', 'reading-days:'.$definitions['first_month']['threshold'], [
                'reading_days' => $distinctReadingDays,
            ]));
        }

        foreach (self::STREAK_THRESHOLDS as $threshold) {
            $key = "reading_streak_{$threshold}";
            if ($longestStreak >= $threshold) {
                $candidates->push($this->candidate($key, "streak:{$threshold}", [
                    'streak_days' => $threshold,
                    'longest_streak' => $longestStreak,
                ]));
            }
        }

        foreach (self::BIBLE_PROGRESS_THRESHOLDS as $threshold) {
            $key = "bible_progress_{$threshold}";
            if ($bibleProgress['percentage'] >= $threshold) {
                $candidates->push($this->candidate($key, "progress:{$threshold}", [
                    'progress_percent' => $bibleProgress['percentage'],
                    'chapters_read' => $bibleProgress['chapters_read'],
                    'total_chapters' => $bibleProgress['total_chapters'],
                ]));
            }
        }

        $this->bookCompletionCandidates($user)->each(fn (array $candidate) => $candidates->push($candidate));
        $this->testamentCompletionCandidates($user)->each(fn (array $candidate) => $candidates->push($candidate));

        return $candidates->sortBy([
            ['sort_order', 'asc'],
            ['context_key', 'asc'],
        ])->values();
    }

    private function candidate(string $key, string $contextKey, array $metadata = [], array $overrides = []): array
    {
        $definition = array_merge($this->definitions()[$key], $overrides);

        return [
            'achievement_key' => $key,
            'context_key' => $contextKey,
            'category' => $definition['category'],
            'display_name' => $definition['display_name'],
            'description' => $definition['description'],
            'icon' => $definition['icon'] ?? 'trophy',
            'style' => $definition['style'] ?? 'primary',
            'sort_order' => $definition['sort_order'] ?? 0,
            'metadata' => $metadata,
            'earned_at' => now(),
        ];
    }

    /**
     * @return array{eyebrow: string, title: string, description: string, icon: string, style: string, current_streak: int, previous_best: int}|null
     */
    private function recordCelebrationPayload(User $user): ?array
    {
        $readingDates = $this->readingDates($user);
        $currentStreak = $this->currentStreak($readingDates);
        $previousBest = $this->previousBestBeforeCurrentRun($readingDates);

        if ($previousBest <= 0 || $currentStreak !== $previousBest + 1) {
            return null;
        }

        return [
            'eyebrow' => 'Personal best',
            'title' => "Longest streak: {$currentStreak} days",
            'description' => "You beat your previous best of {$previousBest} days.",
            'icon' => 'trophy',
            'style' => 'accent',
            'current_streak' => $currentStreak,
            'previous_best' => $previousBest,
        ];
    }

    /**
     * @return array{book_id?: int, book_name?: string, chapter?: int, passage?: string, date_read?: string}
     */
    private function firstReadingMetadata(User $user): array
    {
        $firstReading = $user->readingLogs()
            ->orderBy('date_read')
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        if (! $firstReading) {
            return [];
        }

        $includeDeuterocanonical = $user->includesDeuterocanonicalBooks() || $firstReading->book_id > 66;
        $bookName = $this->bibleReferenceService->getLocalizedBookName(
            $firstReading->book_id,
            includeDeuterocanonical: $includeDeuterocanonical
        );

        return [
            'book_id' => $firstReading->book_id,
            'book_name' => $bookName,
            'chapter' => $firstReading->chapter,
            'passage' => $firstReading->passage_text ?: "{$bookName} {$firstReading->chapter}",
            'date_read' => $firstReading->date_read->toDateString(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function bookCompletionCandidates(User $user): Collection
    {
        $includeDeuterocanonical = $user->includesDeuterocanonicalBooks();

        return $user->bookProgress()
            ->where('is_completed', true)
            ->get()
            ->filter(fn ($progress): bool => $progress->book_id <= 66 || $includeDeuterocanonical)
            ->map(function ($progress) use ($includeDeuterocanonical) {
                $bookName = $this->bibleReferenceService->getLocalizedBookName(
                    $progress->book_id,
                    includeDeuterocanonical: $includeDeuterocanonical || $progress->book_id > 66
                );

                return $this->candidate('book_completed', 'book:'.$progress->book_id, [
                    'book_id' => $progress->book_id,
                    'book_name' => $bookName,
                ], [
                    'display_name' => "Completed {$bookName}",
                    'description' => "You completed {$bookName}.",
                    'sort_order' => 100 + $progress->book_id,
                ]);
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function testamentCompletionCandidates(User $user): Collection
    {
        return collect([
            'old' => 'Old Testament',
            'new' => 'New Testament',
            'deuterocanonical' => 'Deuterocanonical books',
        ])
            ->filter(fn (string $label, string $testament): bool => $testament !== 'deuterocanonical' || $user->includesDeuterocanonicalBooks())
            ->filter(fn (string $label, string $testament): bool => $this->isTestamentCompleted($user, $testament))
            ->map(function (string $label, string $testament) {
                return $this->candidate('testament_completed', "testament:{$testament}", [
                    'testament' => $testament,
                    'label' => $label,
                ], [
                    'display_name' => "Completed the {$label}",
                    'description' => "You completed every book in the {$label}.",
                ]);
            })
            ->values();
    }

    private function isTestamentCompleted(User $user, string $testament): bool
    {
        $includeDeuterocanonical = $user->includesDeuterocanonicalBooks();
        $books = collect($this->bibleReferenceService->listBibleBooks($testament, includeDeuterocanonical: $includeDeuterocanonical));

        if ($books->isEmpty()) {
            return false;
        }

        $progress = $user->bookProgress()->get()->keyBy('book_id');

        return $books->every(function (array $book) use ($progress): bool {
            $bookProgress = $progress->get($book['id']);

            if (! $bookProgress) {
                return false;
            }

            $chaptersRead = collect($bookProgress->chapters_read ?? [])
                ->filter(fn (int $chapter): bool => $chapter >= 1 && $chapter <= (int) $book['chapters'])
                ->unique()
                ->count();

            return $chaptersRead >= (int) $book['chapters'];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function testamentProgressGoals(User $user): Collection
    {
        $includeDeuterocanonical = $user->includesDeuterocanonicalBooks();
        $progress = $user->bookProgress()->get()->keyBy('book_id');

        return collect([
            'old' => 'Old Testament',
            'new' => 'New Testament',
            'deuterocanonical' => 'Deuterocanonical books',
        ])
            ->filter(fn (string $label, string $testament): bool => $testament !== 'deuterocanonical' || $includeDeuterocanonical)
            ->map(function (string $label, string $testament) use ($includeDeuterocanonical, $progress): ?array {
                $books = collect($this->bibleReferenceService->listBibleBooks($testament, includeDeuterocanonical: $includeDeuterocanonical));

                if ($books->isEmpty()) {
                    return null;
                }

                $completed = $books->filter(function (array $book) use ($progress): bool {
                    $bookProgress = $progress->get($book['id']);

                    if (! $bookProgress) {
                        return false;
                    }

                    return collect($bookProgress->chapters_read ?? [])
                        ->filter(fn (int $chapter): bool => $chapter >= 1 && $chapter <= (int) $book['chapters'])
                        ->unique()
                        ->count() >= (int) $book['chapters'];
                })->count();

                $total = $books->count();
                $remaining = $total - $completed;
                $progressPercent = $total > 0 ? round(($completed / $total) * 100) : 0;

                return [
                    'testament' => $testament,
                    'label' => $label,
                    'books_completed' => $completed,
                    'total_books' => $total,
                    'books_remaining' => $remaining,
                    'progress_percent' => $progressPercent,
                ];
            })
            ->filter()
            ->filter(fn (array $goal): bool => $goal['books_remaining'] > 0)
            ->filter(fn (array $goal): bool => $goal['books_remaining'] <= 5 || $goal['progress_percent'] >= 75)
            ->sortBy(fn (array $goal): array => [$goal['books_remaining'], -$goal['progress_percent']])
            ->values();
    }

    /**
     * @return array{percentage: float, chapters_read: int, total_chapters: int}
     */
    private function bibleProgress(User $user): array
    {
        $includeDeuterocanonical = $user->includesDeuterocanonicalBooks();
        $books = collect($this->bibleReferenceService->listBibleBooks(includeDeuterocanonical: $includeDeuterocanonical))
            ->keyBy('id');
        $progress = $user->bookProgress()
            ->whereIn('book_id', $books->keys()->all())
            ->get();

        $chaptersRead = $progress->sum(function ($bookProgress) use ($books): int {
            $book = $books->get($bookProgress->book_id);

            if (! $book) {
                return 0;
            }

            return collect($bookProgress->chapters_read ?? [])
                ->filter(fn (int $chapter): bool => $chapter >= 1 && $chapter <= (int) $book['chapters'])
                ->unique()
                ->count();
        });

        $totalChapters = $books->sum(fn (array $book): int => (int) $book['chapters']);

        return [
            'percentage' => $totalChapters > 0 ? round(($chaptersRead / $totalChapters) * 100, 2) : 0.0,
            'chapters_read' => $chaptersRead,
            'total_chapters' => $totalChapters,
        ];
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function readingDates(User $user): Collection
    {
        return $user->readingLogs()
            ->select('date_read')
            ->distinct()
            ->orderBy('date_read')
            ->pluck('date_read')
            ->map(fn ($date) => Carbon::parse($date)->startOfDay())
            ->unique(fn (Carbon $date): string => $date->toDateString())
            ->values();
    }

    private function longestStreak(Collection $readingDates): int
    {
        if ($readingDates->isEmpty()) {
            return 0;
        }

        $longest = 1;
        $current = 1;
        $previous = $readingDates->first();

        foreach ($readingDates->skip(1) as $date) {
            if ((int) $previous->diffInDays($date) === 1) {
                $current++;
                $longest = max($longest, $current);
            } else {
                $current = 1;
            }

            $previous = $date;
        }

        return $longest;
    }

    private function currentStreak(Collection $readingDates): int
    {
        if ($readingDates->isEmpty()) {
            return 0;
        }

        $lookup = $readingDates->map(fn (Carbon $date): string => $date->toDateString())->flip();
        $checkDate = today();
        $yesterday = today()->subDay();

        if (! $lookup->has($checkDate->toDateString()) && $lookup->has($yesterday->toDateString())) {
            $checkDate = $yesterday;
        }

        $streak = 0;

        while ($lookup->has($checkDate->toDateString())) {
            $streak++;
            $checkDate->subDay();
        }

        return $streak;
    }

    private function previousBestBeforeCurrentRun(Collection $readingDates): int
    {
        $currentStreak = $this->currentStreak($readingDates);

        if ($currentStreak === 0) {
            return $this->longestStreak($readingDates);
        }

        return $this->longestStreak($readingDates->slice(0, max(0, $readingDates->count() - $currentStreak))->values());
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function getLockedAchievements(User $user, Collection $earned): Collection
    {
        $earnedContexts = $earned
            ->map(fn (UserAchievement $achievement): string => $achievement->achievement_key.'|'.$achievement->context_key)
            ->flip();
        $definitions = $this->definitions();
        $readingDays = $this->readingDates($user)->count();
        $longestStreak = $this->longestStreak($this->readingDates($user));
        $bibleProgress = $this->bibleProgress($user);

        $locked = collect([
            $this->lockedPayload('first_reading', 'first-reading', min($readingDays, 1), 1),
            $this->lockedPayload('first_month', 'reading-days:30', $readingDays, 30),
        ]);

        foreach (self::STREAK_THRESHOLDS as $threshold) {
            $locked->push($this->lockedPayload("reading_streak_{$threshold}", "streak:{$threshold}", $longestStreak, $threshold));
        }

        foreach (self::BIBLE_PROGRESS_THRESHOLDS as $threshold) {
            $locked->push($this->lockedPayload("bible_progress_{$threshold}", "progress:{$threshold}", (int) floor($bibleProgress['percentage']), $threshold));
        }

        return $locked
            ->reject(fn (array $achievement): bool => $earnedContexts->has($achievement['achievement_key'].'|'.$achievement['context_key']))
            ->sortBy(fn (array $achievement): int => $definitions[$achievement['achievement_key']]['sort_order'] ?? 0)
            ->values();
    }

    private function lockedPayload(string $key, string $contextKey, int $current, int $target): array
    {
        $definition = $this->definitions()[$key];

        return [
            'achievement_key' => $key,
            'context_key' => $contextKey,
            'category' => $definition['category'],
            'display_name' => $definition['display_name'],
            'description' => $definition['description'],
            'icon' => $definition['icon'] ?? 'trophy',
            'style' => $definition['style'] ?? 'primary',
            'current' => min($current, $target),
            'target' => $target,
            'progress_percent' => $target > 0 ? min(100, round(($current / $target) * 100)) : 0,
        ];
    }

    /**
     * @param  Collection<int, UserAchievement>  $earned
     * @return Collection<string, int>
     */
    private function earnedContextLookup(Collection $earned): Collection
    {
        return $earned
            ->map(fn (UserAchievement $achievement): string => $achievement->achievement_key.'|'.$achievement->context_key)
            ->flip();
    }

    private function nextStreakDashboardMilestone(int $currentStreak, Collection $earnedContexts): ?array
    {
        if ($currentStreak <= 0) {
            return null;
        }

        foreach (self::STREAK_THRESHOLDS as $threshold) {
            $key = "reading_streak_{$threshold}";
            $contextKey = "streak:{$threshold}";

            if ($earnedContexts->has($key.'|'.$contextKey)) {
                continue;
            }

            $definition = $this->definitions()[$key];

            return $this->dashboardPayload(
                key: $key,
                contextKey: $contextKey,
                displayName: $definition['display_name'],
                description: $definition['description'],
                icon: $definition['icon'],
                style: $definition['style'],
                current: $currentStreak,
                target: $threshold,
                priority: 10,
                sortOrder: $definition['sort_order']
            );
        }

        return null;
    }

    private function weeklyRhythmDashboardMilestone(Collection $readingDates): ?array
    {
        $weekStart = today()->startOfWeek(Carbon::SUNDAY);
        $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();
        $current = $readingDates
            ->filter(fn (Carbon $date): bool => $date->betweenIncluded($weekStart, $weekEnd))
            ->count();

        if ($current <= 0 || $current >= self::WEEKLY_TARGET_DAYS) {
            return null;
        }

        return $this->dashboardPayload(
            key: 'weekly_rhythm',
            contextKey: 'weekly-rhythm:'.self::WEEKLY_TARGET_DAYS,
            displayName: '4 days this week',
            description: 'Build a steady weekly rhythm without chasing another streak.',
            icon: 'target',
            style: 'primary',
            current: $current,
            target: self::WEEKLY_TARGET_DAYS,
            priority: 20,
            sortOrder: 390
        );
    }

    private function nextBibleProgressDashboardMilestone(array $bibleProgress, Collection $earnedContexts): ?array
    {
        foreach (self::BIBLE_PROGRESS_THRESHOLDS as $threshold) {
            $key = "bible_progress_{$threshold}";
            $contextKey = "progress:{$threshold}";

            if ($earnedContexts->has($key.'|'.$contextKey)) {
                continue;
            }

            $definition = $this->definitions()[$key];

            return $this->dashboardPayload(
                key: $key,
                contextKey: $contextKey,
                displayName: $definition['display_name'],
                description: $definition['description'],
                icon: $definition['icon'],
                style: $definition['style'],
                current: (int) floor($bibleProgress['percentage']),
                target: $threshold,
                priority: 30,
                sortOrder: $definition['sort_order']
            );
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardPayload(
        string $key,
        string $contextKey,
        string $displayName,
        string $description,
        string $icon,
        string $style,
        int $current,
        int $target,
        int $priority,
        int $sortOrder
    ): array {
        $current = min($current, $target);

        return [
            'achievement_key' => $key,
            'context_key' => $contextKey,
            'display_name' => $displayName,
            'description' => $description,
            'icon' => $icon,
            'style' => $style,
            'current' => $current,
            'target' => $target,
            'progress_percent' => $target > 0 ? min(100, round(($current / $target) * 100)) : 0,
            'remaining' => max(0, $target - $current),
            'priority' => $priority,
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function definitions(): array
    {
        return config('achievements.definitions', []);
    }
}

<?php

namespace App\Services;

use App\Models\ReadingLog;
use App\Models\User;
use App\Models\UserAchievement;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AchievementService
{
    private const int WEEKLY_TARGET_DAYS = 4;

    private const array STREAK_THRESHOLDS = [7, 30, 100, 365];

    private const array DASHBOARD_STREAK_WINDOWS = [
        7 => 7,
        30 => 7,
        100 => 7,
        365 => 18,
    ];

    private const array BIBLE_PROGRESS_THRESHOLDS = [25, 50, 75, 100];

    public function __construct(
        private BibleReferenceService $bibleReferenceService,
        private ReadingCalendarService $readingCalendar,
        private BookProgressService $bookProgressService
    ) {}

    /**
     * @return array{awarded: int, skipped_duplicates: int, updated_completion_dates: int, would_award: int, would_award_by_key: array<string, int>, would_update_completion_dates: int, would_update_completion_dates_by_key: array<string, int>, candidates: Collection<int, array<string, mixed>>, awarded_achievements: Collection<int, UserAchievement>}
     */
    public function evaluateAndAward(User $user, bool $dryRun = false, bool $reconcileExistingCompletionDates = false): array
    {
        $existingContexts = $user->achievements()
            ->get(['id', 'achievement_key', 'context_key', 'earned_at', 'completed_on', 'metadata'])
            ->mapWithKeys(fn (UserAchievement $achievement): array => [
                $achievement->achievement_key.'|'.$achievement->context_key => $achievement,
            ]);
        $candidates = $this->buildAwardCandidates($user, $existingContexts, $reconcileExistingCompletionDates);
        $awardedAchievements = collect();
        $awarded = 0;
        $skippedDuplicates = 0;
        $updatedCompletionDates = 0;
        $wouldAward = 0;
        $wouldAwardByKey = [];
        $wouldUpdateCompletionDates = 0;
        $wouldUpdateCompletionDatesByKey = [];

        foreach ($candidates as $candidate) {
            $contextKey = $candidate['achievement_key'].'|'.$candidate['context_key'];
            $existingAchievement = $existingContexts->get($contextKey);

            if ($existingAchievement instanceof UserAchievement) {
                if ($this->completedOnMissing($existingAchievement, $candidate)) {
                    if ($dryRun) {
                        $wouldUpdateCompletionDates++;
                        $wouldUpdateCompletionDatesByKey[$candidate['achievement_key']] = ($wouldUpdateCompletionDatesByKey[$candidate['achievement_key']] ?? 0) + 1;
                    } else {
                        $existingAchievement->completed_on = $candidate['completed_on'];
                        $existingAchievement->save();
                        $updatedCompletionDates++;
                    }
                }

                $skippedDuplicates++;

                continue;
            }

            if ($dryRun) {
                $wouldAward++;
                $wouldAwardByKey[$candidate['achievement_key']] = ($wouldAwardByKey[$candidate['achievement_key']] ?? 0) + 1;

                continue;
            }

            try {
                $achievement = $user->achievements()->create($candidate);
                $awardedAchievements->push($achievement);
                $existingContexts->put($contextKey, $achievement);
                $awarded++;
            } catch (UniqueConstraintViolationException $exception) {
                $exists = $user->achievements()
                    ->where('achievement_key', $candidate['achievement_key'])
                    ->where('context_key', $candidate['context_key'])
                    ->exists();

                if (! $exists) {
                    throw $exception;
                }

                $existingContexts->put($contextKey, true);
                $skippedDuplicates++;
            }
        }

        return [
            'awarded' => $awarded,
            'skipped_duplicates' => $skippedDuplicates,
            'updated_completion_dates' => $updatedCompletionDates,
            'would_award' => $wouldAward,
            'would_award_by_key' => $wouldAwardByKey,
            'would_update_completion_dates' => $wouldUpdateCompletionDates,
            'would_update_completion_dates_by_key' => $wouldUpdateCompletionDatesByKey,
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
            ->map(function (UserAchievement $achievement): array {
                $celebrationCopy = $this->celebrationCopy($achievement);

                return [
                    'id' => $achievement->id,
                    'display_name' => $celebrationCopy['display_name'],
                    'description' => $celebrationCopy['description'],
                    'icon' => $achievement->icon,
                    'style' => $achievement->style,
                    'category' => $achievement->category,
                    'is_repeat_book_completion' => $achievement->achievement_key === 'book_completed'
                        && (int) ($achievement->metadata['completion_number'] ?? 1) > 1,
                    'earned_at' => $achievement->earned_at?->format('M j, Y'),
                ];
            })
            ->values()
            ->all();

        $progress = $this->nearestLockedGoalsByCategory(
            $this->getLockedAchievements($user, $user->achievements()->get()),
            3
        )
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
     * @return array{earned: Collection<string, Collection<int, array{achievement: UserAchievement, count: int, completion_dates: Collection<int, array{completed_on: ?Carbon, earned_at: ?Carbon}>}>>, locked: Collection<int, array<string, mixed>>, next_goals: array{books: Collection<int, array<string, mixed>>, progress: Collection<int, array<string, mixed>>}, recent: Collection<int, UserAchievement>}
     */
    public function getShelfData(User $user): array
    {
        $earned = $user->achievements()
            ->orderByDesc('earned_at')
            ->orderBy('sort_order')
            ->get();
        $overallProgress = $this->bookProgressService->getOverallProgress($user);
        $locked = $this->getLockedAchievements($user, $earned, $overallProgress);

        return [
            'earned' => $earned
                ->groupBy('category')
                ->map(fn (Collection $categoryAchievements): Collection => $categoryAchievements
                    ->groupBy(fn (UserAchievement $achievement): string => $this->earnedAchievementGroupKey($achievement))
                    ->map(function (Collection $group): array {
                        $achievement = $group->first();

                        $completionDates = match ($achievement->achievement_key) {
                            'book_completed', 'testament_completed', 'bible_completed' => $group
                                ->sortBy(fn (UserAchievement $completion): int => (int) ($completion->metadata['completion_number'] ?? 1))
                                ->map(fn (UserAchievement $completion): array => [
                                    'completed_on' => $completion->completed_on,
                                    'earned_at' => $completion->earned_at,
                                ])
                                ->values(),
                            default => collect(),
                        };

                        return [
                            'achievement' => $achievement,
                            'count' => $group->count(),
                            'completion_dates' => $completionDates,
                        ];
                    })
                    ->values()),
            'locked' => $locked,
            'next_goals' => $this->nextGoals($overallProgress, $locked),
            'recent' => $earned->take(3),
        ];
    }

    /**
     * @param  array<string, mixed>  $overallProgress
     * @param  Collection<int, array<string, mixed>>  $lockedAchievements
     * @return array{books: Collection<int, array<string, mixed>>, progress: Collection<int, array<string, mixed>>}
     */
    private function nextGoals(array $overallProgress, Collection $lockedAchievements): array
    {
        return [
            'books' => $this->almostFinishedBooks($overallProgress),
            'progress' => $this->nearestLockedGoalsByCategory($lockedAchievements, 4),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lockedAchievements
     * @return Collection<int, array<string, mixed>>
     */
    private function nearestLockedGoalsByCategory(Collection $lockedAchievements, int $limit): Collection
    {
        return $lockedAchievements
            ->filter(fn (array $achievement): bool => (int) $achievement['current'] > 0)
            ->unique('category')
            ->sortByDesc(fn (array $achievement): int|float => $achievement['progress_percent'])
            ->take($limit)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $overallProgress
     * @return Collection<int, array<string, mixed>>
     */
    private function almostFinishedBooks(array $overallProgress): Collection
    {
        return $this->includedTestaments($overallProgress)
            ->flatMap(fn (array $testament): Collection => $testament['progress']['processed_books'])
            ->map(function (array $book): array {
                $chaptersRemaining = $book['chapter_count'] - $book['next_completion_chapters'];

                return [
                    'book_id' => $book['book_id'],
                    'book_name' => $book['name'],
                    'chapters_read' => $book['next_completion_chapters'],
                    'total_chapters' => $book['chapter_count'],
                    'chapters_remaining' => $chaptersRemaining,
                    'missing_chapters' => $chaptersRemaining <= 10 ? $book['next_completion_missing_chapters'] : [],
                    'completed_count' => $book['completed_count'],
                    'progress_percent' => round($book['next_completion_progress_percent']),
                    'icon' => 'book-open',
                    'style' => 'success',
                ];
            })
            ->filter(fn (array $goal): bool => $goal['chapters_read'] > 0 && ($goal['chapters_remaining'] <= 5 || $goal['progress_percent'] >= 75))
            ->sortBy(fn (array $goal): array => [$goal['chapters_remaining'], -$goal['progress_percent']])
            ->take(3)
            ->values();
    }

    /**
     * @param  array<string, mixed>|null  $overallProgress
     * @return array{latest: ?UserAchievement, milestone: ?array<string, mixed>}
     */
    public function getDashboardMilestone(User $user, ?array $overallProgress = null): array
    {
        $latest = $user->achievements()
            ->where('achievement_key', '!=', 'personal_best_streak')
            ->latest('earned_at')
            ->orderByDesc('sort_order')
            ->first();

        $earned = $user->achievements()->get();

        return [
            'latest' => $latest,
            'milestone' => $this->dashboardMilestone($user, $earned, $overallProgress),
        ];
    }

    /**
     * @param  Collection<int, UserAchievement>  $earned
     * @param  array<string, mixed>|null  $overallProgress
     * @return array<string, mixed>|null
     */
    private function dashboardMilestone(User $user, Collection $earned, ?array $overallProgress): ?array
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
        $currentStreak = $this->currentStreak($readingDates, $user);
        $overallProgress ??= $this->bookProgressService->getOverallProgress($user);
        $bibleProgress = $this->bibleProgress($overallProgress);

        $streakMilestone = $this->nextStreakDashboardMilestone($currentStreak, $earnedContexts);
        if ($streakMilestone !== null) {
            $candidates->push($streakMilestone);
        }

        $weeklyRhythmMilestone = $this->weeklyRhythmDashboardMilestone($readingDates, $user);
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

        $bibleCompletionMilestone = $this->nextBibleCompletionDashboardMilestone($overallProgress);
        if ($bibleCompletionMilestone !== null) {
            $candidates->push($bibleCompletionMilestone);
        }

        $this->almostFinishedBooks($overallProgress)
            ->each(fn (array $book): mixed => $candidates->push($this->dashboardPayload(
                key: 'book_completed',
                contextKey: 'book:'.$book['book_id'],
                displayName: $book['completed_count'] > 0 ? 'Complete '.$book['book_name'].' again' : 'Finish '.$book['book_name'],
                description: $book['chapters_remaining'].' '.str('chapter')->plural($book['chapters_remaining']).' left to '.($book['completed_count'] > 0 ? 'complete '.$book['book_name'].' again' : 'complete '.$book['book_name']).'.',
                icon: $book['icon'],
                style: $book['style'],
                current: $book['chapters_read'],
                target: $book['total_chapters'],
                priority: 10,
                sortOrder: 100 + (int) $book['book_id']
            )));

        $this->testamentProgressGoals($overallProgress)
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
            return $this->getLockedAchievements($user, $earned, $overallProgress)
                ->first();
        }

        return $candidates
            ->sort(function (array $a, array $b): int {
                return [
                    $a['priority'],
                    $a['remaining'],
                    -$a['progress_percent'],
                    $a['sort_order'],
                    $a['display_name'],
                ] <=> [
                    $b['priority'],
                    $b['remaining'],
                    -$b['progress_percent'],
                    $b['sort_order'],
                    $b['display_name'],
                ];
            })
            ->first();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildAwardCandidates(User $user, Collection $existingContexts, bool $reconcileExistingCompletionDates): Collection
    {
        $definitions = $this->definitions();
        $readingDates = $this->readingDates($user);
        $distinctReadingDays = $readingDates->count();
        $longestStreak = $this->longestStreak($readingDates);
        $includeDeuterocanonical = $user->includesDeuterocanonicalBooks();
        $overallProgress = $this->bookProgressService->getOverallProgress($user);
        $existingCompletionRequirements = $reconcileExistingCompletionDates
            ? $this->existingCompletionDateRequirements($existingContexts)
            : collect();
        $completionDateRequirements = $this->completionDateRequirements($overallProgress, $includeDeuterocanonical, $existingContexts);
        $existingBookIds = $existingCompletionRequirements->flatMap(
            fn (array $requirement): Collection => $requirement['books']
                ->flatMap(fn (Collection $books): Collection => $books->pluck('book_id'))
        );
        $completionDates = $this->completionDatesByChapter(
            $user,
            $completionDateRequirements['book_ids']
                ->concat($existingBookIds)
                ->unique()
                ->values(),
            $completionDateRequirements['completion_numbers']
                ->concat($existingCompletionRequirements->pluck('completion_number'))
                ->unique()
                ->values()
        );
        $bibleProgress = $this->bibleProgress($overallProgress);
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

        $this->bookCompletionCandidates($overallProgress, $includeDeuterocanonical, $completionDates, $existingContexts)->each(fn (array $candidate) => $candidates->push($candidate));
        $this->testamentCompletionCandidates($overallProgress, $completionDates, $existingContexts)->each(fn (array $candidate) => $candidates->push($candidate));
        $this->bibleCompletionCandidates($overallProgress, $includeDeuterocanonical, $completionDates, $existingContexts)->each(fn (array $candidate) => $candidates->push($candidate));
        $this->existingCompletionDateCandidates($existingCompletionRequirements, $completionDates)->each(fn (array $candidate) => $candidates->push($candidate));

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
            'earned_at' => $overrides['earned_at'] ?? now(),
            'completed_on' => $overrides['completed_on'] ?? null,
        ];
    }

    /**
     * @return array{display_name: string, description: string}
     */
    private function celebrationCopy(UserAchievement $achievement): array
    {
        $completionNumber = (int) ($achievement->metadata['completion_number'] ?? 1);

        if ($achievement->achievement_key !== 'book_completed' || $completionNumber < 2) {
            return [
                'display_name' => $achievement->display_name,
                'description' => $achievement->description,
            ];
        }

        return [
            'display_name' => "{$achievement->display_name} for the {$this->ordinal($completionNumber)} time",
            'description' => "Every chapter has been read {$completionNumber} times.",
        ];
    }

    private function ordinal(int $number): string
    {
        $lastTwoDigits = $number % 100;
        $suffix = match (true) {
            $lastTwoDigits >= 11 && $lastTwoDigits <= 13 => 'th',
            $number % 10 === 1 => 'st',
            $number % 10 === 2 => 'nd',
            $number % 10 === 3 => 'rd',
            default => 'th',
        };

        return "{$number}{$suffix}";
    }

    /**
     * @return array{eyebrow: string, title: string, description: string, icon: string, style: string, current_streak: int, previous_best: int}|null
     */
    private function recordCelebrationPayload(User $user): ?array
    {
        $readingDates = $this->readingDates($user);
        $currentStreak = $this->currentStreak($readingDates, $user);
        $previousBest = $this->previousBestBeforeCurrentRun($readingDates, $user);

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
    private function bookCompletionCandidates(array $overallProgress, bool $includeDeuterocanonical, Collection $completionDates, Collection $existingContexts): Collection
    {
        return $this->includedTestaments($overallProgress)
            ->flatMap(fn (array $testament): Collection => $testament['progress']['processed_books'])
            ->filter(fn (array $book): bool => $book['completed_count'] > 0)
            ->flatMap(function (array $book) use ($includeDeuterocanonical, $completionDates, $existingContexts): Collection {
                $bookName = $this->bibleReferenceService->getLocalizedBookName(
                    $book['book_id'],
                    includeDeuterocanonical: $includeDeuterocanonical || $book['book_id'] > 66
                );

                return collect(range(1, (int) $book['completed_count']))
                    ->map(function (int $completionNumber) use ($book, $bookName, $completionDates, $existingContexts): ?array {
                        $contextKey = $completionNumber === 1
                            ? 'book:'.$book['book_id']
                            : "book:{$book['book_id']}:completion:{$completionNumber}";
                        $completedOn = $this->completionDateForCandidate(
                            'book_completed',
                            $contextKey,
                            collect([$book]),
                            $completionNumber,
                            $completionDates,
                            $existingContexts
                        );

                        if ($completedOn === null) {
                            return null;
                        }

                        return $this->candidate(
                            'book_completed',
                            $contextKey,
                            [
                                'book_id' => $book['book_id'],
                                'book_name' => $bookName,
                                'completion_number' => $completionNumber,
                            ],
                            [
                                'display_name' => "Completed {$bookName}",
                                'description' => "You completed {$bookName}.",
                                'sort_order' => 100 + $book['book_id'],
                                'completed_on' => $completedOn->toDateString(),
                            ]
                        );
                    })
                    ->filter()
                    ->values();
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function testamentCompletionCandidates(array $overallProgress, Collection $completionDates, Collection $existingContexts): Collection
    {
        return $this->includedTestaments($overallProgress)
            ->filter(fn (array $testament): bool => $testament['progress']['testament_completions'] > 0)
            ->flatMap(function (array $testament) use ($completionDates, $existingContexts): Collection {
                return collect(range(1, (int) $testament['progress']['testament_completions']))
                    ->map(function (int $completionNumber) use ($testament, $completionDates, $existingContexts): ?array {
                        $books = collect($testament['progress']['processed_books']);
                        $contextKey = $completionNumber === 1
                            ? 'testament:'.$testament['key']
                            : "testament:{$testament['key']}:completion:{$completionNumber}";
                        $completedOn = $this->completionDateForCandidate(
                            'testament_completed',
                            $contextKey,
                            $books,
                            $completionNumber,
                            $completionDates,
                            $existingContexts
                        );

                        if ($completedOn === null) {
                            return null;
                        }

                        return $this->candidate(
                            'testament_completed',
                            $contextKey,
                            [
                                'testament' => $testament['key'],
                                'label' => $testament['label'],
                                'completion_number' => $completionNumber,
                            ],
                            [
                                'display_name' => "Completed the {$testament['label']}",
                                'description' => "You completed every book in the {$testament['label']}.",
                                'completed_on' => $completedOn->toDateString(),
                            ]
                        );
                    })
                    ->filter()
                    ->values();
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function bibleCompletionCandidates(array $overallProgress, bool $includeDeuterocanonical, Collection $completionDates, Collection $existingContexts): Collection
    {
        $completions = (int) $overallProgress['bible_completions'];

        if ($completions === 0) {
            return collect();
        }

        $collectionKey = $includeDeuterocanonical ? 'with-deuterocanonical' : 'canonical';
        $collectionLabel = $includeDeuterocanonical ? 'Bible with Deuterocanonical books' : 'Bible';

        $books = $this->includedTestaments($overallProgress)
            ->flatMap(fn (array $testament): Collection => $testament['progress']['processed_books'])
            ->values();

        return collect(range(1, $completions))
            ->map(function (int $completionNumber) use ($books, $collectionKey, $collectionLabel, $completionDates, $existingContexts): ?array {
                $contextKey = "bible:{$collectionKey}:completion:{$completionNumber}";
                $completedOn = $this->completionDateForCandidate(
                    'bible_completed',
                    $contextKey,
                    $books,
                    $completionNumber,
                    $completionDates,
                    $existingContexts
                );

                if ($completedOn === null) {
                    return null;
                }

                return $this->candidate(
                    'bible_completed',
                    $contextKey,
                    [
                        'collection_key' => $collectionKey,
                        'completion_number' => $completionNumber,
                    ],
                    [
                        'display_name' => "Completed the {$collectionLabel}",
                        'description' => "You completed the {$collectionLabel}.",
                        'completed_on' => $completedOn->toDateString(),
                    ]
                );
            })
            ->filter()
            ->values();
    }

    /**
     * @return array{book_ids: Collection<int, int>, completion_numbers: Collection<int, int>}
     */
    private function completionDateRequirements(array $overallProgress, bool $includeDeuterocanonical, Collection $existingContexts): array
    {
        $bookIds = collect();
        $completionNumbers = collect();
        $testaments = $this->includedTestaments($overallProgress);

        foreach ($testaments as $testament) {
            $books = collect($testament['progress']['processed_books']);

            foreach ($books->filter(fn (array $book): bool => $book['completed_count'] > 0) as $book) {
                foreach (range(1, (int) $book['completed_count']) as $completionNumber) {
                    $contextKey = $completionNumber === 1
                        ? 'book:'.$book['book_id']
                        : "book:{$book['book_id']}:completion:{$completionNumber}";

                    if ($this->completionDateIsNeeded($existingContexts, 'book_completed', $contextKey)) {
                        $bookIds->push((int) $book['book_id']);
                        $completionNumbers->push($completionNumber);
                    }
                }
            }

            for ($completionNumber = 1; $completionNumber <= (int) $testament['progress']['testament_completions']; $completionNumber++) {
                $contextKey = $completionNumber === 1
                    ? 'testament:'.$testament['key']
                    : "testament:{$testament['key']}:completion:{$completionNumber}";

                if ($this->completionDateIsNeeded($existingContexts, 'testament_completed', $contextKey)) {
                    $bookIds->push(...$books->pluck('book_id')->all());
                    $completionNumbers->push($completionNumber);
                }
            }
        }

        $collectionKey = $includeDeuterocanonical ? 'with-deuterocanonical' : 'canonical';

        for ($completionNumber = 1; $completionNumber <= (int) $overallProgress['bible_completions']; $completionNumber++) {
            $contextKey = "bible:{$collectionKey}:completion:{$completionNumber}";

            if ($this->completionDateIsNeeded($existingContexts, 'bible_completed', $contextKey)) {
                $bookIds->push(...$testaments
                    ->flatMap(fn (array $testament): Collection => collect($testament['progress']['processed_books']))
                    ->pluck('book_id')
                    ->all());
                $completionNumbers->push($completionNumber);
            }
        }

        return [
            'book_ids' => $bookIds->unique()->values(),
            'completion_numbers' => $completionNumbers->unique()->values(),
        ];
    }

    /**
     * @param  Collection<int, int>  $bookIds
     * @param  Collection<int, int>  $completionNumbers
     * @return Collection<string, Collection<int, Carbon>>
     */
    private function completionDatesByChapter(User $user, Collection $bookIds, Collection $completionNumbers): Collection
    {
        if ($bookIds->isEmpty() || $completionNumbers->isEmpty()) {
            return collect();
        }

        $rankedReadings = $user->readingLogs()
            ->whereIn('book_id', $bookIds)
            ->select(['book_id', 'chapter', 'date_read'])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY book_id, chapter ORDER BY date_read, id) AS completion_number');

        return DB::query()
            ->fromSub($rankedReadings, 'ranked_readings')
            ->whereIn('completion_number', $completionNumbers)
            ->get(['book_id', 'chapter', 'completion_number', 'date_read'])
            ->groupBy(fn (object $reading): string => $this->completionChapterKey((int) $reading->book_id, (int) $reading->chapter))
            ->map(fn (Collection $readings): Collection => $readings->mapWithKeys(
                fn (object $reading): array => [
                    (int) $reading->completion_number => Carbon::parse($reading->date_read)->startOfDay(),
                ]
            ));
    }

    private function completionDateIsNeeded(Collection $existingContexts, string $achievementKey, string $contextKey): bool
    {
        return ! $existingContexts->has($achievementKey.'|'.$contextKey);
    }

    /**
     * Reconcile existing awards separately from current-canon eligibility for new awards.
     *
     * @param  Collection<string, UserAchievement>  $existingContexts
     * @return Collection<int, array{award: UserAchievement, books: Collection<int, Collection<int, array{book_id: int, chapter_count: int}>>, completion_number: int}>
     */
    private function existingCompletionDateRequirements(Collection $existingContexts): Collection
    {
        $awards = $existingContexts->filter(fn (UserAchievement $award): bool => $award->completed_on === null
            && in_array($award->achievement_key, ['book_completed', 'testament_completed', 'bible_completed'], true));

        if ($awards->isEmpty()) {
            return collect();
        }

        $canonicalBooks = collect($this->bibleReferenceService->listBibleBooks())
            ->map(fn (array $book): array => [
                'book_id' => (int) $book['id'],
                'chapter_count' => (int) $book['chapters'],
                'testament' => $book['testament'],
            ])->keyBy('book_id');
        $catholicBooks = collect($this->bibleReferenceService->listBibleBooks(includeDeuterocanonical: true))
            ->map(fn (array $book): array => [
                'book_id' => (int) $book['id'],
                'chapter_count' => (int) $book['chapters'],
                'testament' => $book['testament'],
            ])->keyBy('book_id');

        return $awards->map(function (UserAchievement $award) use ($canonicalBooks, $catholicBooks): ?array {
            $books = $this->existingAwardBookSets($award, $canonicalBooks, $catholicBooks);

            if ($books->isEmpty()) {
                return null;
            }

            preg_match('/completion:(\d+)$/', $award->context_key, $completionMatch);

            return [
                'award' => $award,
                'books' => $books,
                'completion_number' => (int) ($award->metadata['completion_number'] ?? $completionMatch[1] ?? 1),
            ];
        })->filter()->values();
    }

    /**
     * @return Collection<int, Collection<int, array{book_id: int, chapter_count: int}>>
     */
    private function existingAwardBookSets(UserAchievement $award, Collection $canonicalBooks, Collection $catholicBooks): Collection
    {
        if ($award->achievement_key === 'book_completed') {
            preg_match('/^book:(\d+)(?::completion:\d+)?$/', $award->context_key, $bookMatch);
            $bookId = (int) ($award->metadata['book_id'] ?? $bookMatch[1] ?? 0);
            $canonicalBook = $canonicalBooks->get($bookId);
            $catholicBook = $catholicBooks->get($bookId);

            if ($canonicalBook === null && $catholicBook === null) {
                return collect();
            }

            if ($canonicalBook === null || $catholicBook === null || $canonicalBook['chapter_count'] === $catholicBook['chapter_count']) {
                return collect([collect([$canonicalBook ?? $catholicBook])]);
            }

            return collect([collect([$canonicalBook]), collect([$catholicBook])]);
        }

        if ($award->achievement_key === 'testament_completed') {
            preg_match('/^testament:([^:]+)/', $award->context_key, $testamentMatch);
            $testament = $award->metadata['testament'] ?? $testamentMatch[1] ?? null;

            return match ($testament) {
                'old' => collect([
                    $canonicalBooks->where('testament', 'old')->values(),
                    $catholicBooks->where('testament', 'old')->values(),
                ]),
                'new' => collect([$canonicalBooks->where('testament', 'new')->values()]),
                'deuterocanonical' => collect([$catholicBooks->where('testament', 'deuterocanonical')->values()]),
                default => collect(),
            };
        }

        preg_match('/^bible:([^:]+)/', $award->context_key, $collectionMatch);
        $collectionKey = $award->metadata['collection_key'] ?? $collectionMatch[1] ?? null;

        return match ($collectionKey) {
            'canonical' => collect([$canonicalBooks->values()]),
            'with-deuterocanonical' => collect([$catholicBooks->values()]),
            default => collect(),
        };
    }

    /**
     * @param  Collection<int, array{award: UserAchievement, books: Collection<int, Collection<int, array{book_id: int, chapter_count: int}>>, completion_number: int}>  $requirements
     * @param  Collection<string, Collection<int, Carbon>>  $completionDates
     * @return Collection<int, array<string, mixed>>
     */
    private function existingCompletionDateCandidates(Collection $requirements, Collection $completionDates): Collection
    {
        return $requirements->map(function (array $requirement) use ($completionDates): ?array {
            $dates = $requirement['books']
                ->map(fn (Collection $books): ?Carbon => $this->completionEarnedAt($books, $requirement['completion_number'], $completionDates));
            $completionDate = $dates->first();
            $catholicDate = $dates->count() > 1 ? $dates->last() : null;

            if ($completionDate === null) {
                return null;
            }

            // Legacy book and Old Testament awards do not record which canon earned them.
            // Allow a day for the difference between an account's reading date and the UTC award timestamp.
            $latestPossibleAwardDate = $requirement['award']->earned_at->copy()->addDay()->toDateString();
            if ($catholicDate !== null && ! $completionDate->equalTo($catholicDate)
                && $catholicDate->toDateString() <= $latestPossibleAwardDate) {
                return null;
            }

            return [
                'achievement_key' => $requirement['award']->achievement_key,
                'context_key' => $requirement['award']->context_key,
                'sort_order' => $requirement['award']->sort_order,
                'completed_on' => $completionDate->toDateString(),
            ];
        })->filter()->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $books
     * @param  Collection<string, Collection<int, Carbon>>  $completionDates
     */
    private function completionDateForCandidate(
        string $achievementKey,
        string $contextKey,
        Collection $books,
        int $completionNumber,
        Collection $completionDates,
        Collection $existingContexts
    ): ?Carbon {
        $existingAchievement = $existingContexts->get($achievementKey.'|'.$contextKey);

        if ($existingAchievement instanceof UserAchievement && $existingAchievement->completed_on !== null) {
            return Carbon::parse($existingAchievement->completed_on)->startOfDay();
        }

        if ($existingAchievement instanceof UserAchievement) {
            return null;
        }

        return $this->completionEarnedAt($books, $completionNumber, $completionDates);
    }

    /**
     * Return the date the nth whole pass finished: the latest nth-reading date among its chapters.
     *
     * @param  Collection<int, array<string, mixed>>  $books
     * @param  Collection<string, Collection<int, Carbon>>  $completionDates
     */
    private function completionEarnedAt(Collection $books, int $completionNumber, Collection $completionDates): ?Carbon
    {
        $latestChapterDate = null;

        foreach ($books as $book) {
            foreach (range(1, (int) $book['chapter_count']) as $chapter) {
                $chapterDate = $completionDates
                    ->get($this->completionChapterKey((int) $book['book_id'], $chapter))
                    ?->get($completionNumber);

                if ($chapterDate === null) {
                    return null;
                }

                if ($latestChapterDate === null || $chapterDate->greaterThan($latestChapterDate)) {
                    $latestChapterDate = $chapterDate;
                }
            }
        }

        return $latestChapterDate;
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function completedOnMissing(UserAchievement $achievement, array $candidate): bool
    {
        $candidateCompletedOn = $candidate['completed_on'] ?? null;

        return is_string($candidateCompletedOn)
            && $achievement->completed_on === null;
    }

    private function completionChapterKey(int $bookId, int $chapter): string
    {
        return "{$bookId}:{$chapter}";
    }

    private function earnedAchievementGroupKey(UserAchievement $achievement): string
    {
        return match ($achievement->achievement_key) {
            'book_completed' => 'book:'.($achievement->metadata['book_id'] ?? $achievement->context_key),
            'testament_completed' => 'testament:'.($achievement->metadata['testament'] ?? $achievement->context_key),
            'bible_completed' => 'bible:'.($achievement->metadata['collection_key'] ?? $achievement->context_key),
            default => $achievement->achievement_key.':'.$achievement->context_key,
        };
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function testamentProgressGoals(array $overallProgress): Collection
    {
        return $this->includedTestaments($overallProgress)
            ->map(function (array $testament): array {
                $progress = $testament['progress'];
                $total = $progress['total_books'];
                $completed = $progress['completed_books'];
                $remaining = $total - $completed;

                return [
                    'testament' => $testament['key'],
                    'label' => $testament['label'],
                    'books_completed' => $completed,
                    'total_books' => $total,
                    'books_remaining' => $remaining,
                    'progress_percent' => $total > 0 ? round(($completed / $total) * 100) : 0,
                ];
            })
            ->filter(fn (array $goal): bool => $goal['books_remaining'] > 0)
            ->filter(fn (array $goal): bool => $goal['books_remaining'] <= 5 || $goal['progress_percent'] >= 75)
            ->sortBy(fn (array $goal): array => [$goal['books_remaining'], -$goal['progress_percent']])
            ->values();
    }

    /**
     * @return array{percentage: float, chapters_read: int, total_chapters: int}
     */
    private function bibleProgress(array $overallProgress): array
    {
        return [
            'percentage' => $overallProgress['first_coverage_percent'],
            'chapters_read' => $overallProgress['first_coverage_chapters'],
            'total_chapters' => $overallProgress['next_completion_target'],
        ];
    }

    /**
     * @return Collection<int, array{key: string, label: string, progress: array<string, mixed>}>
     */
    private function includedTestaments(array $overallProgress): Collection
    {
        return collect([
            ['key' => 'old', 'label' => 'Old Testament', 'progress' => $overallProgress['old_testament']],
            ['key' => 'new', 'label' => 'New Testament', 'progress' => $overallProgress['new_testament']],
            ['key' => 'deuterocanonical', 'label' => 'Deuterocanonical books', 'progress' => $overallProgress['deuterocanonical']],
        ])->filter(fn (array $testament): bool => $testament['progress'] !== null)->values();
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

    private function currentStreak(Collection $readingDates, User $user): int
    {
        if ($readingDates->isEmpty()) {
            return 0;
        }

        $lookup = $readingDates->map(fn (Carbon $date): string => $date->toDateString())->flip();
        $checkDate = $this->readingCalendar->todayFor($user)->toMutable();
        $yesterday = $this->readingCalendar->yesterdayFor($user)->toMutable();

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

    private function previousBestBeforeCurrentRun(Collection $readingDates, User $user): int
    {
        $currentStreak = $this->currentStreak($readingDates, $user);

        if ($currentStreak === 0) {
            return $this->longestStreak($readingDates);
        }

        return $this->longestStreak($readingDates->slice(0, max(0, $readingDates->count() - $currentStreak))->values());
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function getLockedAchievements(User $user, Collection $earned, ?array $overallProgress = null): Collection
    {
        $earnedContexts = $earned
            ->map(fn (UserAchievement $achievement): string => $achievement->achievement_key.'|'.$achievement->context_key)
            ->flip();
        $definitions = $this->definitions();
        $readingDates = $this->readingDates($user);
        $readingDays = $readingDates->count();
        $longestStreak = $this->longestStreak($readingDates);
        $overallProgress ??= $this->bookProgressService->getOverallProgress($user);
        $bibleProgress = $this->bibleProgress($overallProgress);

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
        $milestone = null;

        if ($currentStreak > 0) {
            foreach (self::STREAK_THRESHOLDS as $threshold) {
                $key = "reading_streak_{$threshold}";
                $contextKey = "streak:{$threshold}";

                if ($earnedContexts->has($key.'|'.$contextKey)) {
                    continue;
                }

                $remaining = $threshold - $currentStreak;
                $window = self::DASHBOARD_STREAK_WINDOWS[$threshold] ?? 7;

                if ($remaining > $window) {
                    break;
                }

                $definition = $this->definitions()[$key];

                $milestone = $this->dashboardPayload(
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

                break;
            }
        }

        return $milestone;
    }

    private function weeklyRhythmDashboardMilestone(Collection $readingDates, User $user): ?array
    {
        $weekStart = $this->readingCalendar->todayFor($user)->toMutable()->startOfWeek(Carbon::SUNDAY);
        $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();
        $current = $readingDates
            ->filter(fn (Carbon $date): bool => $date->toDateString() >= $weekStart->toDateString() && $date->toDateString() <= $weekEnd->toDateString())
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
     * Build a dashboard-only goal for progress toward the next lifetime Bible completion.
     *
     * @param  array<string, mixed>  $overallProgress
     * @return array<string, mixed>|null
     */
    private function nextBibleCompletionDashboardMilestone(array $overallProgress): ?array
    {
        $completions = (int) $overallProgress['bible_completions'];

        if ($completions === 0) {
            return null;
        }

        $nextCompletion = $completions + 1;
        $totalChapters = (int) $overallProgress['next_completion_target'];

        return $this->dashboardPayload(
            key: 'bible_completion',
            contextKey: "bible:completion:{$nextCompletion}",
            displayName: 'Complete the Bible again',
            description: "Read all {$totalChapters} chapters again to reach Bible completion {$nextCompletion}.",
            icon: 'book-open',
            style: 'success',
            current: (int) $overallProgress['next_completion_chapters'],
            target: $totalChapters,
            priority: 30,
            sortOrder: 440
        );
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

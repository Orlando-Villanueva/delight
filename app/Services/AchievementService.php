<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAchievement;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

class AchievementService
{
    private const int WEEKLY_TARGET_DAYS = 4;

    public function __construct(
        private BibleReferenceService $bibleReferenceService
    ) {}

    /**
     * @return array{awarded: int, skipped_duplicates: int, would_award: int, candidates: Collection<int, array<string, mixed>>}
     */
    public function evaluateAndAward(User $user, bool $dryRun = false): array
    {
        $candidates = $this->buildAwardCandidates($user);
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
                $user->achievements()->create($candidate);
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
        ];
    }

    /**
     * @return array{earned: Collection<string, Collection<int, UserAchievement>>, locked: Collection<int, array<string, mixed>>, recent: Collection<int, UserAchievement>}
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
            'recent' => $earned->take(3),
        ];
    }

    /**
     * @return array{latest: ?UserAchievement, next: ?array<string, mixed>}
     */
    public function getDashboardTeaser(User $user): array
    {
        $latest = $user->achievements()
            ->latest('earned_at')
            ->orderByDesc('sort_order')
            ->first();

        $locked = $this->getLockedAchievements($user, $user->achievements()->get());

        return [
            'latest' => $latest,
            'next' => $locked->first(),
        ];
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
        $currentStreak = $this->currentStreak($readingDates);
        $weeklyConsistency = $this->weeklyConsistencyStreak($readingDates);
        $bibleProgress = $this->bibleProgress($user);
        $candidates = collect();

        if ($distinctReadingDays >= 1) {
            $candidates->push($this->candidate('first_reading', 'first-reading'));
        }

        foreach (['first_week', 'first_month'] as $key) {
            if ($distinctReadingDays >= (int) $definitions[$key]['threshold']) {
                $candidates->push($this->candidate($key, 'reading-days:'.$definitions[$key]['threshold'], [
                    'reading_days' => $distinctReadingDays,
                ]));
            }
        }

        foreach ([7, 30, 100, 365] as $threshold) {
            $key = "reading_streak_{$threshold}";
            if ($longestStreak >= $threshold) {
                $candidates->push($this->candidate($key, "streak:{$threshold}", [
                    'streak_days' => $threshold,
                    'longest_streak' => $longestStreak,
                ]));
            }
        }

        if ($currentStreak > 1 && $currentStreak > $this->previousBestBeforeCurrentRun($readingDates)) {
            $candidates->push($this->candidate('personal_best_streak', "streak:{$currentStreak}", [
                'streak_days' => $currentStreak,
            ], [
                'display_name' => "New longest streak: {$currentStreak} days",
                'description' => "You set a {$currentStreak}-day personal-best reading streak.",
            ]));
        }

        foreach ([4, 8, 12] as $threshold) {
            $key = "weekly_consistency_{$threshold}";
            if ($weeklyConsistency >= $threshold) {
                $candidates->push($this->candidate($key, "weekly-target:{$threshold}", [
                    'weeks' => $threshold,
                    'best_weekly_target_streak' => $weeklyConsistency,
                ]));
            }
        }

        foreach ([25, 50, 75, 100] as $threshold) {
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

    private function weeklyConsistencyStreak(Collection $readingDates): int
    {
        if ($readingDates->isEmpty()) {
            return 0;
        }

        $achievedWeeks = $readingDates
            ->groupBy(fn (Carbon $date): string => $date->copy()->startOfWeek(Carbon::SUNDAY)->toDateString())
            ->filter(fn (Collection $dates): bool => $dates->count() >= self::WEEKLY_TARGET_DAYS)
            ->keys()
            ->map(fn (string $date): Carbon => Carbon::parse($date))
            ->values();

        if ($achievedWeeks->isEmpty()) {
            return 0;
        }

        $best = 1;
        $current = 1;
        $previous = $achievedWeeks->first();

        foreach ($achievedWeeks->skip(1) as $weekStart) {
            if ((int) $previous->diffInWeeks($weekStart) === 1) {
                $current++;
                $best = max($best, $current);
            } else {
                $current = 1;
            }

            $previous = $weekStart;
        }

        return $best;
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
        $weeklyConsistency = $this->weeklyConsistencyStreak($this->readingDates($user));
        $bibleProgress = $this->bibleProgress($user);

        $locked = collect([
            $this->lockedPayload('first_reading', 'first-reading', min($readingDays, 1), 1),
            $this->lockedPayload('first_week', 'reading-days:7', $readingDays, 7),
            $this->lockedPayload('first_month', 'reading-days:30', $readingDays, 30),
        ]);

        foreach ([7, 30, 100, 365] as $threshold) {
            $locked->push($this->lockedPayload("reading_streak_{$threshold}", "streak:{$threshold}", $longestStreak, $threshold));
        }

        foreach ([4, 8, 12] as $threshold) {
            $locked->push($this->lockedPayload("weekly_consistency_{$threshold}", "weekly-target:{$threshold}", $weeklyConsistency, $threshold));
        }

        foreach ([25, 50, 75, 100] as $threshold) {
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
     * @return array<string, array<string, mixed>>
     */
    private function definitions(): array
    {
        return config('achievements.definitions', []);
    }
}

<?php

namespace App\Services;

use App\Models\User;

class ReadingFormService
{
    public function __construct(
        private ReadingLogService $readingLogService,
        private BibleReferenceService $bibleReferenceService
    ) {}

    /**
     * Check if the user has read today.
     */
    public function hasReadToday(User $user): bool
    {
        return $user->readingLogs()
            ->whereDate('date_read', today())
            ->exists();
    }

    /**
     * Get yesterday availability logic and user reading status for the form.
     * This determines if the "yesterday" option should be available based on streak preservation.
     */
    public function getFormContextData(User $user, ?string $locale = null): array
    {
        $hasReadToday = $this->hasReadToday($user);

        $hasReadYesterday = $user->readingLogs()
            ->whereDate('date_read', today()->subDay())
            ->exists();

        $currentStreak = $this->readingLogService->calculateCurrentStreak($user);

        // Check if user is new (created today) to prevent logging for yesterday before they existed
        $isNewUser = $user->created_at->isToday();

        // Yesterday option logic:
        // 1. If already read yesterday, don't show the option
        // 2. If user is new (created today), don't allow yesterday (they didn't exist)
        // 3. If current streak > 0 AND haven't read today, yesterday could break the streak pattern
        // 4. Allow yesterday if: no streak OR has read today OR hasn't read yesterday
        $allowYesterday = ! $hasReadYesterday && ! $isNewUser && ($currentStreak === 0 || $hasReadToday);

        return [
            'allowYesterday' => $allowYesterday,
            'hasReadToday' => $hasReadToday,
            'hasReadYesterday' => $hasReadYesterday,
            'currentStreak' => $currentStreak,
            'recentBooks' => $this->getRecentBooks($user, $locale),
        ];
    }

    /**
     * Build a quick-pick list of the user's most recently logged books.
     */
    public function getRecentBooks(User $user, ?string $locale = null, int $limit = 5): array
    {
        $recentBooks = $this->readingLogService->getRecentBooks($user, $limit);

        if ($recentBooks->isEmpty()) {
            return [];
        }

        $localizedBooks = collect($this->bibleReferenceService->listBibleBooks(null, $locale))->keyBy('id');

        return $recentBooks
            ->filter(fn (array $book) => $localizedBooks->has($book['book_id']))
            ->map(function (array $book) use ($localizedBooks) {
                $bookMeta = $localizedBooks->get($book['book_id']);
                $lastReadAt = $book['last_read_at'];

                return [
                    'id' => $bookMeta['id'],
                    'name' => $bookMeta['name'],
                    'testament' => $bookMeta['testament'],
                    'chapters' => $bookMeta['chapters'],
                    'last_read_at' => $lastReadAt->toDateString(),
                    'last_read_label' => $lastReadAt->format('M d, Y'),
                    'last_read_for_humans' => $lastReadAt->diffForHumans(),
                ];
            })
            ->values()
            ->all();
    }
}

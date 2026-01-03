<?php

namespace App\Services;

use App\Models\ReadingLog;
use App\Models\ReadingPlan;
use App\Models\ReadingPlanSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReadingPlanService
{
    public function __construct(
        private BibleReferenceService $bibleService,
        private ReadingLogService $readingLogService
    ) {}

    /**
     * Subscribe a user to a reading plan.
     */
    public function subscribe(User $user, ReadingPlan $plan, ?Carbon $startDate = null): ReadingPlanSubscription
    {
        return ReadingPlanSubscription::updateOrCreate(
            [
                'user_id' => $user->id,
                'reading_plan_id' => $plan->id,
            ],
            [
                'started_at' => $startDate ?? Carbon::today(),
            ]
        );
    }

    /**
     * Unsubscribe a user from a reading plan.
     */
    public function unsubscribe(User $user, ReadingPlan $plan): bool
    {
        return ReadingPlanSubscription::where('user_id', $user->id)
            ->where('reading_plan_id', $plan->id)
            ->delete() > 0;
    }

    /**
     * Get the user's subscription for a specific plan.
     */
    public function getSubscription(User $user, ReadingPlan $plan): ?ReadingPlanSubscription
    {
        return ReadingPlanSubscription::where('user_id', $user->id)
            ->where('reading_plan_id', $plan->id)
            ->first();
    }

    /**
     * Get today's reading for a subscription with completion status.
     */
    public function getTodaysReadingWithStatus(
        ReadingPlanSubscription $subscription,
        ?Carbon $forDate = null
    ): ?array {
        $date = $forDate ?? Carbon::today();
        $reading = $subscription->getTodaysReading($forDate);

        if (! $reading) {
            return null;
        }

        $chapters = $reading['chapters'] ?? [];
        $completedChapters = $this->getCompletedChapters(
            $subscription->user,
            $chapters,
            $date
        );

        // Attach completion status to each chapter
        $chaptersWithStatus = array_map(function ($chapter) use ($completedChapters) {
            $key = $chapter['book_id'].'-'.$chapter['chapter'];

            return array_merge($chapter, [
                'completed' => in_array($key, $completedChapters),
            ]);
        }, $chapters);

        return [
            'day' => $reading['day'],
            'label' => $reading['label'],
            'chapters' => $chaptersWithStatus,
            'all_completed' => count($completedChapters) === count($chapters),
            'completed_count' => count($completedChapters),
            'total_count' => count($chapters),
        ];
    }

    /**
     * Get completed chapter keys for given chapters on a date.
     *
     * @return array<string> Array of "book_id-chapter" keys
     */
    public function getCompletedChapters(User $user, array $chapters, Carbon $date): array
    {
        if (empty($chapters)) {
            return [];
        }

        // Build query to check which chapters are already logged for this date
        $completedLogs = ReadingLog::where('user_id', $user->id)
            ->whereDate('date_read', $date)
            ->get(['book_id', 'chapter']);

        $completedKeys = $completedLogs->map(fn ($log) => $log->book_id.'-'.$log->chapter)->toArray();

        // Filter to only chapters in our list
        $relevantKeys = [];
        foreach ($chapters as $chapter) {
            $key = $chapter['book_id'].'-'.$chapter['chapter'];
            if (in_array($key, $completedKeys)) {
                $relevantKeys[] = $key;
            }
        }

        return $relevantKeys;
    }

    /**
     * Log a single chapter from a reading plan.
     */
    public function logChapter(User $user, array $chapter, Carbon $date): ReadingLog
    {
        $bookId = $chapter['book_id'];
        $chapterNum = $chapter['chapter'];

        return $this->readingLogService->logReading($user, [
            'book_id' => $bookId,
            'chapter' => $chapterNum,
            'date_read' => $date->toDateString(),
        ]);
    }

    /**
     * Log all chapters from a reading plan day.
     */
    public function logAllChapters(User $user, array $chapters, Carbon $date): Collection
    {
        $logged = collect();

        foreach ($chapters as $chapter) {
            $key = $chapter['book_id'].'-'.$chapter['chapter'];

            // Check if already logged
            $exists = ReadingLog::where('user_id', $user->id)
                ->where('book_id', $chapter['book_id'])
                ->where('chapter', $chapter['chapter'])
                ->whereDate('date_read', $date)
                ->exists();

            if (! $exists) {
                $log = $this->logChapter($user, $chapter, $date);
                $logged->push($log);
            }
        }

        return $logged;
    }
}

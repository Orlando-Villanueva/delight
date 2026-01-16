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
        $subscription = ReadingPlanSubscription::where('user_id', $user->id)
            ->where('reading_plan_id', $plan->id)
            ->first();

        if (! $subscription) {
            return false;
        }

        ReadingLog::where('reading_plan_subscription_id', $subscription->id)
            ->update([
                'reading_plan_subscription_id' => null,
                'reading_plan_day' => null,
            ]);

        return $subscription->delete();
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
        ?int $dayNumber = null
    ): ?array {
        $dayNumber = $dayNumber ?? $subscription->getDayNumber();
        $reading = $subscription->getTodaysReading($dayNumber);

        if (! $reading) {
            return null;
        }

        $chapters = $reading['chapters'] ?? [];
        $completedChapters = $this->getCompletedChaptersForDay(
            $subscription,
            $chapters,
            $dayNumber
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
     * Get completed chapter keys for given chapters on a plan day.
     *
     * @return array<string> Array of "book_id-chapter" keys
     */
    public function getCompletedChaptersForDay(
        ReadingPlanSubscription $subscription,
        array $chapters,
        int $dayNumber
    ): array {
        if (empty($chapters)) {
            return [];
        }

        // Build query to check which chapters are already logged for this plan day
        $completedLogs = ReadingLog::where('reading_plan_subscription_id', $subscription->id)
            ->where('reading_plan_day', $dayNumber)
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
    public function logChapter(
        User $user,
        ReadingPlanSubscription $subscription,
        int $dayNumber,
        array $chapter,
        Carbon $date
    ): ReadingLog {
        $bookId = $chapter['book_id'];
        $chapterNum = $chapter['chapter'];

        $existingLog = ReadingLog::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('chapter', $chapterNum)
            ->whereDate('date_read', $date)
            ->first();

        if ($existingLog) {
            if ($existingLog->reading_plan_subscription_id === null) {
                $existingLog->update([
                    'reading_plan_subscription_id' => $subscription->id,
                    'reading_plan_day' => $dayNumber,
                ]);
            }

            $subscription->resetCompletedDaysCountCache();

            return $existingLog;
        }

        $log = $this->readingLogService->logReading($user, [
            'book_id' => $bookId,
            'chapter' => $chapterNum,
            'date_read' => $date->toDateString(),
            'reading_plan_subscription_id' => $subscription->id,
            'reading_plan_day' => $dayNumber,
        ]);

        $subscription->resetCompletedDaysCountCache();

        return $log;
    }

    /**
     * Log all chapters from a reading plan day.
     */
    public function logAllChapters(
        User $user,
        ReadingPlanSubscription $subscription,
        int $dayNumber,
        array $chapters,
        Carbon $date
    ): Collection {
        $logged = collect();

        foreach ($chapters as $chapter) {
            $existingLog = ReadingLog::where('user_id', $user->id)
                ->where('book_id', $chapter['book_id'])
                ->where('chapter', $chapter['chapter'])
                ->whereDate('date_read', $date)
                ->first();

            if ($existingLog) {
                if ($existingLog->reading_plan_subscription_id === null) {
                    $existingLog->update([
                        'reading_plan_subscription_id' => $subscription->id,
                        'reading_plan_day' => $dayNumber,
                    ]);
                }

                continue;
            }

            $log = $this->logChapter($user, $subscription, $dayNumber, $chapter, $date);
            $logged->push($log);
        }

        $subscription->resetCompletedDaysCountCache();

        return $logged;
    }
}

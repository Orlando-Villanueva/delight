<?php

namespace App\Services;

use App\Models\ReadingLog;
use App\Models\ReadingPlan;
use App\Models\ReadingPlanDayCompletion;
use App\Models\ReadingPlanSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReadingPlanService
{
    public function __construct(
        private ReadingLogService $readingLogService
    ) {}

    /**
     * Subscribe a user to a reading plan.
     * Deactivates any other active subscriptions for the user.
     */
    public function subscribe(User $user, ReadingPlan $plan, ?Carbon $startDate = null): ReadingPlanSubscription
    {
        return DB::transaction(function () use ($user, $plan, $startDate) {
            // Deactivate all existing subscriptions for this user
            ReadingPlanSubscription::where('user_id', $user->id)
                ->update(['is_active' => false]);

            // Create or update the subscription and set it as active
            return ReadingPlanSubscription::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'reading_plan_id' => $plan->id,
                ],
                [
                    'started_at' => $startDate ?? Carbon::today(),
                    'is_active' => true,
                ]
            );
        });
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

        // Delete all junction table records linking logs to this subscription
        ReadingPlanDayCompletion::where('reading_plan_subscription_id', $subscription->id)
            ->delete();

        $deleted = $subscription->delete();

        // Auto-activate if only one inactive subscription remains
        $this->autoActivateLoneSubscription($user);

        return $deleted;
    }

    /**
     * Auto-activate a user's subscription if they have exactly one and it's inactive.
     * This prevents the scenario of having a single paused plan with nothing active.
     */
    public function autoActivateLoneSubscription(User $user): ?ReadingPlanSubscription
    {
        $subscriptions = ReadingPlanSubscription::where('user_id', $user->id)->get();

        // Only auto-activate if there's exactly one subscription and it's inactive
        if ($subscriptions->count() === 1 && ! $subscriptions->first()->is_active) {
            $subscription = $subscriptions->first();
            $subscription->update(['is_active' => true]);

            return $subscription->fresh();
        }

        return null;
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
     * Activate a subscription, deactivating any other active subscriptions for the user.
     */
    public function activate(ReadingPlanSubscription $subscription): ReadingPlanSubscription
    {
        return DB::transaction(function () use ($subscription) {
            // Deactivate all subscriptions for this user
            ReadingPlanSubscription::where('user_id', $subscription->user_id)
                ->update(['is_active' => false]);

            // Activate the specified subscription
            $subscription->update(['is_active' => true]);

            return $subscription->fresh();
        });
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

        // Query junction table to find completed chapters for this plan day
        $completedLogs = ReadingPlanDayCompletion::where('reading_plan_subscription_id', $subscription->id)
            ->where('reading_plan_day', $dayNumber)
            ->with('readingLog:id,book_id,chapter')
            ->get()
            ->map(fn ($completion) => $completion->readingLog)
            ->filter(); // Remove any nulls from deleted logs

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
        Carbon $date,
        bool $resetCache = true
    ): ReadingLog {
        $bookId = $chapter['book_id'];
        $chapterNum = $chapter['chapter'];

        // Find existing log for this chapter on this date
        $existingLog = ReadingLog::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('chapter', $chapterNum)
            ->whereDate('date_read', $date)
            ->first();

        if ($existingLog) {
            $readingLog = $existingLog;
        } else {
            // Create new reading log (without plan fields on the log itself)
            $readingLog = $this->readingLogService->logReading($user, [
                'book_id' => $bookId,
                'chapter' => $chapterNum,
                'date_read' => $date->toDateString(),
            ]);
        }

        // Link to plan via junction table (updateOrCreate to avoid duplicates)
        ReadingPlanDayCompletion::updateOrCreate(
            [
                'reading_log_id' => $readingLog->id,
                'reading_plan_subscription_id' => $subscription->id,
            ],
            [
                'reading_plan_day' => $dayNumber,
            ]
        );

        if ($resetCache) {
            $subscription->resetCompletedDaysCountCache();
        }

        return $readingLog;
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
            $log = $this->logChapter($user, $subscription, $dayNumber, $chapter, $date, resetCache: false);
            $logged->push($log);
        }

        $subscription->resetCompletedDaysCountCache();

        return $logged;
    }
}

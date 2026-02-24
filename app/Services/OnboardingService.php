<?php

namespace App\Services;

use App\Jobs\SendOnboardingReminderJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class OnboardingService
{
    /**
     * Dismiss onboarding and optionally schedule a reminder for tomorrow.
     */
    public function remind(int $userId): void
    {
        $dispatchPayload = null;

        DB::transaction(function () use ($userId, &$dispatchPayload) {
            $now = now();
            $lockedUser = User::query()
                ->whereKey($userId)
                ->lockForUpdate()
                ->firstOrFail();

            $isEligible = $lockedUser->onboarding_dismissed_at === null
                && $lockedUser->celebrated_first_reading_at === null
                && ! $lockedUser->readingLogs()->exists();

            if (! $isEligible) {
                return;
            }

            // Dismiss onboarding whenever this escape hatch is selected.
            $lockedUser->onboarding_dismissed_at = $now;

            if ($lockedUser->marketing_emails_opted_out_at !== null) {
                $lockedUser->save();

                return;
            }

            if ($lockedUser->onboarding_reminder_requested_at !== null) {
                $lockedUser->save();

                return;
            }

            $lockedUser->onboarding_reminder_requested_at = $now;
            $lockedUser->save();

            $dispatchPayload = [
                'user_id' => $lockedUser->id,
                'requested_at' => $now->copy(),
            ];
        });

        if ($dispatchPayload !== null) {
            try {
                SendOnboardingReminderJob::dispatch(
                    $dispatchPayload['user_id'],
                    $dispatchPayload['requested_at']->toIso8601String()
                )->delay($dispatchPayload['requested_at']->copy()->addDay());
            } catch (Throwable $exception) {
                // Best-effort rollback so users can retry if queueing fails.
                User::query()
                    ->whereKey($dispatchPayload['user_id'])
                    ->where('onboarding_dismissed_at', $dispatchPayload['requested_at']->toDateTimeString())
                    ->where('onboarding_reminder_requested_at', $dispatchPayload['requested_at']->toDateTimeString())
                    ->update([
                        'onboarding_dismissed_at' => null,
                        'onboarding_reminder_requested_at' => null,
                    ]);

                throw $exception;
            }
        }
    }

    /**
     * Dismiss the onboarding flow for a user.
     */
    public function dismiss(User $user): void
    {
        $user->update([
            'onboarding_dismissed_at' => now(),
        ]);
    }
}

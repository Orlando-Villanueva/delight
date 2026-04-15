<?php

namespace App\Services;

use App\Enums\OnboardingStep;
use App\Models\OnboardingStepEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class OnboardingService
{
    public function shouldTrackPreFirstReading(User $user): bool
    {
        return ! $user->hasEverCelebratedFirstReading()
            && ! $user->readingLogs()->exists();
    }

    public function recordStep(
        User $user,
        OnboardingStep $step,
        ?CarbonInterface $occurredAt = null,
        ?array $metadata = null
    ): void {
        OnboardingStepEvent::query()->upsert(
            values: [[
                'user_id' => $user->id,
                'step' => $step->value,
                'occurred_at' => $occurredAt ?? now(),
                'metadata' => $metadata,
            ]],
            uniqueBy: ['user_id', 'step'],
            update: ['occurred_at', 'metadata']
        );
    }

    /**
     * Dismiss onboarding and optionally schedule a reminder for tomorrow.
     */
    public function remind(int $userId): void
    {
        DB::transaction(function () use ($userId) {
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
            $this->recordStep($lockedUser, OnboardingStep::Dismissed, $now);

            if ($lockedUser->marketing_emails_opted_out_at !== null) {
                $lockedUser->save();

                return;
            }

            if ($lockedUser->onboarding_reminder_requested_at !== null) {
                $lockedUser->save();

                return;
            }

            $lockedUser->onboarding_reminder_requested_at = $now;
            $this->recordStep($lockedUser, OnboardingStep::ReminderRequested, $now);
            $lockedUser->save();
        });
    }

    /**
     * Dismiss the onboarding flow for a user.
     */
    public function dismiss(User $user): void
    {
        $dismissedAt = now();
        $shouldTrack = $this->shouldTrackPreFirstReading($user);

        $user->update([
            'onboarding_dismissed_at' => $dismissedAt,
        ]);

        if ($shouldTrack) {
            $this->recordStep($user, OnboardingStep::Dismissed, $dismissedAt);
        }
    }
}

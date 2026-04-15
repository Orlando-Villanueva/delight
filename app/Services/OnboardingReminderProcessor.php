<?php

namespace App\Services;

use App\Mail\OnboardingReminderEmail;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OnboardingReminderProcessor
{
    public const MAX_REMINDER_AGE_HOURS = 48;

    public const STATUS_FAILED = 'failed';

    public const STATUS_SENT = 'sent';

    public const STATUS_SKIPPED = 'skipped';

    public function __construct(
        private EmailService $emailService
    ) {}

    public function process(int $userId, ?CarbonInterface $referenceTime = null): string
    {
        $shouldEvaluateSend = false;
        $markerToRestore = null;
        $timezone = config('app.timezone');
        $referenceMoment = ($referenceTime ?? now())->copy();
        $now = $referenceMoment->copy()->setTimezone($timezone);

        DB::transaction(function () use ($userId, $now, $timezone, $referenceMoment, &$shouldEvaluateSend, &$markerToRestore): void {
            $user = User::query()
                ->whereKey($userId)
                ->lockForUpdate()
                ->first();

            if (! $user) {
                return;
            }

            $marker = $user->onboarding_reminder_requested_at;
            $rawMarker = $user->getRawOriginal('onboarding_reminder_requested_at');

            if (! $marker || $rawMarker === null) {
                return;
            }

            if ($marker->copy()->setTimezone($timezone)->toDateString() >= $now->toDateString()) {
                return;
            }

            if ($marker->copy()->addHours(self::MAX_REMINDER_AGE_HOURS)->lte($referenceMoment)) {
                User::query()
                    ->whereKey($user->id)
                    ->where('onboarding_reminder_requested_at', $rawMarker)
                    ->update(['onboarding_reminder_requested_at' => null]);

                return;
            }

            $isEligibleToSend = ! $user->readingLogs()->exists()
                && $user->celebrated_first_reading_at === null
                && $user->marketing_emails_opted_out_at === null;

            $clearedMarker = User::query()
                ->whereKey($user->id)
                ->where('onboarding_reminder_requested_at', $rawMarker)
                ->update(['onboarding_reminder_requested_at' => null]);

            if ($clearedMarker !== 1) {
                return;
            }

            if (! $isEligibleToSend) {
                return;
            }

            $shouldEvaluateSend = true;
            $markerToRestore = $rawMarker;
        });

        if (! $shouldEvaluateSend || $markerToRestore === null) {
            return self::STATUS_SKIPPED;
        }

        $mailSent = false;

        try {
            $sent = $this->emailService->sendWithErrorHandling(function () use ($userId, &$mailSent): void {
                $freshUser = User::query()->whereKey($userId)->first();

                if (! $freshUser) {
                    return;
                }

                $isEligibleToSend = ! $freshUser->readingLogs()->exists()
                    && $freshUser->celebrated_first_reading_at === null
                    && $freshUser->marketing_emails_opted_out_at === null;

                if (! $isEligibleToSend) {
                    return;
                }

                Mail::to($freshUser->email)->send(new OnboardingReminderEmail($freshUser));
                $mailSent = true;
            }, 'onboarding-reminder');
        } catch (Throwable $exception) {
            $this->restoreReminderMarker($userId, $markerToRestore);

            Log::error('Onboarding reminder processing failed', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            return self::STATUS_FAILED;
        }

        if (! $sent) {
            $this->restoreReminderMarker($userId, $markerToRestore);

            return self::STATUS_FAILED;
        }

        if (! $mailSent) {
            return self::STATUS_SKIPPED;
        }

        return self::STATUS_SENT;
    }

    private function restoreReminderMarker(int $userId, string $marker): void
    {
        User::query()
            ->whereKey($userId)
            ->whereNull('onboarding_reminder_requested_at')
            ->whereNull('marketing_emails_opted_out_at')
            ->whereNull('celebrated_first_reading_at')
            ->update(['onboarding_reminder_requested_at' => $marker]);
    }
}

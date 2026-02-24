<?php

namespace App\Jobs;

use App\Mail\OnboardingReminderEmail;
use App\Models\User;
use App\Services\EmailService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendOnboardingReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $userId,
        public string $expectedRequestedAtIso
    ) {}

    /**
     * Retry cadence for transient delivery failures.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [300, 900, 1800, 3600, 7200, 14400];
    }

    public function retryUntil(): Carbon
    {
        return Carbon::parse($this->expectedRequestedAtIso)->addHours(48);
    }

    public function handle(EmailService $emailService): void
    {
        $expectedRequestedAt = Carbon::parse($this->expectedRequestedAtIso);
        $shouldEvaluateSend = false;
        $markerToRestore = null;

        DB::transaction(function () use ($expectedRequestedAt, &$shouldEvaluateSend, &$markerToRestore) {
            $user = User::query()
                ->whereKey($this->userId)
                ->lockForUpdate()
                ->first();

            if (! $user) {
                return;
            }

            $marker = $user->onboarding_reminder_requested_at;

            if (! $marker || ! $marker->equalTo($expectedRequestedAt)) {
                return;
            }

            $dueAt = $marker->copy()->addDay();

            if ($dueAt->isFuture()) {
                // Jobs can execute slightly early on clock skew; re-queue until due.
                if ($this->job !== null) {
                    $this->release($dueAt);
                }

                return;
            }

            $isEligibleToSend = ! $user->readingLogs()->exists()
                && $user->celebrated_first_reading_at === null
                && $user->marketing_emails_opted_out_at === null;

            $clearedMarker = User::query()
                ->whereKey($user->id)
                ->where('onboarding_reminder_requested_at', $marker->toDateTimeString())
                ->update(['onboarding_reminder_requested_at' => null]);

            if ($clearedMarker !== 1) {
                return;
            }

            if (! $isEligibleToSend) {
                return;
            }

            $shouldEvaluateSend = true;
            $markerToRestore = $marker->copy();
        });

        if (! $shouldEvaluateSend || $markerToRestore === null) {
            return;
        }

        try {
            $sent = $emailService->sendWithErrorHandling(function () {
                $freshUser = User::query()
                    ->whereKey($this->userId)
                    ->first();

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
            }, 'onboarding-reminder');
        } catch (Throwable $exception) {
            $this->restoreReminderMarker($markerToRestore);

            throw $exception;
        }

        if (! $sent) {
            // Restore marker for retries when transport failed after marker clear.
            $this->restoreReminderMarker($markerToRestore);

            throw new RuntimeException("Failed to send onboarding reminder for user {$this->userId}");
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('Onboarding reminder job failed', [
            'user_id' => $this->userId,
            'expected_requested_at' => $this->expectedRequestedAtIso,
            'error' => $e->getMessage(),
        ]);
    }

    private function restoreReminderMarker(Carbon $marker): void
    {
        User::query()
            ->whereKey($this->userId)
            ->whereNull('onboarding_reminder_requested_at')
            ->whereNull('marketing_emails_opted_out_at')
            ->whereNull('celebrated_first_reading_at')
            ->update(['onboarding_reminder_requested_at' => $marker->toDateTimeString()]);
    }
}

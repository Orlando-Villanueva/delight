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
        DB::transaction(function () use ($emailService) {
            $expectedRequestedAt = Carbon::parse($this->expectedRequestedAtIso);

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

            if ($marker->copy()->addDay()->isFuture()) {
                return;
            }

            $isEligibleToSend = ! $user->readingLogs()->exists()
                && $user->celebrated_first_reading_at === null
                && $user->marketing_emails_opted_out_at === null;

            if (! $isEligibleToSend) {
                User::query()
                    ->whereKey($user->id)
                    ->where('onboarding_reminder_requested_at', $marker->toDateTimeString())
                    ->update(['onboarding_reminder_requested_at' => null]);

                return;
            }

            $sent = $emailService->sendWithErrorHandling(function () use ($user) {
                Mail::to($user->email)->send(new OnboardingReminderEmail($user));
            }, 'onboarding-reminder');

            if (! $sent) {
                throw new RuntimeException("Failed to send onboarding reminder for user {$user->id}");
            }

            User::query()
                ->whereKey($user->id)
                ->where('onboarding_reminder_requested_at', $marker->toDateTimeString())
                ->update(['onboarding_reminder_requested_at' => null]);
        });
    }

    public function failed(Throwable $e): void
    {
        Log::error('Onboarding reminder job failed', [
            'user_id' => $this->userId,
            'expected_requested_at' => $this->expectedRequestedAtIso,
            'error' => $e->getMessage(),
        ]);
    }
}

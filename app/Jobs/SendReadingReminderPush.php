<?php

namespace App\Jobs;

use App\Models\PushReminderDelivery;
use App\Models\User;
use App\Notifications\ReadingReminderPushNotification;
use App\Services\ReadingReminderEligibilityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class SendReadingReminderPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        private int $deliveryId
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('reading-reminder-delivery-'.$this->deliveryId))->releaseAfter(30)->expireAfter(300)];
    }

    public function handle(?ReadingReminderEligibilityService $eligibility = null): void
    {
        $eligibility ??= app(ReadingReminderEligibilityService::class);

        $delivery = PushReminderDelivery::query()->with('user')->find($this->deliveryId);

        if (! $delivery || $delivery->sent_at || $delivery->skipped_at || $delivery->failed_at) {
            return;
        }

        $user = $delivery->user;
        $referenceTime = now();

        if (! $user
            || $delivery->reminder_date->toDateString() !== $eligibility->reminderDateFor($user, $referenceTime)
            || ! $eligibility->isEligible($user, $delivery->reminder_type, $referenceTime)) {
            $delivery->forceFill(['skipped_at' => now()])->save();

            return;
        }

        $user->notify(new ReadingReminderPushNotification(
            $delivery->reminder_type,
            $delivery->reminder_date->toDateString(),
            $this->targetUrlFor($user)
        ));

        $delivery->forceFill(['sent_at' => now()])->save();
    }

    public function failed(Throwable $exception): void
    {
        $delivery = PushReminderDelivery::query()->find($this->deliveryId);

        if (! $delivery || $delivery->sent_at || $delivery->skipped_at || $delivery->failed_at) {
            return;
        }

        $delivery->forceFill([
            'failed_at' => now(),
            'failure_reason' => str($exception->getMessage())->limit(255, '')->toString(),
        ])->save();
    }

    private function targetUrlFor(User $user): string
    {
        $subscription = $user->readingPlanSubscriptions()
            ->active()
            ->with('plan')
            ->first();

        if ($subscription && $subscription->plan) {
            return route('plans.today', $subscription->plan);
        }

        return route('logs.create');
    }
}

<?php

namespace App\Jobs;

use App\Models\PushReminderDelivery;
use App\Models\User;
use App\Notifications\ReadingReminderPushNotification;
use App\Services\ReadingReminderEligibilityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendReadingReminderPush implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private int $deliveryId
    ) {}

    public function handle(?ReadingReminderEligibilityService $eligibility = null): void
    {
        $eligibility ??= app(ReadingReminderEligibilityService::class);

        $delivery = PushReminderDelivery::query()->with('user')->find($this->deliveryId);

        if (! $delivery || $delivery->sent_at || $delivery->skipped_at || $delivery->failed_at) {
            return;
        }

        $user = $delivery->user;

        if (! $user || ! $eligibility->isEligible($user, $delivery->reminder_type, now())) {
            $delivery->forceFill(['skipped_at' => now()])->save();

            return;
        }

        try {
            $user->notify(new ReadingReminderPushNotification(
                $delivery->reminder_type,
                $delivery->reminder_date->toDateString(),
                $this->targetUrlFor($user)
            ));

            $delivery->forceFill(['sent_at' => now()])->save();
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'failed_at' => now(),
                'failure_reason' => str($exception->getMessage())->limit(255)->toString(),
            ])->save();
        }
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

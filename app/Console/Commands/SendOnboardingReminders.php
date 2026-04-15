<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\OnboardingReminderProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendOnboardingReminders extends Command
{
    protected $signature = 'onboarding:send-reminders';

    protected $description = 'Send due onboarding reminder emails';

    public function handle(OnboardingReminderProcessor $processor): int
    {
        $summary = [
            OnboardingReminderProcessor::STATUS_SENT => 0,
            OnboardingReminderProcessor::STATUS_SKIPPED => 0,
            OnboardingReminderProcessor::STATUS_FAILED => 0,
        ];

        User::query()
            ->whereNotNull('onboarding_reminder_requested_at')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($processor, &$summary): void {
                foreach ($users as $user) {
                    $status = $processor->process($user->id);
                    $summary[$status]++;
                }
            });

        $message = sprintf(
            'Onboarding reminders processed: %d sent, %d skipped, %d failed.',
            $summary[OnboardingReminderProcessor::STATUS_SENT],
            $summary[OnboardingReminderProcessor::STATUS_SKIPPED],
            $summary[OnboardingReminderProcessor::STATUS_FAILED]
        );

        $this->info($message);

        Log::info($message, $summary);

        return self::SUCCESS;
    }
}

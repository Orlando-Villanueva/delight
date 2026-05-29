<?php

namespace App\Console\Commands;

use App\Jobs\SendReadingReminderPush;
use App\Models\PushReminderDelivery;
use App\Models\User;
use App\Services\ReadingReminderEligibilityService;
use Illuminate\Console\Command;

class DispatchReadingReminderPushes extends Command
{
    protected $signature = 'push:dispatch-reading-reminders';

    protected $description = 'Queue due web push reading reminders';

    public function handle(ReadingReminderEligibilityService $eligibility): int
    {
        $dueCount = 0;
        $skippedCount = 0;
        $types = [
            PushReminderDelivery::TYPE_DAILY_READING,
            PushReminderDelivery::TYPE_STREAK_RISK,
        ];

        User::query()
            ->whereHas('pushSubscriptions')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($eligibility, $types, &$dueCount, &$skippedCount): void {
                foreach ($users as $user) {
                    $freshUser = User::query()->find($user->id);

                    if (! $freshUser) {
                        continue;
                    }

                    foreach ($types as $type) {
                        if (! $eligibility->isEligible($freshUser, $type, now())) {
                            continue;
                        }

                        $reminderDate = $eligibility->reminderDateFor($freshUser, now());
                        $existingDelivery = PushReminderDelivery::query()
                            ->where('user_id', $freshUser->id)
                            ->where('reminder_type', $type)
                            ->whereDate('reminder_date', $reminderDate)
                            ->first();

                        if ($existingDelivery) {
                            $skippedCount++;

                            continue;
                        }

                        $delivery = PushReminderDelivery::query()->create([
                            'user_id' => $freshUser->id,
                            'reminder_type' => $type,
                            'reminder_date' => $reminderDate,
                            'scheduled_for_at' => now(),
                        ]);

                        SendReadingReminderPush::dispatch($delivery->id);
                        $dueCount++;
                    }
                }
            });

        $this->info("Reading reminder pushes queued: {$dueCount} due, {$skippedCount} skipped.");

        return self::SUCCESS;
    }
}

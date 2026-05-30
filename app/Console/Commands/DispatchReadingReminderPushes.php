<?php

namespace App\Console\Commands;

use App\Jobs\SendReadingReminderPush;
use App\Models\PushReminderDelivery;
use App\Models\User;
use App\Services\ReadingReminderEligibilityService;
use Carbon\CarbonImmutable;
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
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($eligibility, $types, &$dueCount, &$skippedCount): void {
                foreach ($users as $user) {
                    $referenceTime = now();

                    foreach ($types as $type) {
                        if (! $eligibility->isEligible($user, $type, $referenceTime)) {
                            continue;
                        }

                        $reminderDate = $eligibility->reminderDateFor($user, $referenceTime);
                        $reminderDateKey = CarbonImmutable::parse($reminderDate)->startOfDay();
                        $delivery = PushReminderDelivery::query()->createOrFirst([
                            'user_id' => $user->id,
                            'reminder_type' => $type,
                            'reminder_date' => $reminderDateKey,
                        ], [
                            'scheduled_for_at' => $referenceTime,
                        ]);

                        if (! $delivery->wasRecentlyCreated) {
                            $skippedCount++;

                            continue;
                        }

                        SendReadingReminderPush::dispatch($delivery->id);
                        $dueCount++;
                    }
                }
            });

        $this->info("Reading reminder pushes queued: {$dueCount} due, {$skippedCount} skipped.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\ReadingReminderType;
use App\Jobs\SendNativeReadingReminderPushBatch;
use App\Models\NativePushRegistration;
use App\Models\NativePushReminderDelivery;
use App\Models\User;
use App\Services\ReadingReminderConditionService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class DispatchNativeReadingReminderPushes extends Command
{
    private const int PENDING_RETRY_AFTER_MINUTES = 15;

    protected $signature = 'push:dispatch-native-reading-reminders';

    protected $description = 'Queue due native reading reminder pushes';

    public function handle(ReadingReminderConditionService $conditions): int
    {
        $queuedCount = 0;
        $skippedCount = 0;
        $batch = [];
        $types = [
            ReadingReminderType::DailyReading->value,
            ReadingReminderType::StreakRisk->value,
        ];

        NativePushRegistration::query()
            ->whereHas('reminderPreference', fn ($query) => $query->where('enabled', true))
            ->with(['reminderPreference', 'personalAccessToken.tokenable'])
            ->orderBy('id')
            ->chunkById(100, function ($registrations) use (
                $conditions,
                $types,
                &$batch,
                &$queuedCount,
                &$skippedCount,
            ): void {
                foreach ($registrations as $registration) {
                    $user = $registration->personalAccessToken?->tokenable;

                    if (! $user instanceof User) {
                        continue;
                    }

                    $referenceTime = now();

                    foreach ($types as $type) {
                        if (! $conditions->isEligible($user, $type, $referenceTime)) {
                            continue;
                        }

                        $reminderDate = $conditions->reminderDateFor($user, $referenceTime);
                        $delivery = NativePushReminderDelivery::query()->createOrFirst([
                            'native_push_registration_id' => $registration->id,
                            'reminder_type' => $type,
                            'reminder_date' => CarbonImmutable::parse($reminderDate)->startOfDay(),
                        ], [
                            'scheduled_for_at' => $referenceTime,
                            'token_hash' => $registration->token_hash,
                        ]);

                        if (! $delivery->wasRecentlyCreated) {
                            $requeued = NativePushReminderDelivery::query()
                                ->whereKey($delivery->id)
                                ->whereNull('sent_at')
                                ->whereNull('failed_at')
                                ->where(function ($query) use ($registration, $referenceTime): void {
                                    $query->whereNotNull('skipped_at')
                                        ->orWhere('token_hash', '!=', $registration->token_hash)
                                        ->orWhere(
                                            'updated_at',
                                            '<=',
                                            $referenceTime->copy()->subMinutes(self::PENDING_RETRY_AFTER_MINUTES),
                                        );
                                })
                                ->update([
                                    'skipped_at' => null,
                                    'scheduled_for_at' => $referenceTime,
                                    'token_hash' => $registration->token_hash,
                                ]);

                            if (! $requeued) {
                                $skippedCount++;

                                continue;
                            }
                        }

                        $batch[] = $delivery->id;
                        $queuedCount++;

                        if (count($batch) === 100) {
                            SendNativeReadingReminderPushBatch::dispatch($batch);
                            $batch = [];
                        }
                    }
                }
            });

        if ($batch !== []) {
            SendNativeReadingReminderPushBatch::dispatch($batch);
        }

        $this->info("Native reading reminder pushes queued: {$queuedCount} due, {$skippedCount} skipped.");

        return self::SUCCESS;
    }
}

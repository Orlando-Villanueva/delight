<?php

namespace App\Console\Commands;

use App\Enums\ReadingReminderType;
use App\Jobs\SendNativeReadingReminderPushBatch;
use App\Models\NativePushReminderDelivery;
use Illuminate\Console\Command;
use Throwable;

class TestNativeReadingReminderDelivery extends Command
{
    private const string EXPO_REJECTION = 'Expo rejected the notification.';

    protected $signature = 'push:test-native-reading-reminder-delivery {deliveryId : The ID of one failed daily reminder delivery}';

    protected $description = 'Retry one Expo-rejected daily reminder delivery for device testing';

    public function handle(): int
    {
        $delivery = NativePushReminderDelivery::query()->find($this->argument('deliveryId'));

        if (! $delivery) {
            $this->error('Native reminder delivery not found.');

            return self::FAILURE;
        }

        if ($delivery->reminder_type !== ReadingReminderType::DailyReading->value
            || ! $delivery->failed_at
            || $delivery->sent_at
            || $delivery->skipped_at
            || $delivery->failure_reason !== self::EXPO_REJECTION) {
            $this->error('Only one failed Expo-rejected daily reminder can be retried.');

            return self::FAILURE;
        }

        if ($delivery->expo_ticket_error_code !== null || $delivery->expo_ticket_error_message !== null) {
            $this->error('Ticket diagnostics already exist; refusing to overwrite them.');

            return self::FAILURE;
        }

        $updated = NativePushReminderDelivery::query()
            ->whereKey($delivery->getKey())
            ->where('reminder_type', ReadingReminderType::DailyReading->value)
            ->whereNotNull('failed_at')
            ->whereNull('sent_at')
            ->whereNull('skipped_at')
            ->where('failure_reason', self::EXPO_REJECTION)
            ->whereNull('expo_ticket_error_code')
            ->whereNull('expo_ticket_error_message')
            ->update([
                'failed_at' => null,
                'failure_reason' => null,
                'scheduled_for_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            $this->error('The delivery changed before it could be retried.');

            return self::FAILURE;
        }

        try {
            SendNativeReadingReminderPushBatch::dispatchSync([(int) $delivery->getKey()]);
        } catch (Throwable $exception) {
            report($exception);

            NativePushReminderDelivery::query()
                ->whereKey($delivery->getKey())
                ->whereNull('sent_at')
                ->whereNull('skipped_at')
                ->whereNull('failed_at')
                ->update([
                    'failed_at' => now(),
                    'failure_reason' => 'Expo push service request failed.',
                    'expo_retry_at' => null,
                    'updated_at' => now(),
                ]);

            $this->error('The immediate Expo retry failed before a ticket response was saved.');

            return self::FAILURE;
        }

        $this->info("Retry attempt completed for native reminder delivery {$delivery->getKey()}.");

        return self::SUCCESS;
    }
}

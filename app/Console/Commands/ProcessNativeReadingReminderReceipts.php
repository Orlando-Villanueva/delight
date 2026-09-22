<?php

namespace App\Console\Commands;

use App\Enums\NativePushReceiptStatus;
use App\Jobs\SendNativeReadingReminderPushBatch;
use App\Models\NativePushRegistration;
use App\Models\NativePushReminderDelivery;
use App\Services\ExpoPushService;
use Illuminate\Console\Command;

class ProcessNativeReadingReminderReceipts extends Command
{
    private const int RECEIPT_RETENTION_HOURS = 24;

    private const int MAX_RATE_LIMIT_RETRIES = 3;

    private const int BASE_RATE_LIMIT_RETRY_MINUTES = 15;

    private const int RETRY_QUEUE_LEASE_MINUTES = 15;

    protected $signature = 'push:process-native-reading-reminder-receipts';

    protected $description = 'Process Expo receipts for native reading reminders';

    public function handle(ExpoPushService $expo): int
    {
        $processedCount = 0;
        $unavailableCount = 0;
        $retryQueuedCount = 0;
        $now = now();
        $cutoff = $now->copy()->subMinutes((int) config('services.expo.receipt_delay_minutes', 15));
        $receiptExpiredBefore = $now->copy()->subHours(self::RECEIPT_RETENTION_HOURS);

        NativePushReminderDelivery::query()
            ->where('expo_receipt_status', NativePushReceiptStatus::Pending->value)
            ->whereNotNull('expo_ticket_id')
            ->whereNotNull('sent_at')
            ->whereNull('expo_receipt_checked_at')
            ->where('sent_at', '<=', $receiptExpiredBefore)
            ->orderBy('id')
            ->chunkById(1000, function ($deliveries) use (&$unavailableCount, $now): void {
                $unavailableCount += NativePushReminderDelivery::query()
                    ->whereKey($deliveries->modelKeys())
                    ->where('expo_receipt_status', NativePushReceiptStatus::Pending->value)
                    ->whereNull('expo_receipt_checked_at')
                    ->update([
                        'expo_receipt_status' => NativePushReceiptStatus::Unavailable->value,
                        'expo_receipt_error' => 'Expo receipt unavailable after the 24-hour retention period.',
                        'expo_receipt_checked_at' => $now,
                        'updated_at' => $now,
                    ]);
            });

        NativePushReminderDelivery::query()
            ->where('expo_receipt_status', NativePushReceiptStatus::Pending->value)
            ->whereNotNull('expo_ticket_id')
            ->whereNotNull('sent_at')
            ->whereNull('expo_receipt_checked_at')
            ->where('sent_at', '<=', $cutoff)
            ->where('sent_at', '>', $receiptExpiredBefore)
            ->orderBy('id')
            ->chunkById(1000, function ($deliveries) use ($expo, &$processedCount): void {
                $ticketIds = $deliveries->pluck('expo_ticket_id')->filter()->values()->all();
                $receipts = $expo->receipts($ticketIds);

                foreach ($deliveries as $delivery) {
                    $ticketId = $delivery->expo_ticket_id;
                    $receipt = is_string($ticketId) ? ($receipts[$ticketId] ?? null) : null;

                    if (! is_array($receipt)) {
                        continue;
                    }

                    if (($receipt['status'] ?? null) === 'ok') {
                        $delivery->forceFill([
                            'expo_receipt_status' => NativePushReceiptStatus::Ok,
                            'expo_receipt_error' => null,
                            'expo_receipt_checked_at' => now(),
                            'expo_retry_at' => null,
                        ])->save();
                        $processedCount++;

                        continue;
                    }

                    $error = data_get($receipt, 'details.error');
                    $errorCode = is_string($error) ? $error : 'Expo rejected the notification.';

                    if ($errorCode === 'MessageRateExceeded'
                        && $delivery->expo_retry_count < self::MAX_RATE_LIMIT_RETRIES) {
                        $retryDelayMinutes = self::BASE_RATE_LIMIT_RETRY_MINUTES * (2 ** $delivery->expo_retry_count);
                        $checkedAt = now();

                        $delivery->forceFill([
                            'expo_receipt_status' => NativePushReceiptStatus::RetryPending,
                            'expo_receipt_error' => $errorCode,
                            'expo_receipt_checked_at' => $checkedAt,
                            'expo_retry_count' => $delivery->expo_retry_count + 1,
                            'expo_retry_at' => $checkedAt->copy()->addMinutes($retryDelayMinutes),
                            'sent_at' => null,
                        ])->save();
                        $processedCount++;

                        continue;
                    }

                    $delivery->forceFill([
                        'expo_receipt_status' => NativePushReceiptStatus::Error,
                        'expo_receipt_error' => str($errorCode)->limit(255, '')->toString(),
                        'expo_receipt_checked_at' => now(),
                        'expo_retry_at' => null,
                    ])->save();
                    $processedCount++;

                    if ($errorCode === 'DeviceNotRegistered') {
                        NativePushRegistration::query()
                            ->whereKey($delivery->native_push_registration_id)
                            ->where('token_hash', $delivery->token_hash)
                            ->delete();
                    }
                }
            });

        NativePushReminderDelivery::query()
            ->where('expo_receipt_status', NativePushReceiptStatus::RetryPending->value)
            ->whereNotNull('expo_retry_at')
            ->where('expo_retry_at', '<=', now())
            ->whereNull('sent_at')
            ->whereNull('skipped_at')
            ->whereNull('failed_at')
            ->orderBy('id')
            ->chunkById(100, function ($deliveries) use (&$retryQueuedCount): void {
                $deliveryIds = $deliveries->modelKeys();
                $retryAt = now();
                $retryableIds = NativePushReminderDelivery::query()
                    ->whereKey($deliveryIds)
                    ->where('expo_receipt_status', NativePushReceiptStatus::RetryPending->value)
                    ->where('expo_retry_at', '<=', $retryAt)
                    ->whereNull('sent_at')
                    ->whereNull('skipped_at')
                    ->whereNull('failed_at')
                    ->pluck('id')
                    ->all();

                if ($retryableIds === []) {
                    return;
                }

                $leaseUntil = $retryAt->copy()->addMinutes(self::RETRY_QUEUE_LEASE_MINUTES);
                NativePushReminderDelivery::query()
                    ->whereKey($retryableIds)
                    ->update([
                        'expo_retry_at' => $leaseUntil,
                        'scheduled_for_at' => $retryAt,
                        'updated_at' => $retryAt,
                    ]);

                SendNativeReadingReminderPushBatch::dispatch($retryableIds);
                $retryQueuedCount += count($retryableIds);
            });

        $this->info("Native reading reminder receipts processed: {$processedCount}; unavailable after retention: {$unavailableCount}; rate-limited retries queued: {$retryQueuedCount}.");

        return self::SUCCESS;
    }
}

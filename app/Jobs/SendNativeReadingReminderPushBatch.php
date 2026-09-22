<?php

namespace App\Jobs;

use App\Enums\NativePushReceiptStatus;
use App\Enums\ReadingReminderType;
use App\Models\NativePushRegistration;
use App\Models\NativePushReminderDelivery;
use App\Models\User;
use App\Services\ExpoPushService;
use App\Services\ReadingReminderConditionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use RuntimeException;
use Throwable;

class SendNativeReadingReminderPushBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<int, int>  $deliveryIds
     */
    public function __construct(private array $deliveryIds) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('native-reading-reminder-batch-'.sha1(implode(',', $this->deliveryIds))))
                ->releaseAfter(30)
                ->expireAfter(300),
        ];
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(
        ExpoPushService $expo,
        ReadingReminderConditionService $conditions,
    ): void {
        $deliveries = NativePushReminderDelivery::query()
            ->with([
                'nativePushRegistration.reminderPreference',
                'nativePushRegistration.personalAccessToken.tokenable',
            ])
            ->whereIn('id', $this->deliveryIds)
            ->get()
            ->keyBy('id');
        $messages = [];
        $eligibleDeliveries = [];

        foreach ($this->deliveryIds as $deliveryId) {
            /** @var NativePushReminderDelivery|null $delivery */
            $delivery = $deliveries->get($deliveryId);

            if (! $delivery || $delivery->sent_at || $delivery->skipped_at || $delivery->failed_at) {
                continue;
            }

            $registration = $delivery->nativePushRegistration;
            $session = $registration?->personalAccessToken;
            $user = $session?->tokenable;

            if (! $user instanceof User || ! $this->isDeliverable($delivery, $user, $session, $conditions)) {
                $delivery->forceFill([
                    'skipped_at' => now(),
                    'expo_retry_at' => null,
                ])->save();

                continue;
            }

            $messages[] = $this->payloadFor($delivery, $registration->expo_push_token);
            $eligibleDeliveries[] = $delivery;
        }

        if ($messages === []) {
            return;
        }

        $tickets = $expo->send($messages);

        if (count($tickets) !== count($eligibleDeliveries)) {
            throw new RuntimeException('Expo push service returned an unexpected ticket count.');
        }

        foreach ($eligibleDeliveries as $index => $delivery) {
            $ticket = $tickets[$index] ?? null;
            $ticketError = data_get($ticket, 'details.error');

            if (! is_array($ticket) || ($ticket['status'] ?? null) !== 'ok' || ! is_string($ticket['id'] ?? null)) {
                $delivery->forceFill([
                    'failed_at' => now(),
                    'failure_reason' => 'Expo rejected the notification.',
                    'expo_retry_at' => null,
                ])->save();

                if ($ticketError === 'DeviceNotRegistered') {
                    NativePushRegistration::query()
                        ->whereKey($delivery->native_push_registration_id)
                        ->where('token_hash', $delivery->token_hash)
                        ->delete();
                }

                continue;
            }

            $delivery->forceFill([
                'expo_ticket_id' => $ticket['id'],
                'expo_receipt_status' => NativePushReceiptStatus::Pending,
                'expo_receipt_error' => null,
                'expo_receipt_checked_at' => null,
                'expo_retry_at' => null,
                'sent_at' => now(),
            ])->save();
        }
    }

    public function failed(Throwable $exception): void
    {
        NativePushReminderDelivery::query()
            ->whereIn('id', $this->deliveryIds)
            ->whereNull('sent_at')
            ->whereNull('skipped_at')
            ->whereNull('failed_at')
            ->update([
                'failed_at' => now(),
                'failure_reason' => 'Expo push service request failed.',
                'expo_retry_at' => null,
                'updated_at' => now(),
            ]);
    }

    private function isDeliverable(
        NativePushReminderDelivery $delivery,
        User $user,
        mixed $session,
        ReadingReminderConditionService $conditions,
    ): bool {
        $registration = $delivery->nativePushRegistration;

        if (! $registration
            || ! $registration->reminderPreference?->enabled
            || ! hash_equals($delivery->token_hash, $registration->token_hash)
            || ($session?->expires_at && $session->expires_at->isPast())) {
            return false;
        }

        $referenceTime = now();

        return $delivery->reminder_date->toDateString() === $conditions->reminderDateFor($user, $referenceTime)
            && $conditions->isEligible($user, $delivery->reminder_type, $referenceTime);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(NativePushReminderDelivery $delivery, string $expoPushToken): array
    {
        $isStreakRisk = $delivery->reminder_type === ReadingReminderType::StreakRisk->value;

        return [
            'to' => $expoPushToken,
            'title' => $isStreakRisk ? 'Your reading streak is at risk' : "Time for today's reading",
            'body' => $isStreakRisk
                ? 'Open Delight and read one chapter to keep your streak going.'
                : 'Open Delight and log one chapter when you are ready.',
            'sound' => 'default',
            'channelId' => 'reading-reminders',
            'data' => [
                'reminder_type' => $delivery->reminder_type,
                'reminder_date' => $delivery->reminder_date->toDateString(),
                'target' => 'reading-log',
            ],
        ];
    }
}

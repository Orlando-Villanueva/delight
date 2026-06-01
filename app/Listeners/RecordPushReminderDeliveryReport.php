<?php

namespace App\Listeners;

use App\Models\PushReminderDelivery;
use App\Models\PushReminderDeliveryReport;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\Events\NotificationFailed;
use NotificationChannels\WebPush\Events\NotificationSent;

class RecordPushReminderDeliveryReport
{
    public function handle(NotificationSent|NotificationFailed $event): void
    {
        $message = $event->message->toArray();
        $data = is_array($message['data'] ?? null) ? $message['data'] : [];
        $userId = $this->userIdFromSubscription($event);
        $delivery = $this->deliveryFrom($data, $userId);
        $endpoint = $event->subscription->endpoint;
        $response = $event->report->getResponse();
        $responseBody = $response?->getBody()?->__toString();

        PushReminderDeliveryReport::query()->create([
            'push_reminder_delivery_id' => $delivery?->id,
            'user_id' => $delivery?->user_id ?? $userId,
            'reminder_type' => $delivery?->reminder_type ?? $this->nullableString($data['reminderType'] ?? null),
            'reminder_date' => $delivery?->reminder_date ?? $this->nullableString($data['reminderDate'] ?? null),
            'push_subscription_id' => $event->subscription->id,
            'endpoint_host' => parse_url($endpoint, PHP_URL_HOST) ?: null,
            'endpoint_hash' => hash('sha256', $endpoint),
            'status' => $event instanceof NotificationSent
                ? PushReminderDeliveryReport::STATUS_SENT
                : PushReminderDeliveryReport::STATUS_FAILED,
            'http_status' => $response?->getStatusCode(),
            'expired' => $event->report->isSubscriptionExpired(),
            'failure_reason' => $event instanceof NotificationFailed
                ? Str::limit($event->report->getReason(), 255, '')
                : null,
            'response_body' => $responseBody !== null && $responseBody !== ''
                ? Str::limit($responseBody, 4096, '')
                : null,
            'reported_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function deliveryFrom(array $data, ?int $userId): ?PushReminderDelivery
    {
        $deliveryId = $data['deliveryId'] ?? null;

        if (is_int($deliveryId) || is_string($deliveryId)) {
            return PushReminderDelivery::query()->find($deliveryId);
        }

        $reminderType = $this->nullableString($data['reminderType'] ?? null);
        $reminderDate = $this->nullableString($data['reminderDate'] ?? null);

        if ($userId === null || $reminderType === null || $reminderDate === null) {
            return null;
        }

        return PushReminderDelivery::query()
            ->where('user_id', $userId)
            ->where('reminder_type', $reminderType)
            ->whereDate('reminder_date', $reminderDate)
            ->first();
    }

    private function userIdFromSubscription(NotificationSent|NotificationFailed $event): ?int
    {
        if ($event->subscription->subscribable_id === null) {
            return null;
        }

        return (int) $event->subscription->subscribable_id;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}

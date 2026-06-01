<?php

use App\Listeners\RecordPushReminderDeliveryReport;
use App\Models\PushReminderDelivery;
use App\Models\PushReminderDeliveryReport;
use App\Models\User;
use App\Notifications\ReadingReminderPushNotification;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Minishlink\WebPush\MessageSentReport;
use NotificationChannels\WebPush\Events\NotificationFailed;
use NotificationChannels\WebPush\Events\NotificationSent;
use NotificationChannels\WebPush\PushSubscription;
use NotificationChannels\WebPush\WebPushMessage;

it('records successful webpush reports for a reminder delivery subscription', function () {
    $user = User::factory()->create();
    $subscription = pushSubscriptionFor($user, 'https://fcm.googleapis.com/fcm/send/success-token');
    $delivery = pushDeliveryFor($user);

    $event = sentWebPushEvent($subscription, reminderMessageFor($user, $delivery));

    (new RecordPushReminderDeliveryReport)->handle($event);

    $report = PushReminderDeliveryReport::query()->sole();

    expect($report->push_reminder_delivery_id)->toBe($delivery->id)
        ->and($report->user_id)->toBe($user->id)
        ->and($report->reminder_type)->toBe(PushReminderDelivery::TYPE_DAILY_READING)
        ->and($report->reminder_date->toDateString())->toBe('2026-06-01')
        ->and($report->push_subscription_id)->toBe($subscription->id)
        ->and($report->endpoint_host)->toBe('fcm.googleapis.com')
        ->and($report->endpoint_hash)->toBe(hash('sha256', $subscription->endpoint))
        ->and($report->status)->toBe(PushReminderDeliveryReport::STATUS_SENT)
        ->and($report->http_status)->toBe(201)
        ->and($report->expired)->toBeFalse()
        ->and($report->failure_reason)->toBeNull()
        ->and($report->reported_at)->not->toBeNull();
});

it('records failed webpush reports without deleting non-expired subscription evidence', function () {
    $user = User::factory()->create();
    $subscription = pushSubscriptionFor($user, 'https://fcm.googleapis.com/fcm/send/failure-token');
    $delivery = pushDeliveryFor($user);

    $event = failedWebPushEvent(
        $subscription,
        reminderMessageFor($user, $delivery),
        403,
        'VAPID credentials rejected',
        'Client error: 403 Forbidden',
    );

    (new RecordPushReminderDeliveryReport)->handle($event);

    $report = PushReminderDeliveryReport::query()->sole();

    expect($report->push_reminder_delivery_id)->toBe($delivery->id)
        ->and($report->push_subscription_id)->toBe($subscription->id)
        ->and($report->status)->toBe(PushReminderDeliveryReport::STATUS_FAILED)
        ->and($report->http_status)->toBe(403)
        ->and($report->expired)->toBeFalse()
        ->and($report->failure_reason)->toBe('Client error: 403 Forbidden')
        ->and($report->response_body)->toBe('VAPID credentials rejected');

    expect($user->pushSubscriptions()->whereKey($subscription->id)->exists())->toBeTrue();
});

it('records failed webpush reports without an HTTP response', function () {
    $user = User::factory()->create();
    $subscription = pushSubscriptionFor($user, 'https://fcm.googleapis.com/fcm/send/transport-failure-token');
    $delivery = pushDeliveryFor($user);

    $event = failedWebPushEventWithoutResponse(
        $subscription,
        reminderMessageFor($user, $delivery),
        'cURL error 28: Operation timed out',
    );

    (new RecordPushReminderDeliveryReport)->handle($event);

    $report = PushReminderDeliveryReport::query()->sole();

    expect($report->push_reminder_delivery_id)->toBe($delivery->id)
        ->and($report->push_subscription_id)->toBe($subscription->id)
        ->and($report->status)->toBe(PushReminderDeliveryReport::STATUS_FAILED)
        ->and($report->http_status)->toBeNull()
        ->and($report->expired)->toBeFalse()
        ->and($report->failure_reason)->toBe('cURL error 28: Operation timed out')
        ->and($report->response_body)->toBeNull();
});

it('records expired endpoint reports after the subscription row has already been deleted', function () {
    $user = User::factory()->create();
    $subscription = pushSubscriptionFor($user, 'https://fcm.googleapis.com/fcm/send/expired-token');
    $subscriptionId = $subscription->id;
    $delivery = pushDeliveryFor($user);

    $subscription->delete();

    $event = failedWebPushEvent(
        $subscription,
        reminderMessageFor($user, $delivery),
        410,
        'push subscription expired',
        'Client error: 410 Gone',
    );

    (new RecordPushReminderDeliveryReport)->handle($event);

    $report = PushReminderDeliveryReport::query()->sole();

    expect($report->push_reminder_delivery_id)->toBe($delivery->id)
        ->and($report->push_subscription_id)->toBe($subscriptionId)
        ->and($report->status)->toBe(PushReminderDeliveryReport::STATUS_FAILED)
        ->and($report->http_status)->toBe(410)
        ->and($report->expired)->toBeTrue()
        ->and($report->endpoint_host)->toBe('fcm.googleapis.com');
});

it('links reports by user and reminder metadata when the payload has no delivery id', function () {
    $user = User::factory()->create();
    $subscription = pushSubscriptionFor($user, 'https://fcm.googleapis.com/fcm/send/fallback-token');
    $delivery = pushDeliveryFor($user);
    $notification = new ReadingReminderPushNotification(
        $delivery->reminder_type,
        $delivery->reminder_date->toDateString(),
        'https://example.com/logs',
    );
    $event = sentWebPushEvent($subscription, $notification->toWebPush($user, $notification));

    (new RecordPushReminderDeliveryReport)->handle($event);

    $report = PushReminderDeliveryReport::query()->sole();

    expect($report->push_reminder_delivery_id)->toBe($delivery->id)
        ->and($report->user_id)->toBe($user->id)
        ->and($report->reminder_type)->toBe(PushReminderDelivery::TYPE_DAILY_READING)
        ->and($report->reminder_date->toDateString())->toBe('2026-06-01');
});

it('includes reminder delivery metadata in the webpush payload', function () {
    $user = User::factory()->create();
    $delivery = pushDeliveryFor($user);

    $message = reminderMessageFor($user, $delivery)->toArray();

    expect($message['data']['deliveryId'])->toBe($delivery->id)
        ->and($message['data']['reminderType'])->toBe(PushReminderDelivery::TYPE_DAILY_READING)
        ->and($message['data']['reminderDate'])->toBe('2026-06-01');
});

function pushSubscriptionFor(User $user, string $endpoint): PushSubscription
{
    return $user->updatePushSubscription($endpoint, 'public-key', 'auth-token', 'aes128gcm');
}

function pushDeliveryFor(User $user): PushReminderDelivery
{
    return PushReminderDelivery::factory()->for($user)->create([
        'reminder_type' => PushReminderDelivery::TYPE_DAILY_READING,
        'reminder_date' => '2026-06-01',
        'scheduled_for_at' => '2026-06-01 09:00:08',
    ]);
}

function reminderMessageFor(User $user, PushReminderDelivery $delivery): WebPushMessage
{
    $notification = new ReadingReminderPushNotification(
        $delivery->reminder_type,
        $delivery->reminder_date->toDateString(),
        'https://example.com/logs',
        $delivery->id,
    );

    return $notification->toWebPush($user, $notification);
}

function sentWebPushEvent(PushSubscription $subscription, WebPushMessage $message): NotificationSent
{
    return new NotificationSent(
        new MessageSentReport(
            new Request('POST', $subscription->endpoint),
            new Response(201, [], 'accepted'),
        ),
        $subscription,
        $message,
    );
}

function failedWebPushEvent(
    PushSubscription $subscription,
    WebPushMessage $message,
    int $httpStatus,
    string $responseBody,
    string $reason,
): NotificationFailed {
    return new NotificationFailed(
        new MessageSentReport(
            new Request('POST', $subscription->endpoint),
            new Response($httpStatus, [], $responseBody),
            false,
            $reason,
        ),
        $subscription,
        $message,
    );
}

function failedWebPushEventWithoutResponse(
    PushSubscription $subscription,
    WebPushMessage $message,
    string $reason,
): NotificationFailed {
    return new NotificationFailed(
        new MessageSentReport(
            new Request('POST', $subscription->endpoint),
            null,
            false,
            $reason,
        ),
        $subscription,
        $message,
    );
}

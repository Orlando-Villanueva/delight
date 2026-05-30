<?php

namespace App\Notifications;

use App\Models\PushReminderDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ReadingReminderPushNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $reminderType,
        private string $reminderDate,
        private string $targetUrl
    ) {}

    /**
     * @return array<int, class-string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        [$title, $body] = $this->copy();

        return (new WebPushMessage)
            ->title($title)
            ->body($body)
            ->icon('/images/app-icon-v2-192.png')
            ->badge('/images/app-icon-v2-64.png')
            ->tag('delight-'.$this->reminderType.'-'.$this->reminderDate)
            ->data([
                'url' => $this->targetUrl,
                'reminderType' => $this->reminderType,
                'reminderDate' => $this->reminderDate,
            ])
            ->options(['TTL' => 3600]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'reminder_type' => $this->reminderType,
            'reminder_date' => $this->reminderDate,
            'target_url' => $this->targetUrl,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function copy(): array
    {
        if ($this->reminderType === PushReminderDelivery::TYPE_STREAK_RISK) {
            return [
                'Your streak needs today\'s reading',
                'Open Delight and log a reading tonight to keep your streak alive.',
            ];
        }

        return [
            "Time for today's reading",
            'Open Delight and log one chapter when you are ready.',
        ];
    }
}

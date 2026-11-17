<?php

namespace App\Notifications;

use App\Enums\ReadingReminderType;
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
        private string $targetUrl,
        private ?int $deliveryId = null
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
        [$title, $body] = $this->copy(method_exists($notifiable, 'preferredLocale')
            ? $notifiable->preferredLocale()
            : app()->getLocale());

        return (new WebPushMessage)
            ->title($title)
            ->body($body)
            ->icon('/images/app-icon-v2-192.png')
            ->badge('/images/notification-badge.png')
            ->tag('delight-'.$this->reminderType.'-'.$this->reminderDate)
            ->data([
                'url' => $this->targetUrl,
                'deliveryId' => $this->deliveryId,
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
            'delivery_id' => $this->deliveryId,
            'target_url' => $this->targetUrl,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function copy(string $locale): array
    {
        $previousLocale = app()->getLocale();
        app()->setLocale($locale);

        try {
            if ($this->reminderType === ReadingReminderType::StreakRisk->value) {
                return [__('Your streak needs today\'s reading'), __('Open Delight and log a reading tonight to keep your streak alive.')];
            }

            return [__('Time for today\'s reading'), __('Open Delight and log one chapter when you are ready.')];
        } finally {
            app()->setLocale($previousLocale);
        }
    }
}

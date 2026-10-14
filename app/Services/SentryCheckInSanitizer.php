<?php

namespace App\Services;

use Sentry\CheckIn;
use Sentry\Event;
use Sentry\EventType;

class SentryCheckInSanitizer
{
    public const ANNOUNCEMENT_MONITOR_SLUG = 'announcement-email-scheduler';

    public static function sanitize(Event $event): ?Event
    {
        $checkIn = $event->getCheckIn();

        if (! config('services.sentry.announcement_cron_enabled') || ! config('sentry.dsn')
            || $event->getType() !== EventType::checkIn() || $checkIn === null
            || $checkIn->getMonitorSlug() !== self::ANNOUNCEMENT_MONITOR_SLUG) {
            return null;
        }

        return Event::createCheckIn($event->getId())->setCheckIn(new CheckIn(
            self::ANNOUNCEMENT_MONITOR_SLUG,
            $checkIn->getStatus(),
            $checkIn->getId(),
            config('sentry.release'),
            config('sentry.environment'),
            $checkIn->getDuration(),
        ));
    }
}

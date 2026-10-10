<?php

namespace App\Services;

use RuntimeException;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\EventType;
use Sentry\ExceptionDataBag;
use Sentry\ExceptionMechanism;
use Sentry\Frame;
use Sentry\Stacktrace;

class SentryEventSanitizer
{
    public static function sanitize(Event $event, ?EventHint $hint = null): ?Event
    {
        if ($event->getType() !== EventType::event() || $event->getExceptions() === []) {
            return null;
        }

        $sanitized = Event::createEvent($event->getId())
            ->setTimestamp($event->getTimestamp())
            ->setLevel($event->getLevel())
            ->setEnvironment($event->getEnvironment())
            ->setRelease($event->getRelease());

        $exceptions = [];

        foreach ($event->getExceptions() as $exception) {
            $mechanism = $exception->getMechanism();
            $exceptions[] = (new ExceptionDataBag(
                new RuntimeException('[redacted]'),
                self::sanitizeStacktrace($exception->getStacktrace()),
                $mechanism === null ? null : new ExceptionMechanism('generic', $mechanism->isHandled()),
            ))->setType($exception->getType());
        }

        return $sanitized->setExceptions($exceptions);
    }

    private static function sanitizeStacktrace(?Stacktrace $stacktrace): ?Stacktrace
    {
        if ($stacktrace === null) {
            return null;
        }

        $frames = [];

        foreach ($stacktrace->getFrames() as $frame) {
            $frames[] = new Frame(
                $frame->getFunctionName(),
                $frame->getFile(),
                $frame->getLine(),
                inApp: $frame->isInApp(),
            );
        }

        return new Stacktrace($frames);
    }
}

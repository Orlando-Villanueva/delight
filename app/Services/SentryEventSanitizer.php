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

        $tags = $event->getTags();

        $permittedOutcomes = [
            OperationalFailureReporter::NATIVE_PUSH_OPERATION => [OperationalFailureReporter::UNKNOWN_OUTCOME],
            OperationalFailureReporter::NATIVE_PUSH_DELIVERY_OPERATION => [OperationalFailureReporter::RETRY_EXHAUSTED],
            OperationalFailureReporter::ANNOUNCEMENT_EMAIL_OPERATION => [OperationalFailureReporter::TERMINAL_FAILURE, OperationalFailureReporter::UNCERTAIN_OUTCOME],
            OperationalFailureReporter::ONBOARDING_REMINDER_OPERATION => [OperationalFailureReporter::PROCESSING_FAILURE],
        ];
        $operation = $tags['operation'] ?? null;

        if (is_string($operation) && isset($permittedOutcomes[$operation])
            && in_array($tags['outcome'] ?? null, $permittedOutcomes[$operation], true)) {
            $sanitized->setTags([
                'operation' => $operation,
                'outcome' => $tags['outcome'],
            ]);
        }

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

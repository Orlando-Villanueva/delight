<?php

namespace App\Services;

use App\Exceptions\AnnouncementEmailTerminalFailureException;
use App\Exceptions\AnnouncementEmailUncertainOutcomeException;
use App\Exceptions\NativePushReceiptUnavailableException;
use App\Exceptions\NativePushRetryExhaustedException;
use App\Exceptions\NativePushUnknownOutcomeException;
use App\Exceptions\OnboardingReminderProcessingFailureException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Sentry\State\Scope;
use Throwable;

use function Sentry\withScope;

class OperationalFailureReporter
{
    public const NATIVE_PUSH_OPERATION = 'native_push_dispatch';

    public const UNKNOWN_OUTCOME = 'unknown_outcome';

    public const NATIVE_PUSH_DELIVERY_OPERATION = 'native_push_delivery';

    public const RETRY_EXHAUSTED = 'retry_exhausted';

    public const NATIVE_PUSH_RECEIPTS_OPERATION = 'native_push_receipts';

    public const RECEIPT_UNAVAILABLE = 'receipt_unavailable';

    public const ANNOUNCEMENT_EMAIL_OPERATION = 'announcement_email_delivery';

    public const TERMINAL_FAILURE = 'terminal_failure';

    public const UNCERTAIN_OUTCOME = 'uncertain_outcome';

    public const ONBOARDING_REMINDER_OPERATION = 'onboarding_reminder';

    public const PROCESSING_FAILURE = 'processing_failure';

    private const REPORT_INTERVAL_SECONDS = 3600;

    public function reportNativePushUnknownOutcome(): void
    {
        $this->report(self::NATIVE_PUSH_OPERATION, self::UNKNOWN_OUTCOME,
            new NativePushUnknownOutcomeException('Native push send outcome is unknown.'));
    }

    public function reportAnnouncementEmailTerminalFailure(): void
    {
        $this->report(self::ANNOUNCEMENT_EMAIL_OPERATION, self::TERMINAL_FAILURE,
            new AnnouncementEmailTerminalFailureException('Announcement email processing recorded terminal delivery failures.'));
    }

    public function reportAnnouncementEmailUncertainOutcome(): void
    {
        $this->report(self::ANNOUNCEMENT_EMAIL_OPERATION, self::UNCERTAIN_OUTCOME,
            new AnnouncementEmailUncertainOutcomeException('Announcement email processing recorded uncertain delivery outcomes.'));
    }

    public function reportOnboardingReminderProcessingFailure(): void
    {
        $this->report(self::ONBOARDING_REMINDER_OPERATION, self::PROCESSING_FAILURE,
            new OnboardingReminderProcessingFailureException('Onboarding reminder processing recorded failures.'));
    }

    public function reportNativePushRetryExhausted(): void
    {
        $this->report(self::NATIVE_PUSH_DELIVERY_OPERATION, self::RETRY_EXHAUSTED,
            new NativePushRetryExhaustedException('Native push delivery exhausted its rate-limit retries.'));
    }

    public function reportNativePushReceiptUnavailable(): void
    {
        $this->report(self::NATIVE_PUSH_RECEIPTS_OPERATION, self::RECEIPT_UNAVAILABLE,
            new NativePushReceiptUnavailableException('Native push receipts became unavailable after the retention period.'));
    }

    private function report(string $operation, string $outcome, RuntimeException $exception): void
    {
        if (! config('sentry.dsn')) {
            return;
        }

        try {
            $key = 'operational-failure:'.hash('sha256', (string) config('sentry.environment'))
                .':'.$operation.':'.$outcome;

            if (! Cache::add($key, true, self::REPORT_INTERVAL_SECONDS)) {
                return;
            }

            withScope(function (Scope $scope) use ($operation, $outcome, $exception): void {
                $scope->setTag('operation', $operation);
                $scope->setTag('outcome', $outcome);

                report($exception);
            });
        } catch (Throwable) {
            // Monitoring must not change delivery state or release the reporting gate during an outage.
        }
    }
}

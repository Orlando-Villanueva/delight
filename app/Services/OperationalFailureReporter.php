<?php

namespace App\Services;

use App\Exceptions\AnnouncementEmailTerminalFailureException;
use App\Exceptions\NativePushUnknownOutcomeException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Sentry\State\Scope;
use Throwable;

use function Sentry\withScope;

class OperationalFailureReporter
{
    public const NATIVE_PUSH_OPERATION = 'native_push_dispatch';

    public const UNKNOWN_OUTCOME = 'unknown_outcome';

    public const ANNOUNCEMENT_EMAIL_OPERATION = 'announcement_email_delivery';

    public const TERMINAL_FAILURE = 'terminal_failure';

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

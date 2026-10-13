<?php

namespace App\Services;

use App\Exceptions\NativePushUnknownOutcomeException;
use Illuminate\Support\Facades\Cache;
use Sentry\State\Scope;
use Throwable;

use function Sentry\withScope;

class OperationalFailureReporter
{
    public const NATIVE_PUSH_OPERATION = 'native_push_dispatch';

    public const UNKNOWN_OUTCOME = 'unknown_outcome';

    private const REPORT_INTERVAL_SECONDS = 3600;

    public function reportNativePushUnknownOutcome(): void
    {
        if (! config('sentry.dsn')) {
            return;
        }

        try {
            $key = 'operational-failure:'.hash('sha256', (string) config('sentry.environment'))
                .':'.self::NATIVE_PUSH_OPERATION.':'.self::UNKNOWN_OUTCOME;

            if (! Cache::add($key, true, self::REPORT_INTERVAL_SECONDS)) {
                return;
            }

            withScope(function (Scope $scope): void {
                $scope->setTag('operation', self::NATIVE_PUSH_OPERATION);
                $scope->setTag('outcome', self::UNKNOWN_OUTCOME);

                report(new NativePushUnknownOutcomeException('Native push send outcome is unknown.'));
            });
        } catch (Throwable) {
            // Monitoring must not change delivery state or release the reporting gate during an outage.
        }
    }
}

<?php

namespace App\Services;

class NativePushRateLimitRetryService
{
    public const string ERROR_CODE = 'MessageRateExceeded';

    private const int MAX_RETRIES = 3;

    private const int BASE_RETRY_DELAY_MINUTES = 15;

    public function delayMinutes(int $retryCount): ?int
    {
        if ($retryCount >= self::MAX_RETRIES) {
            return null;
        }

        return self::BASE_RETRY_DELAY_MINUTES * (2 ** $retryCount);
    }
}

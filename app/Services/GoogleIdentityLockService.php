<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class GoogleIdentityLockService
{
    private const int EMAIL_LOCK_SECONDS = 30;

    private const int SUBJECT_LOCK_SECONDS = 20;

    private const int LOCK_WAIT_SECONDS = 5;

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function block(string $email, ?string $subject, Closure $callback): mixed
    {
        return Cache::lock(
            $this->lockKey('email', Str::lower($email)),
            self::EMAIL_LOCK_SECONDS
        )->block(
            self::LOCK_WAIT_SECONDS,
            fn (): mixed => filled($subject)
                ? Cache::lock(
                    $this->lockKey('subject', $subject),
                    self::SUBJECT_LOCK_SECONDS
                )->block(self::LOCK_WAIT_SECONDS, $callback)
                : $callback()
        );
    }

    private function lockKey(string $claim, string $value): string
    {
        return "google-mobile-identity-{$claim}-lock:".hash_hmac(
            'sha256',
            $value,
            (string) config('app.key')
        );
    }
}

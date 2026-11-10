<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Telescope\Telescope;

class AutomatedEmailCapacityService
{
    public function reserve(string $purpose): ?string
    {
        $mailer = config('mail.default');
        $host = config('mail.mailers.smtp.host');

        if (in_array($mailer, ['array', 'log'], true)
            || ($mailer === 'smtp' && in_array($host, ['localhost', '127.0.0.1', '::1'], true))) {
            return null;
        }

        if ($mailer !== 'smtp' || ! in_array($host, ['smtp-relay.brevo.com', 'smtp-relay.sendinblue.com'], true)) {
            return 'Email capacity is unknown for the configured transport.';
        }

        $key = config('services.brevo.capacity_api_key');

        if (! is_string($key) || trim($key) === '' || preg_match('/\s/', $key) === 1) {
            return 'Brevo capacity access is not configured.';
        }

        try {
            $response = Telescope::withoutRecording(fn () => Http::withHeaders(['api-key' => $key])
                ->acceptJson()->connectTimeout(3)->timeout(10)
                ->withOptions(['allow_redirects' => false])
                ->get('https://api.brevo.com/v3/account'));
        } catch (ConnectionException) {
            return 'Brevo capacity is unavailable; waiting for a fresh check.';
        }

        $plans = $response->json('plan');

        if (! $response->ok() || ! is_array($plans)) {
            return 'Brevo capacity could not be verified.';
        }

        $emailPlans = collect($plans)->filter(fn ($plan): bool => is_array($plan)
            && ($plan['type'] ?? null) === 'free'
            && ($plan['creditsType'] ?? null) === 'sendLimit')->values();
        $credits = $emailPlans->count() === 1 ? ($emailPlans[0]['credits'] ?? null) : null;

        if ((! is_int($credits) && ! is_float($credits)) || ! is_finite((float) $credits)
            || $credits < 0 || floor($credits) !== (float) $credits) {
            return 'Brevo returned an unknown or invalid email allowance.';
        }

        if ($credits <= 100) {
            return 'Waiting for Brevo email capacity while preserving account-email headroom.';
        }

        return DB::transaction(function () use ($purpose): ?string {
            DB::table('automated_email_capacity_guards')->where('id', 1)->lockForUpdate()->firstOrFail();

            if (DB::table('automated_email_reservations')->where('reserved_at', '>', now()->utc()->subHours(24))->count() >= 200) {
                return 'Waiting for the shared 200-email rolling 24-hour allowance.';
            }

            DB::table('automated_email_reservations')->insert([
                'purpose' => $purpose,
                'reserved_at' => now()->utc(),
            ]);

            return null;
        });
    }
}

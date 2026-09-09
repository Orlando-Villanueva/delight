<?php

namespace App\Services;

use App\Models\NativePushRegistration;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class NativePushRegistrationService
{
    public function register(PersonalAccessToken $session, string $expoPushToken): void
    {
        try {
            DB::transaction(function () use ($session, $expoPushToken): void {
                $currentSession = PersonalAccessToken::query()->lockForUpdate()->find($session->getKey());
                abort_unless($currentSession, 401);

                $hash = hash('sha256', $expoPushToken);
                $existing = NativePushRegistration::query()->where('token_hash', $hash)->lockForUpdate()->first();

                if ($existing && $existing->personal_access_token_id !== $session->getKey()) {
                    abort_if($existing->personal_access_token_id > $session->getKey(), 409,
                        'This notification address belongs to a newer session. Sign in again to register it.');
                    $existing->delete();
                }

                NativePushRegistration::query()->updateOrCreate([
                    'personal_access_token_id' => $session->getKey(),
                ], [
                    'expo_push_token' => $expoPushToken,
                    'token_hash' => $hash,
                ]);
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            abort(409, 'Notification registration changed. Refresh its status and try again.');
        }
    }
}

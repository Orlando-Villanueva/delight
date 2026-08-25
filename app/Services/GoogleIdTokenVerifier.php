<?php

namespace App\Services;

use App\Contracts\GoogleIdTokenVerifierContract;
use Google_Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

class GoogleIdTokenVerifier implements GoogleIdTokenVerifierContract
{
    public function verify(string $idToken): ?GoogleIdentity
    {
        foreach ($this->acceptedAudiences() as $audience) {
            try {
                $payload = (new Google_Client(['client_id' => $audience]))->verifyIdToken($idToken);
            } catch (Throwable) {
                return null;
            }

            if (! is_array($payload)) {
                continue;
            }

            $identity = $this->identityFromPayload($payload);

            if ($identity !== null) {
                return $identity;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function acceptedAudiences(): array
    {
        return collect(config('services.google.mobile_client_ids', []))
            ->filter(fn (mixed $clientId): bool => is_string($clientId) && filled($clientId))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function identityFromPayload(array $payload): ?GoogleIdentity
    {
        $subject = Arr::get($payload, 'sub');
        $email = Arr::get($payload, 'email');
        $emailVerified = filter_var(Arr::get($payload, 'email_verified'), FILTER_VALIDATE_BOOL);
        $expiresAt = filter_var(Arr::get($payload, 'exp'), FILTER_VALIDATE_INT);

        if (! is_string($subject)
            || blank($subject)
            || ! is_string($email)
            || blank($email)
            || ! $emailVerified
            || ! is_int($expiresAt)
            || $expiresAt <= now()->timestamp) {
            return null;
        }

        $hostedDomain = Arr::get($payload, 'hd');
        $name = Arr::get($payload, 'name');
        $avatarUrl = Arr::get($payload, 'picture');

        return new GoogleIdentity(
            subject: $subject,
            email: Str::lower($email),
            emailVerified: true,
            expiresAt: $expiresAt,
            hostedDomain: is_string($hostedDomain) && filled($hostedDomain) ? Str::lower($hostedDomain) : null,
            name: is_string($name) && filled($name) ? Str::limit($name, 255, '') : null,
            avatarUrl: is_string($avatarUrl) && filled($avatarUrl) ? Str::limit($avatarUrl, 255, '') : null,
        );
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\GoogleIdTokenVerifierContract;
use App\Exceptions\GoogleIdentityConflictException;
use App\Exceptions\GooglePasswordProofRequiredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreGoogleTokenRequest;
use App\Http\Resources\Api\V1\MobileTokenResource;
use App\Models\User;
use App\Services\GoogleTokenExchangeService;
use Illuminate\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class GoogleMobileTokenController extends Controller
{
    public function __invoke(
        StoreGoogleTokenRequest $request,
        GoogleIdTokenVerifierContract $verifier,
        GoogleTokenExchangeService $exchangeService
    ): MobileTokenResource {
        $credentials = $request->validated();
        $identity = $verifier->verify($credentials['id_token']);

        if ($identity === null) {
            $this->logRejection('invalid_identity');

            throw $this->invalidIdentity();
        }

        try {
            return Cache::lock($this->identityLockKey($identity->subject), 10)->block(5, function () use ($credentials, $identity, $exchangeService): MobileTokenResource {
                $replayKey = $this->replayKey($credentials['id_token']);

                if (Cache::has($replayKey)) {
                    $this->logRejection('replay');

                    throw $this->invalidIdentity();
                }

                try {
                    $user = $exchangeService->resolve($identity, $credentials['password'] ?? null);
                } catch (GooglePasswordProofRequiredException) {
                    $this->logRejection('password_proof_required');

                    throw ValidationException::withMessages([
                        'password' => ['Confirm your Delight password to link this Google account.'],
                    ]);
                } catch (GoogleIdentityConflictException) {
                    $this->logRejection('identity_conflict');

                    throw $this->invalidIdentity();
                }

                Cache::put($replayKey, true, now()->addMinutes(5));

                return $this->tokenResource($user, $credentials['device_name']);
            });
        } catch (LockTimeoutException) {
            $this->logRejection('lock_timeout');

            throw $this->invalidIdentity();
        }
    }

    private function tokenResource(User $user, string $deviceName): MobileTokenResource
    {
        $token = $user->createToken($deviceName, ['mobile']);

        return new MobileTokenResource([
            'plain_text_token' => $token->plainTextToken,
            'user' => $user,
        ]);
    }

    private function invalidIdentity(): ValidationException
    {
        return ValidationException::withMessages([
            'id_token' => ['Unable to verify this Google account. Please try again.'],
        ]);
    }

    private function identityLockKey(string $subject): string
    {
        return 'google-mobile-identity-lock:'.hash_hmac('sha256', $subject, (string) config('app.key'));
    }

    private function replayKey(string $idToken): string
    {
        return 'google-mobile-token-replay:'.$this->tokenFingerprint($idToken);
    }

    private function tokenFingerprint(string $idToken): string
    {
        return hash_hmac('sha256', $idToken, (string) config('app.key'));
    }

    private function logRejection(string $reason): void
    {
        Log::notice('Google mobile token exchange rejected.', ['reason' => $reason]);
    }
}

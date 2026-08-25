<?php

use App\Services\GoogleIdentity;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Support\Facades\Log;

function verifiedGooglePayload(array $overrides = []): array
{
    return $overrides + [
        'sub' => 'google-subject-1',
        'email' => 'Reader@Example.com',
        'email_verified' => true,
        'exp' => now()->addHour()->timestamp,
        'hd' => 'Example.COM',
        'name' => 'Delight Reader',
        'picture' => 'https://example.com/avatar.png',
    ];
}

function googleClientWithResponses(array $responses): Google_Client
{
    return new class($responses) extends Google_Client
    {
        /** @var list<string> */
        public array $clientIds = [];

        public function __construct(private array $responses) {}

        public function setClientId($clientId)
        {
            $this->clientIds[] = $clientId;
        }

        public function verifyIdToken($idToken = null)
        {
            $response = array_shift($this->responses);

            if ($response instanceof Throwable) {
                throw $response;
            }

            return $response;
        }
    };
}

it('tries normalized configured audiences with one Google client', function (): void {
    config(['services.google.mobile_client_ids' => [' first-client ', '', ' second-client ']]);
    $client = googleClientWithResponses([false, verifiedGooglePayload()]);

    $identity = (new GoogleIdTokenVerifier($client))->verify('google-id-token');

    expect($identity)->toBeInstanceOf(GoogleIdentity::class)
        ->and($identity->email)->toBe('reader@example.com')
        ->and($identity->hostedDomain)->toBe('example.com')
        ->and($client->clientIds)->toBe(['first-client', 'second-client']);
});

it('rejects incomplete or untrusted verified payloads', function (array $payload): void {
    config(['services.google.mobile_client_ids' => ['mobile-client']]);
    $client = googleClientWithResponses([$payload]);

    expect((new GoogleIdTokenVerifier($client))->verify('google-id-token'))->toBeNull();
})->with([
    'missing subject' => fn (): array => array_diff_key(verifiedGooglePayload(), ['sub' => true]),
    'missing email' => fn (): array => array_diff_key(verifiedGooglePayload(), ['email' => true]),
    'unverified email' => fn (): array => verifiedGooglePayload(['email_verified' => false]),
    'expired assertion' => fn (): array => verifiedGooglePayload(['exp' => now()->subSecond()->timestamp]),
    'malformed expiry' => fn (): array => verifiedGooglePayload(['exp' => 'not-a-timestamp']),
]);

it('returns null without logging expected invalid-token exceptions', function (): void {
    config(['services.google.mobile_client_ids' => ['mobile-client']]);
    Log::spy();
    $client = googleClientWithResponses([new UnexpectedValueException('sensitive-token-details')]);

    expect((new GoogleIdTokenVerifier($client))->verify('google-id-token'))->toBeNull();

    Log::shouldNotHaveReceived('error');
});

it('logs only safe context for unexpected verification failures', function (): void {
    config(['services.google.mobile_client_ids' => ['mobile-client']]);
    Log::spy();
    $client = googleClientWithResponses([new RuntimeException('sensitive-network-details')]);

    expect((new GoogleIdTokenVerifier($client))->verify('google-id-token'))->toBeNull();

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Google ID token verification unavailable.'
            && $context === [
                'reason' => 'verification_unavailable',
                'exception_class' => RuntimeException::class,
            ]);
});

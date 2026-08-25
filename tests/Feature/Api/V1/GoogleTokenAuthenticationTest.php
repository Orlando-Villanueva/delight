<?php

use App\Contracts\GoogleIdTokenVerifierContract;
use App\Models\User;
use App\Services\GoogleIdentity;
use App\Services\GoogleTokenExchangeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;

function googleIdentity(array $attributes = []): GoogleIdentity
{
    return new GoogleIdentity(
        subject: $attributes['subject'] ?? 'google-subject-1',
        email: $attributes['email'] ?? 'reader@gmail.com',
        emailVerified: $attributes['email_verified'] ?? true,
        expiresAt: $attributes['expires_at'] ?? now()->addHour()->timestamp,
        hostedDomain: $attributes['hosted_domain'] ?? null,
        name: $attributes['name'] ?? 'Delight Reader',
        avatarUrl: $attributes['avatar_url'] ?? 'https://example.com/avatar.png',
    );
}

function fakeGoogleIdentityVerifier(?GoogleIdentity $identity): void
{
    app()->instance(GoogleIdTokenVerifierContract::class, new class($identity) implements GoogleIdTokenVerifierContract
    {
        public function __construct(private ?GoogleIdentity $identity) {}

        public function verify(string $idToken): ?GoogleIdentity
        {
            return $this->identity;
        }
    });
}

function exchangeGoogleToken(array $payload = []): TestResponse
{
    return test()->postJson('/api/v1/auth/google-token', $payload + [
        'id_token' => 'google-id-token-1',
        'device_name' => 'Orlando Pixel',
    ]);
}

beforeEach(function (): void {
    Cache::flush();
});

it('creates a user and issues a mobile token for a verified Google identity', function (): void {
    fakeGoogleIdentityVerifier(googleIdentity());

    $response = exchangeGoogleToken();

    $response
        ->assertSuccessful()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.email', 'reader@gmail.com');

    $user = User::query()->sole();
    $accessToken = PersonalAccessToken::findToken($response->json('data.token'));

    expect($user->google_subject)->toBe('google-subject-1')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($accessToken->abilities)->toBe(['mobile'])
        ->and($accessToken->expires_at)->toBeNull();
});

it('returns the existing Google subject binding without creating a duplicate account', function (): void {
    $user = User::factory()->create([
        'email' => 'reader@gmail.com',
        'google_subject' => 'google-subject-1',
    ]);
    fakeGoogleIdentityVerifier(googleIdentity());

    exchangeGoogleToken()->assertJsonPath('data.user.id', $user->id);

    expect(User::query()->count())->toBe(1);
});

it('binds an unbound Gmail account without requiring a password proof', function (): void {
    $user = User::factory()->create(['email' => 'reader@gmail.com']);
    fakeGoogleIdentityVerifier(googleIdentity());

    exchangeGoogleToken()->assertJsonPath('data.user.id', $user->id);

    expect($user->fresh()->google_subject)->toBe('google-subject-1');
});

it('requires the existing Delight password before binding an external-domain account', function (): void {
    $user = User::factory()->create([
        'email' => 'reader@example.com',
        'password' => Hash::make('ValidPass123!'),
    ]);
    fakeGoogleIdentityVerifier(googleIdentity(['email' => $user->email]));

    exchangeGoogleToken()
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    exchangeGoogleToken([
        'id_token' => 'google-id-token-2',
        'password' => 'ValidPass123!',
    ])->assertJsonPath('data.user.id', $user->id);

    expect($user->fresh()->google_subject)->toBe('google-subject-1');
});

it('binds an unbound managed Workspace account without a password proof', function (): void {
    $user = User::factory()->create(['email' => 'reader@example.com']);
    fakeGoogleIdentityVerifier(googleIdentity([
        'email' => $user->email,
        'hosted_domain' => 'example.com',
    ]));

    exchangeGoogleToken()->assertJsonPath('data.user.id', $user->id);

    expect($user->fresh()->google_subject)->toBe('google-subject-1');
});

it('rejects conflicting Google subject and email ownership without merging accounts', function (): void {
    User::factory()->create([
        'email' => 'subject-owner@gmail.com',
        'google_subject' => 'google-subject-1',
    ]);
    User::factory()->create(['email' => 'reader@gmail.com']);
    fakeGoogleIdentityVerifier(googleIdentity());

    exchangeGoogleToken()
        ->assertUnprocessable()
        ->assertJsonValidationErrors('id_token');

    expect(User::query()->count())->toBe(2)
        ->and(PersonalAccessToken::query()->count())->toBe(0);
});

it('rejects invalid, wrong-audience, and expired Google assertions without issuing tokens', function (string $case): void {
    fakeGoogleIdentityVerifier(null);

    exchangeGoogleToken(['id_token' => "{$case}-assertion"])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('id_token');

    expect(User::query()->count())->toBe(0)
        ->and(PersonalAccessToken::query()->count())->toBe(0);
})->with(['invalid token', 'wrong audience', 'expired token']);

it('prevents a verified Google assertion from being replayed', function (): void {
    fakeGoogleIdentityVerifier(googleIdentity());

    exchangeGoogleToken()->assertSuccessful();

    $this->travel(6)->minutes();

    exchangeGoogleToken()
        ->assertUnprocessable()
        ->assertJsonValidationErrors('id_token');

    expect(PersonalAccessToken::query()->count())->toBe(1);
});

it('serializes repeated identity resolution without creating a duplicate user', function (): void {
    fakeGoogleIdentityVerifier(googleIdentity());

    exchangeGoogleToken(['id_token' => 'google-id-token-1'])->assertSuccessful();
    exchangeGoogleToken(['id_token' => 'google-id-token-2'])->assertSuccessful();

    expect(User::query()->count())->toBe(1)
        ->and(PersonalAccessToken::query()->count())->toBe(2);
});

it('holds the email identity lock throughout the account resolution window', function (): void {
    $identity = googleIdentity();
    $user = User::factory()->create([
        'email' => $identity->email,
        'google_subject' => $identity->subject,
    ]);

    fakeGoogleIdentityVerifier($identity);

    app()->instance(GoogleTokenExchangeService::class, new class($user) extends GoogleTokenExchangeService
    {
        public function __construct(private User $user) {}

        public function resolve(GoogleIdentity $identity, ?string $passwordProof): User
        {
            test()->travel(12)->seconds();

            $emailLockKey = 'google-mobile-identity-email-lock:'.hash_hmac(
                'sha256',
                $identity->email,
                (string) config('app.key')
            );

            expect(Cache::lock($emailLockKey, 10)->get())->toBeFalse();

            return $this->user;
        }
    });

    exchangeGoogleToken()->assertJsonPath('data.user.id', $user->id);
});

it('rate limits Google token exchanges by IP in production', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');
    fakeGoogleIdentityVerifier(null);

    foreach (range(1, 5) as $attempt) {
        exchangeGoogleToken(['id_token' => "invalid-{$attempt}"])->assertUnprocessable();
    }

    exchangeGoogleToken(['id_token' => 'invalid-six'])->assertTooManyRequests();
});

it('logs only a structured rejection reason for an invalid Google assertion', function (): void {
    Log::spy();
    fakeGoogleIdentityVerifier(null);

    exchangeGoogleToken(['id_token' => 'sensitive-google-assertion'])->assertUnprocessable();

    Log::shouldHaveReceived('notice')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Google mobile token exchange rejected.'
            && $context === ['reason' => 'invalid_identity']);
});

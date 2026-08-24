<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

it('issues a non-expiring mobile bearer token for valid normalized credentials', function () {
    $user = User::factory()->create([
        'email' => 'reader@example.com',
        'password' => Hash::make('ValidPass123!'),
        'avatar_url' => null,
    ]);

    $response = $this->postJson('/api/v1/auth/token', [
        'email' => 'READER@EXAMPLE.COM',
        'password' => 'ValidPass123!',
        'device_name' => 'Orlando Pixel',
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.name', $user->name)
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonPath('data.user.avatar_url', null)
        ->assertJsonStructure([
            'data' => [
                'token',
                'token_type',
                'user' => ['id', 'name', 'email', 'avatar_url'],
            ],
        ]);

    $plainTextToken = $response->json('data.token');
    $accessToken = PersonalAccessToken::findToken($plainTextToken);

    expect($plainTextToken)->toStartWith($accessToken->getKey().'|')
        ->and($accessToken->name)->toBe('Orlando Pixel')
        ->and($accessToken->abilities)->toBe(['mobile'])
        ->and($accessToken->expires_at)->toBeNull()
        ->and($accessToken->token)->not->toBe($plainTextToken);
});

it('returns the same generic validation response for invalid credentials', function (array $credentials) {
    User::factory()->create([
        'email' => 'reader@example.com',
        'password' => Hash::make('ValidPass123!'),
    ]);

    $this->postJson('/api/v1/auth/token', $credentials + ['device_name' => 'Pixel'])
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => __('auth.failed'),
            'errors' => [
                'email' => [__('auth.failed')],
            ],
        ]);
})->with([
    'existing account with wrong password' => [[
        'email' => 'reader@example.com',
        'password' => 'wrong-password',
    ]],
    'unknown account' => [[
        'email' => 'unknown@example.com',
        'password' => 'wrong-password',
    ]],
]);

it('timeboxes credential verification for an unknown account', function () {
    $startedAt = hrtime(true);

    $this->postJson('/api/v1/auth/token', [
        'email' => 'unknown@example.com',
        'password' => 'wrong-password',
        'device_name' => 'Pixel',
    ])->assertUnprocessable();

    $elapsedMilliseconds = (hrtime(true) - $startedAt) / 1_000_000;

    expect($elapsedMilliseconds)->toBeGreaterThanOrEqual(150);
});

it('rehashes stale passwords after successful mobile login', function () {
    $password = 'ValidPass123!';
    $configuredCost = password_get_info(Hash::make($password))['options']['cost'];
    $user = User::factory()->create([
        'email' => 'reader@example.com',
    ]);
    $staleHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => $configuredCost + 1]);

    User::query()->whereKey($user)->toBase()->update(['password' => $staleHash]);
    $user->refresh();

    expect(Hash::needsRehash($user->password))->toBeTrue();

    $this->postJson('/api/v1/auth/token', [
        'email' => $user->email,
        'password' => $password,
        'device_name' => 'Pixel',
    ])->assertSuccessful();

    expect(Hash::needsRehash($user->fresh()->password))->toBeFalse();
});

it('limits production attempts by normalized email and IP', function () {
    $this->app->detectEnvironment(fn (): string => 'production');

    foreach (range(1, 5) as $attempt) {
        $email = $attempt % 2 === 0 ? 'READER@example.com' : 'reader@EXAMPLE.com';

        $this->postJson('/api/v1/auth/token', [
            'email' => $email,
            'password' => 'wrong-password',
            'device_name' => 'Pixel',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/token', [
        'email' => 'reader@example.com',
        'password' => 'wrong-password',
        'device_name' => 'Pixel',
    ])->assertTooManyRequests();
});

it('validates malformed email values after applying the login limiter', function () {
    $this->postJson('/api/v1/auth/token', [
        'email' => ['reader@example.com'],
        'password' => 'wrong-password',
        'device_name' => 'Pixel',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('returns clear mobile credential validation messages', function (array $payload, string $field, string $message) {
    $response = $this->postJson('/api/v1/auth/token', $payload)->assertUnprocessable();

    expect($response->json("errors.{$field}"))->toContain($message);
})->with([
    'email required' => [
        ['password' => 'ValidPass123!', 'device_name' => 'Pixel'],
        'email',
        'An email address is required.',
    ],
    'email invalid' => [
        ['email' => 'not-an-email', 'password' => 'ValidPass123!', 'device_name' => 'Pixel'],
        'email',
        'Enter a valid email address.',
    ],
    'email maximum length' => [
        ['email' => str_repeat('a', 250).'@example.com', 'password' => 'ValidPass123!', 'device_name' => 'Pixel'],
        'email',
        'The email address may not be greater than 255 characters.',
    ],
    'password required' => [
        ['email' => 'reader@example.com', 'device_name' => 'Pixel'],
        'password',
        'A password is required.',
    ],
    'device name required' => [
        ['email' => 'reader@example.com', 'password' => 'ValidPass123!'],
        'device_name',
        'A device name is required.',
    ],
    'device name maximum length' => [
        ['email' => 'reader@example.com', 'password' => 'ValidPass123!', 'device_name' => str_repeat('a', 256)],
        'device_name',
        'The device name may not be greater than 255 characters.',
    ],
]);

it('rejects logout without a token', function () {
    $this->deleteJson('/api/v1/auth/token')->assertUnauthorized();
});

it('rejects logout when the token lacks the mobile ability', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Web integration', ['reporting'])->plainTextToken;

    $this->withToken($token)
        ->deleteJson('/api/v1/auth/token')
        ->assertForbidden();

    expect($user->tokens()->count())->toBe(1);
});

it('revokes only the current mobile token and invalidates it', function () {
    $user = User::factory()->create();
    $currentToken = $user->createToken('Current Pixel', ['mobile'])->plainTextToken;
    $otherToken = $user->createToken('Other device', ['mobile'])->plainTextToken;

    $this->withToken($currentToken)
        ->deleteJson('/api/v1/auth/token')
        ->assertNoContent();

    expect(PersonalAccessToken::findToken($currentToken))->toBeNull()
        ->and(PersonalAccessToken::findToken($otherToken))->not->toBeNull()
        ->and($user->tokens()->pluck('name')->all())->toBe(['Other device']);

    $this->app['auth']->forgetGuards();

    $this->withToken($currentToken)
        ->deleteJson('/api/v1/auth/token')
        ->assertUnauthorized();

    $this->app['auth']->forgetGuards();

    $this->withToken($otherToken)
        ->deleteJson('/api/v1/auth/token')
        ->assertNoContent();
});

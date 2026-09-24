<?php

use App\Models\NativePushRegistration;
use App\Models\NativeReminderPreference;
use App\Models\User;
use App\Providers\TelescopeServiceProvider;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\Watchers\RequestWatcher;

const NATIVE_PUSH_ENDPOINT = '/api/v1/native-push-registration';
const NATIVE_PUSH_PHONE = 'ExpoPushToken[phone-test-address]';
const NATIVE_PUSH_TABLET = 'ExponentPushToken[tablet-test-address]';

beforeEach(function (): void {
    Cache::flush();
});

it('requires mobile bearer authentication for every registration operation', function (string $method): void {
    $this->{$method}(NATIVE_PUSH_ENDPOINT)->assertUnauthorized();
    $user = User::factory()->create();
    $this->withToken($user->createToken('Reporting', ['reporting'])->plainTextToken)
        ->{$method}(NATIVE_PUSH_ENDPOINT, ['expo_push_token' => NATIVE_PUSH_PHONE])->assertForbidden();

    $this->assertDatabaseCount('native_push_registrations', 0);
})->with(['getJson', 'putJson', 'patchJson', 'deleteJson']);

it('rejects browser cookies without a mobile session', function (string $method): void {
    $this->actingAs(User::factory()->create())
        ->{$method}(NATIVE_PUSH_ENDPOINT, ['expo_push_token' => NATIVE_PUSH_PHONE])->assertForbidden();

    $this->assertDatabaseCount('native_push_registrations', 0);
})->with(['getJson', 'putJson', 'patchJson', 'deleteJson']);

it('starts unregistered and saves an encrypted address without opting in or sending notifications', function (string $address): void {
    Http::fake();
    $session = User::factory()->create()->createToken('Phone', ['mobile']);
    $this->withToken($session->plainTextToken)->getJson(NATIVE_PUSH_ENDPOINT)
        ->assertOk()->assertExactJson(['data' => ['registered' => false]]);

    $this->withToken($session->plainTextToken)->putJson(NATIVE_PUSH_ENDPOINT, [
        'expo_push_token' => $address, 'enabled' => true,
    ])->assertOk()->assertExactJson(['data' => ['registered' => true]]);

    $registration = NativePushRegistration::sole();
    expect($registration->expo_push_token)->toBe($address);
    expect($registration->getRawOriginal('expo_push_token'))->not->toBe($address);
    expect($registration->toArray())->not->toHaveKeys(['expo_push_token', 'token_hash']);
    expect($registration->personalAccessToken->id)->toBe($session->accessToken->id);
    $this->assertDatabaseCount('native_reminder_preferences', 0);
    Http::assertNothingSent();
})->with([NATIVE_PUSH_PHONE, NATIVE_PUSH_TABLET, '12345678-1234-1234-1234-123456789abc']);

it('updates the same registration when repeated or rotated and preserves the preference', function (): void {
    $user = User::factory()->create();
    $session = $user->createToken('Phone', ['mobile']);
    $preference = NativeReminderPreference::factory()->for($user)->create([
        'personal_access_token_id' => $session->accessToken->id, 'enabled' => false,
    ]);

    foreach ([NATIVE_PUSH_PHONE, NATIVE_PUSH_PHONE, NATIVE_PUSH_TABLET] as $address) {
        $this->withToken($session->plainTextToken)->patchJson(NATIVE_PUSH_ENDPOINT, [
            'expo_push_token' => $address,
        ])->assertOk();
        $this->assertDatabaseCount('native_push_registrations', 1);
        expect(NativePushRegistration::sole()->expo_push_token)->toBe($address);
    }

    expect($preference->fresh()->enabled)->toBeFalse();
    $this->assertDatabaseMissing('native_push_registrations', ['token_hash' => hash('sha256', NATIVE_PUSH_PHONE)]);
});

it('leaves the existing address unchanged on a 422 validation failure', function (mixed $address): void {
    $session = User::factory()->create()->createToken('Phone', ['mobile']);
    $registration = NativePushRegistration::factory()->create([
        'personal_access_token_id' => $session->accessToken->id,
        'expo_push_token' => NATIVE_PUSH_PHONE,
    ]);

    $this->withToken($session->plainTextToken)->putJson(NATIVE_PUSH_ENDPOINT, ['expo_push_token' => $address])
        ->assertUnprocessable()->assertJsonValidationErrors('expo_push_token');

    expect($registration->fresh()->expo_push_token)->toBe(NATIVE_PUSH_PHONE);
})->with([
    'missing' => [null], 'not a string' => [[]], 'wrong provider' => ['FCM-token'],
    'empty payload' => ['ExpoPushToken[]'], 'oversized' => ['ExpoPushToken['.str_repeat('a', 256).']'],
    'newline' => ["ExpoPushToken[abc\ndef]"],
]);

it('keeps both devices registered and unregisters only the current session', function (): void {
    $user = User::factory()->create();
    $phone = $user->createToken('Phone', ['mobile']);
    $tablet = $user->createToken('Tablet', ['mobile']);
    $phoneRegistration = NativePushRegistration::factory()->create([
        'personal_access_token_id' => $phone->accessToken->id, 'expo_push_token' => NATIVE_PUSH_PHONE,
    ]);
    $preference = NativeReminderPreference::factory()->for($user)->create([
        'personal_access_token_id' => $tablet->accessToken->id, 'enabled' => true,
    ]);

    $this->withToken($tablet->plainTextToken)->putJson(NATIVE_PUSH_ENDPOINT, [
        'expo_push_token' => NATIVE_PUSH_TABLET,
        'personal_access_token_id' => $phone->accessToken->id,
    ])->assertOk();
    $this->assertDatabaseCount('native_push_registrations', 2);

    $this->withToken($tablet->plainTextToken)->deleteJson(NATIVE_PUSH_ENDPOINT, [
        'personal_access_token_id' => $phone->accessToken->id,
    ])->assertNoContent();
    $this->withToken($tablet->plainTextToken)->deleteJson(NATIVE_PUSH_ENDPOINT)->assertNoContent();
    $this->withToken($tablet->plainTextToken)->getJson(NATIVE_PUSH_ENDPOINT)
        ->assertOk()->assertExactJson(['data' => ['registered' => false]]);

    $this->assertModelExists($phoneRegistration);
    $this->assertDatabaseCount('native_push_registrations', 1);
    expect($preference->fresh()->enabled)->toBeTrue();
});

it('moves an address to a newer account session without inheriting opt-in or allowing stale reclamation', function (): void {
    $oldSession = User::factory()->create()->createToken('Old login', ['mobile']);
    $newSession = User::factory()->create()->createToken('New login', ['mobile']);
    NativePushRegistration::factory()->create([
        'personal_access_token_id' => $oldSession->accessToken->id, 'expo_push_token' => NATIVE_PUSH_PHONE,
    ]);

    $this->withToken($newSession->plainTextToken)->putJson(NATIVE_PUSH_ENDPOINT, [
        'expo_push_token' => NATIVE_PUSH_PHONE,
    ])->assertOk();
    expect(NativePushRegistration::sole()->personal_access_token_id)->toBe($newSession->accessToken->id);
    $this->assertDatabaseCount('native_reminder_preferences', 0);

    $this->app['auth']->forgetGuards();
    $this->withToken($oldSession->plainTextToken)->getJson(NATIVE_PUSH_ENDPOINT)
        ->assertOk()->assertExactJson(['data' => ['registered' => false]]);
    $this->withToken($oldSession->plainTextToken)->putJson(NATIVE_PUSH_ENDPOINT, [
        'expo_push_token' => NATIVE_PUSH_PHONE,
    ])->assertConflict();
    $this->withToken($oldSession->plainTextToken)->deleteJson(NATIVE_PUSH_ENDPOINT)->assertNoContent();
    $this->withToken($oldSession->plainTextToken)->deleteJson('/api/v1/auth/token')->assertNoContent();

    expect(NativePushRegistration::sole()->personal_access_token_id)->toBe($newSession->accessToken->id);
});

it('removes the address on logout without removing another device address', function (): void {
    $user = User::factory()->create();
    $phone = $user->createToken('Phone', ['mobile']);
    $tablet = $user->createToken('Tablet', ['mobile']);
    $phoneRegistration = NativePushRegistration::factory()->create(['personal_access_token_id' => $phone->accessToken->id]);
    $tabletRegistration = NativePushRegistration::factory()->create(['personal_access_token_id' => $tablet->accessToken->id]);

    $this->withToken($phone->plainTextToken)->deleteJson('/api/v1/auth/token')->assertNoContent();

    $this->assertModelMissing($phoneRegistration);
    $this->assertModelExists($tabletRegistration);
    $this->app['auth']->forgetGuards();
    $this->withToken($phone->plainTextToken)->putJson(NATIVE_PUSH_ENDPOINT, [
        'expo_push_token' => NATIVE_PUSH_PHONE,
    ])->assertUnauthorized();
});

it('rejects expired sessions before accepting an address', function (): void {
    $session = User::factory()->create()->createToken('Expired', ['mobile'], now()->subMinute());

    $this->withToken($session->plainTextToken)->putJson(NATIVE_PUSH_ENDPOINT, [
        'expo_push_token' => NATIVE_PUSH_PHONE,
    ])->assertUnauthorized();

    $this->assertDatabaseCount('native_push_registrations', 0);
});

it('limits repeated registration requests without changing the saved address', function (): void {
    $session = User::factory()->create()->createToken('Phone', ['mobile']);
    for ($attempt = 0; $attempt < 30; $attempt++) {
        $this->withToken($session->plainTextToken)->putJson(NATIVE_PUSH_ENDPOINT, [
            'expo_push_token' => NATIVE_PUSH_PHONE,
        ])->assertOk();
    }

    $this->withToken($session->plainTextToken)->putJson(NATIVE_PUSH_ENDPOINT, [
        'expo_push_token' => NATIVE_PUSH_TABLET,
    ])->assertTooManyRequests();

    expect(NativePushRegistration::sole()->expo_push_token)->toBe(NATIVE_PUSH_PHONE);
});

it('does not flash notification addresses when validation redirects', function (): void {
    $session = User::factory()->create()->createToken('Phone', ['mobile']);

    $this->withToken($session->plainTextToken)->put(NATIVE_PUSH_ENDPOINT, [
        'expo_push_token' => 'invalid-sensitive-address',
    ])->assertRedirect()->assertSessionMissing('_old_input.expo_push_token');
});

it('redacts the notification address in local Telescope request records', function (): void {
    $environment = $this->app['env'];
    $hidden = Telescope::$hiddenRequestParameters;
    $filters = Telescope::$filterUsing;
    $entries = Telescope::$entriesQueue;
    $recording = Telescope::isRecording();

    try {
        $this->app['env'] = 'local';
        (new TelescopeServiceProvider($this->app))->register();
        Telescope::$entriesQueue = [];
        Telescope::startRecording();
        $request = Request::create(NATIVE_PUSH_ENDPOINT, 'PUT', [
            'expo_push_token' => NATIVE_PUSH_PHONE,
        ]);
        (new RequestWatcher)->recordRequest(
            new RequestHandled($request, response()->json(['data' => ['registered' => true]]))
        );

        expect(Telescope::$entriesQueue)->toHaveCount(1);
        expect(Telescope::$entriesQueue[0]->content['payload']['expo_push_token'])->toBe('********');
    } finally {
        $this->app['env'] = $environment;
        Telescope::$hiddenRequestParameters = $hidden;
        Telescope::$filterUsing = $filters;
        Telescope::$entriesQueue = $entries;
        if (! $recording) {
            Telescope::stopRecording();
        }
    }
});

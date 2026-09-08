<?php

use App\Models\NativeReminderPreference;
use App\Models\User;

const NATIVE_REMINDER_PREFERENCES_ENDPOINT = '/api/v1/native-reminder-preferences';

it('returns 401 without authentication', function (string $method): void {
    $this->{$method}(NATIVE_REMINDER_PREFERENCES_ENDPOINT)->assertUnauthorized();

    $this->assertDatabaseCount('native_reminder_preferences', 0);
})->with(['getJson', 'putJson', 'patchJson']);

it('returns 403 without the mobile token ability', function (string $method): void {
    $user = User::factory()->create();
    $token = $user->createToken('Reporting integration', ['reporting'])->plainTextToken;

    $this->withToken($token)->{$method}(NATIVE_REMINDER_PREFERENCES_ENDPOINT)->assertForbidden();

    $this->assertDatabaseCount('native_reminder_preferences', 0);
})->with(['getJson', 'putJson', 'patchJson']);

it('defaults native reminders to off without inheriting web preferences or creating a record', function (): void {
    $user = User::factory()->create([
        'daily_reading_reminder_enabled_at' => now(),
        'streak_warning_enabled_at' => now(),
        'push_notification_timezone' => 'Europe/Paris',
    ]);

    $this->withToken($user->createToken('Android', ['mobile'])->plainTextToken)
        ->getJson(NATIVE_REMINDER_PREFERENCES_ENDPOINT)
        ->assertOk()
        ->assertExactJson(['data' => ['enabled' => false, 'timezone' => null]]);

    $this->assertDatabaseCount('native_reminder_preferences', 0);
});

it('persists enablement repeated saves timezone changes and disablement in one account record', function (string $method): void {
    $user = User::factory()->create();
    $token = $user->createToken('Android', ['mobile'])->plainTextToken;

    foreach ([
        ['enabled' => true, 'timezone' => 'America/Toronto'],
        ['enabled' => true, 'timezone' => 'America/Toronto'],
        ['enabled' => true, 'timezone' => 'Europe/Paris'],
        ['enabled' => false, 'timezone' => 'Europe/Paris'],
    ] as $preference) {
        $this->withToken($token)->{$method}(NATIVE_REMINDER_PREFERENCES_ENDPOINT, $preference)
            ->assertSuccessful()
            ->assertExactJson(['data' => $preference]);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson(NATIVE_REMINDER_PREFERENCES_ENDPOINT)
            ->assertOk()
            ->assertExactJson(['data' => $preference]);

        $this->assertDatabaseCount('native_reminder_preferences', 1);
        $this->assertDatabaseHas('native_reminder_preferences', ['user_id' => $user->id, ...$preference]);
    }
})->with(['putJson', 'patchJson']);

it('returns 422 for missing required preferences without saving', function (): void {
    $user = User::factory()->create();

    $this->withToken($user->createToken('Android', ['mobile'])->plainTextToken)
        ->putJson(NATIVE_REMINDER_PREFERENCES_ENDPOINT, [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['enabled', 'timezone']);

    $this->assertDatabaseCount('native_reminder_preferences', 0);
});

it('returns 422 for invalid preferences without altering saved state', function (array $invalid, string $field): void {
    $user = User::factory()->create();
    NativeReminderPreference::factory()->for($user)->create(['enabled' => true]);

    $this->withToken($user->createToken('Android', ['mobile'])->plainTextToken)
        ->putJson(NATIVE_REMINDER_PREFERENCES_ENDPOINT, [
            'enabled' => false,
            'timezone' => 'Europe/Paris',
            ...$invalid,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    $this->assertDatabaseHas('native_reminder_preferences', [
        'user_id' => $user->id,
        'enabled' => true,
        'timezone' => 'America/Toronto',
    ]);
})->with([
    'invalid toggle' => [['enabled' => 'yes'], 'enabled'],
    'null toggle' => [['enabled' => null], 'enabled'],
    'invalid timezone' => [['timezone' => 'Not/AZone'], 'timezone'],
    'null timezone' => [['timezone' => null], 'timezone'],
    'non-string timezone' => [['timezone' => ['Europe/Paris']], 'timezone'],
]);

it('reads and writes only the authenticated account even when another user id is supplied', function (): void {
    $user = User::factory()->create();
    $other = NativeReminderPreference::factory()->create(['enabled' => true, 'timezone' => 'Europe/Paris']);
    $token = $user->createToken('Android', ['mobile'])->plainTextToken;

    $this->withToken($token)
        ->getJson(NATIVE_REMINDER_PREFERENCES_ENDPOINT.'?user_id='.$other->user_id)
        ->assertOk()
        ->assertExactJson(['data' => ['enabled' => false, 'timezone' => null]]);

    $this->withToken($token)->putJson(NATIVE_REMINDER_PREFERENCES_ENDPOINT, [
        'user_id' => $other->user_id,
        'enabled' => false,
        'timezone' => 'America/Toronto',
    ])->assertSuccessful();

    $this->assertDatabaseHas('native_reminder_preferences', [
        'user_id' => $user->id, 'enabled' => false, 'timezone' => 'America/Toronto',
    ]);
    $this->assertDatabaseHas('native_reminder_preferences', [
        'user_id' => $other->user_id, 'enabled' => true, 'timezone' => 'Europe/Paris',
    ]);
});

it('leaves web preferences and subscriptions unchanged when native preferences are saved', function (bool $enabled): void {
    $user = User::factory()->create([
        'push_notifications_enabled_at' => now()->subDay(),
        'daily_reading_reminder_enabled_at' => now()->subDay(),
        'streak_warning_enabled_at' => null,
        'push_notification_timezone' => 'Europe/Paris',
    ]);
    $user->updatePushSubscription('https://example.com/browser', 'key', 'token', 'aes128gcm');
    $webPreferences = $user->fresh()->only([
        'push_notifications_enabled_at', 'daily_reading_reminder_enabled_at',
        'streak_warning_enabled_at', 'push_notification_timezone',
    ]);
    $subscriptions = $user->pushSubscriptions()->get()->toArray();

    $this->withToken($user->createToken('Android', ['mobile'])->plainTextToken)
        ->putJson(NATIVE_REMINDER_PREFERENCES_ENDPOINT, [
            'enabled' => $enabled,
            'timezone' => 'America/Toronto',
            'daily_reading_reminder_enabled_at' => null,
            'push_notification_timezone' => 'UTC',
        ])->assertSuccessful();

    expect($user->fresh()->only(array_keys($webPreferences)))->toEqual($webPreferences);
    expect($user->pushSubscriptions()->get()->toArray())->toBe($subscriptions);
})->with([true, false]);

it('leaves native preferences unchanged when web settings are updated', function (): void {
    $preference = NativeReminderPreference::factory()->create(['enabled' => true]);

    $this->actingAs($preference->user)->patch(route('settings.update'), [
        'include_deuterocanonical' => '0',
        'daily_reading_reminder_enabled' => '0',
        'streak_warning_enabled' => '0',
        'push_notification_timezone' => 'Europe/Paris',
        'enabled' => false,
        'timezone' => 'UTC',
    ])->assertRedirect(route('settings.edit'));

    $this->assertDatabaseHas('native_reminder_preferences', [
        'user_id' => $preference->user_id, 'enabled' => true, 'timezone' => 'America/Toronto',
    ]);
});

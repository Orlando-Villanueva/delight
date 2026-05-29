<?php

use App\Models\User;

function pushSubscriptionPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'endpoint' => 'https://updates.push.services.mozilla.com/wpush/v2/test-endpoint',
        'keys' => [
            'p256dh' => str_repeat('a', 88),
            'auth' => str_repeat('b', 24),
        ],
        'contentEncoding' => 'aes128gcm',
        'timezone' => 'America/Toronto',
    ], $overrides);
}

it('requires authentication to store a push subscription', function () {
    $this->postJson(route('push.subscriptions.store'), pushSubscriptionPayload())
        ->assertUnauthorized();
});

it('stores a subscription and enables default reading reminder preferences', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('push.subscriptions.store'), pushSubscriptionPayload())
        ->assertSuccessful()
        ->assertJsonPath('device_enabled', true)
        ->assertJsonPath('account_has_devices', true)
        ->assertJsonPath('subscription_count', 1);

    $freshUser = $user->fresh();

    expect($freshUser->pushSubscriptions()->count())->toBe(1)
        ->and($freshUser->push_notifications_enabled_at)->not->toBeNull()
        ->and($freshUser->daily_reading_reminder_enabled_at)->not->toBeNull()
        ->and($freshUser->streak_warning_enabled_at)->not->toBeNull()
        ->and($freshUser->push_notification_timezone)->toBe('America/Toronto');
});

it('reports whether the current browser endpoint is connected to this account', function () {
    $user = User::factory()->create([
        'push_notifications_enabled_at' => now(),
        'daily_reading_reminder_enabled_at' => now(),
        'streak_warning_enabled_at' => now(),
        'push_notification_timezone' => 'America/Toronto',
    ]);
    $user->updatePushSubscription('https://example.com/phone', 'key', 'token', 'aes128gcm');

    $this->actingAs($user)
        ->postJson(route('push.subscriptions.status'), [
            'endpoint' => 'https://example.com/desktop',
        ])
        ->assertSuccessful()
        ->assertJsonPath('device_enabled', false)
        ->assertJsonPath('account_has_devices', true)
        ->assertJsonPath('subscription_count', 1)
        ->assertJsonPath('daily_reading_reminder_enabled', true)
        ->assertJsonPath('streak_warning_enabled', true)
        ->assertJsonPath('push_notification_timezone', 'America/Toronto');

    $this->actingAs($user)
        ->postJson(route('push.subscriptions.status'), [
            'endpoint' => 'https://example.com/phone',
        ])
        ->assertSuccessful()
        ->assertJsonPath('device_enabled', true)
        ->assertJsonPath('account_has_devices', true)
        ->assertJsonPath('subscription_count', 1);
});

it('stores a second device subscription without replacing the first one', function () {
    $user = User::factory()->create([
        'push_notifications_enabled_at' => now(),
        'daily_reading_reminder_enabled_at' => now(),
        'streak_warning_enabled_at' => now(),
    ]);
    $user->updatePushSubscription('https://example.com/phone', 'key', 'token', 'aes128gcm');

    $this->actingAs($user)
        ->postJson(route('push.subscriptions.store'), pushSubscriptionPayload([
            'endpoint' => 'https://example.com/desktop',
            'timezone' => 'America/New_York',
        ]))
        ->assertSuccessful()
        ->assertJsonPath('device_enabled', true)
        ->assertJsonPath('account_has_devices', true)
        ->assertJsonPath('subscription_count', 2)
        ->assertJsonPath('push_notification_timezone', 'America/New_York');

    expect($user->fresh()->pushSubscriptions()->pluck('endpoint')->all())->toContain(
        'https://example.com/phone',
        'https://example.com/desktop',
    );
});

it('deletes only the current device subscription without clearing account preferences', function () {
    $user = User::factory()->create([
        'push_notifications_enabled_at' => now(),
        'daily_reading_reminder_enabled_at' => now(),
        'streak_warning_enabled_at' => now(),
    ]);
    $user->updatePushSubscription('https://example.com/phone', 'key', 'token', 'aes128gcm');
    $user->updatePushSubscription('https://example.com/desktop', 'key', 'token', 'aes128gcm');

    $this->actingAs($user)
        ->deleteJson(route('push.subscriptions.destroy'), [
            'endpoint' => 'https://example.com/phone',
        ])
        ->assertSuccessful()
        ->assertJsonPath('device_enabled', false)
        ->assertJsonPath('account_has_devices', true)
        ->assertJsonPath('subscription_count', 1);

    $freshUser = $user->fresh();

    expect($freshUser->pushSubscriptions()->pluck('endpoint')->all())->toBe(['https://example.com/desktop'])
        ->and($freshUser->push_notifications_enabled_at)->not->toBeNull()
        ->and($freshUser->hasDailyReadingReminderEnabled())->toBeTrue()
        ->and($freshUser->hasStreakWarningEnabled())->toBeTrue();
});

it('clears the account connected marker when the last device subscription is deleted', function () {
    $user = User::factory()->create([
        'push_notifications_enabled_at' => now(),
        'daily_reading_reminder_enabled_at' => now(),
        'streak_warning_enabled_at' => now(),
    ]);
    $user->updatePushSubscription('https://example.com/subscription', 'key', 'token', 'aes128gcm');

    $this->actingAs($user)
        ->deleteJson(route('push.subscriptions.destroy'), [
            'endpoint' => 'https://example.com/subscription',
        ])
        ->assertSuccessful()
        ->assertJsonPath('device_enabled', false)
        ->assertJsonPath('account_has_devices', false)
        ->assertJsonPath('subscription_count', 0)
        ->assertJsonPath('daily_reading_reminder_enabled', true)
        ->assertJsonPath('streak_warning_enabled', true);

    $freshUser = $user->fresh();

    expect($freshUser->push_notifications_enabled_at)->toBeNull()
        ->and($freshUser->hasDailyReadingReminderEnabled())->toBeTrue()
        ->and($freshUser->hasStreakWarningEnabled())->toBeTrue()
        ->and($freshUser->pushSubscriptions()->count())->toBe(0);
});

it('can disconnect reminders from every device without clearing account schedule preferences', function () {
    $user = User::factory()->create([
        'push_notifications_enabled_at' => now(),
        'daily_reading_reminder_enabled_at' => now(),
        'streak_warning_enabled_at' => now(),
    ]);
    $user->updatePushSubscription('https://example.com/phone', 'key', 'token', 'aes128gcm');
    $user->updatePushSubscription('https://example.com/desktop', 'key', 'token', 'aes128gcm');

    $this->actingAs($user)
        ->deleteJson(route('push.subscriptions.destroy-all'))
        ->assertSuccessful()
        ->assertJsonPath('device_enabled', false)
        ->assertJsonPath('account_has_devices', false)
        ->assertJsonPath('subscription_count', 0)
        ->assertJsonPath('daily_reading_reminder_enabled', true)
        ->assertJsonPath('streak_warning_enabled', true);

    $freshUser = $user->fresh();

    expect($freshUser->push_notifications_enabled_at)->toBeNull()
        ->and($freshUser->hasDailyReadingReminderEnabled())->toBeTrue()
        ->and($freshUser->hasStreakWarningEnabled())->toBeTrue()
        ->and($freshUser->pushSubscriptions()->count())->toBe(0);
});

it('updates reading reminder preferences independently', function () {
    $user = User::factory()->create([
        'push_notifications_enabled_at' => now(),
        'daily_reading_reminder_enabled_at' => now(),
        'streak_warning_enabled_at' => now(),
        'push_notification_timezone' => 'America/Toronto',
    ]);
    $user->updatePushSubscription('https://example.com/subscription', 'key', 'token', 'aes128gcm');

    $this->actingAs($user)
        ->patchJson(route('push.preferences.update'), [
            'daily_reading_reminder_enabled' => false,
            'timezone' => 'America/New_York',
        ])
        ->assertSuccessful()
        ->assertJsonPath('enabled', true)
        ->assertJsonPath('daily_reading_reminder_enabled', false)
        ->assertJsonPath('streak_warning_enabled', true)
        ->assertJsonPath('push_notification_timezone', 'America/New_York');

    $freshUser = $user->fresh();

    expect($freshUser->hasDailyReadingReminderEnabled())->toBeFalse()
        ->and($freshUser->hasStreakWarningEnabled())->toBeTrue()
        ->and($freshUser->push_notification_timezone)->toBe('America/New_York')
        ->and($freshUser->pushSubscriptions()->count())->toBe(1);
});

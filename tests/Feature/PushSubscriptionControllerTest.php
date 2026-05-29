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
        ->assertJsonPath('enabled', true);

    $freshUser = $user->fresh();

    expect($freshUser->pushSubscriptions()->count())->toBe(1)
        ->and($freshUser->push_notifications_enabled_at)->not->toBeNull()
        ->and($freshUser->daily_reading_reminder_enabled_at)->not->toBeNull()
        ->and($freshUser->streak_warning_enabled_at)->not->toBeNull()
        ->and($freshUser->push_notification_timezone)->toBe('America/Toronto');
});

it('deletes the current device subscription without clearing account preferences', function () {
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
        ->assertNoContent();

    expect($user->fresh()->pushSubscriptions()->count())->toBe(0)
        ->and($user->fresh()->push_notifications_enabled_at)->not->toBeNull();
});

it('disables all push preferences when requested', function () {
    $user = User::factory()->create([
        'push_notifications_enabled_at' => now(),
        'daily_reading_reminder_enabled_at' => now(),
        'streak_warning_enabled_at' => now(),
    ]);
    $user->updatePushSubscription('https://example.com/subscription', 'key', 'token', 'aes128gcm');

    $this->actingAs($user)
        ->patchJson(route('push.preferences.update'), [
            'push_notifications_enabled' => false,
        ])
        ->assertSuccessful()
        ->assertJsonPath('enabled', false);

    $freshUser = $user->fresh();

    expect($freshUser->push_notifications_enabled_at)->toBeNull()
        ->and($freshUser->daily_reading_reminder_enabled_at)->toBeNull()
        ->and($freshUser->streak_warning_enabled_at)->toBeNull()
        ->and($freshUser->pushSubscriptions()->count())->toBe(0);
});

it('updates reading reminder preferences independently', function () {
    $user = User::factory()->create([
        'push_notifications_enabled_at' => now(),
        'daily_reading_reminder_enabled_at' => now(),
        'streak_warning_enabled_at' => now(),
        'push_notification_timezone' => 'America/Toronto',
    ]);

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
        ->and($freshUser->push_notification_timezone)->toBe('America/New_York');
});

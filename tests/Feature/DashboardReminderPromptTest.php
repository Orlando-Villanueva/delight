<?php

use App\Models\User;

it('does not show a dashboard discovery prompt when reminders are off', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertSuccessful()
        ->assertDontSee('data-reading-reminders-discovery', false)
        ->assertDontSee('/settings#reading-reminders', false)
        ->assertDontSee('Reading reminders are off')
        ->assertDontSee('Turn on the 09:00 reminder and 18:00 streak warning from Settings.')
        ->assertDontSee('data-reading-reminders-discovery-dismiss', false)
        ->assertDontSee('Notification.requestPermission', false);
});

it('does not show the dashboard prompt when reminders are enabled', function () {
    $user = User::factory()->create([
        'push_notifications_enabled_at' => now(),
    ]);
    $user->updatePushSubscription('https://example.com/subscription', 'key', 'token', 'aes128gcm');

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSuccessful()
        ->assertDontSee('data-reading-reminders-discovery', false);
});

it('does not show the dashboard prompt when only the old account marker remains', function () {
    $user = User::factory()->create([
        'push_notifications_enabled_at' => now(),
    ]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSuccessful()
        ->assertDontSee('data-reading-reminders-discovery', false);
});

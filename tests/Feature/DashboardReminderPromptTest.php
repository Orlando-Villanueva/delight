<?php

use App\Models\User;

it('shows a dismissible dashboard discovery prompt when reminders are off', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertSuccessful()
        ->assertSee('data-reading-reminders-discovery', false)
        ->assertSee('/settings#reading-reminders', false)
        ->assertSee('Reading reminders are off')
        ->assertSee('Turn on the 09:00 reminder and 18:00 streak warning from Settings.')
        ->assertSee('data-reading-reminders-discovery-dismiss', false)
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

it('shows the dashboard prompt when only the old account marker remains', function () {
    $user = User::factory()->create([
        'push_notifications_enabled_at' => now(),
    ]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('data-reading-reminders-discovery', false);
});

it('persists dismissal of the dashboard prompt', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('push.dashboard-prompt.dismiss'))
        ->assertNoContent();

    expect($user->fresh()->reading_reminders_prompt_dismissed_at)->not->toBeNull();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSuccessful()
        ->assertDontSee('data-reading-reminders-discovery', false);
});

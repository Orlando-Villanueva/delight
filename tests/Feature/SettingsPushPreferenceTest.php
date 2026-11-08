<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('shows reading reminder settings with explicit enable control and support guidance', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('settings.edit'));

    $response->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Settings', false)
            ->where('push.accountHasDevices', false)
            ->where('push.statusUrl', route('push.subscriptions.status'))
            ->where('values.daily_reading_reminder_enabled', false)
            ->where('values.streak_warning_enabled', false)->etc());
});

it('updates reading reminder preferences without changing the deuterocanonical setting', function () {
    $user = User::factory()->create([
        'deuterocanonical_books_enabled_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($user)->patch(route('settings.update'), [
        'include_deuterocanonical' => '1',
        'daily_reading_reminder_enabled' => '1',
        'streak_warning_enabled' => '0',
        'reading_timezone' => 'America/Toronto',
    ]);

    $response->assertRedirect(route('settings.edit'))
        ->assertSessionHas('status', 'Settings saved.');

    $freshUser = $user->fresh();

    expect($freshUser->includesDeuterocanonicalBooks())->toBeTrue()
        ->and($freshUser->daily_reading_reminder_enabled_at)->not->toBeNull()
        ->and($freshUser->streak_warning_enabled_at)->toBeNull()
        ->and($freshUser->reading_timezone)->toBe('America/Toronto');
});

it('keeps reminder preferences disabled when unchecked', function () {
    $user = User::factory()->create([
        'push_notifications_enabled_at' => now(),
        'daily_reading_reminder_enabled_at' => now(),
        'streak_warning_enabled_at' => now(),
        'reading_timezone' => 'America/Toronto',
    ]);

    $response = $this->actingAs($user)->patch(route('settings.update'), [
        'include_deuterocanonical' => '0',
        'daily_reading_reminder_enabled' => '0',
        'streak_warning_enabled' => '0',
    ]);

    $response->assertRedirect(route('settings.edit'));

    $freshUser = $user->fresh();

    expect($freshUser->daily_reading_reminder_enabled_at)->toBeNull()
        ->and($freshUser->streak_warning_enabled_at)->toBeNull()
        ->and($freshUser->reading_timezone)->toBe('America/Toronto');
});

it('does not render this browser as enabled from the account-level connected marker', function () {
    $user = User::factory()->create([
        'push_notifications_enabled_at' => now(),
        'daily_reading_reminder_enabled_at' => now(),
        'streak_warning_enabled_at' => now(),
    ]);
    $user->updatePushSubscription('https://example.com/phone', 'key', 'token', 'aes128gcm');

    $this->actingAs($user)->get(route('settings.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Settings', false)
            ->where('push.accountHasDevices', true)
            ->missing('push.deviceEnabled')
            ->where('values.daily_reading_reminder_enabled', true)->etc());
});

it('provides the save confirmation to the JavaScript settings page', function () {
    $user = User::factory()->create();

    $this->withSession(['status' => 'Settings saved.'])
        ->actingAs($user)
        ->get(route('settings.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Settings', false)
            ->where('status', 'Settings saved.')->etc());
});

it('renders account preferences without individual autosave hooks', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('settings.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Settings', false)
            ->where('updateUrl', route('settings.update'))
            ->where('values.include_deuterocanonical', false)
            ->where('values.daily_reading_reminder_enabled', false)
            ->where('values.streak_warning_enabled', false)->etc());
});

it('uses a single browser-state toggle for reminder visibility', function () {
    $javascript = file_get_contents(resource_path('js/components/browser-reminders.js'));

    expect($javascript)->toContain("reminderToggle.setAttribute('aria-checked'")
        ->and($javascript)->toContain("reminderToggle?.addEventListener('click'")
        ->and($javascript)->toContain('showPermissionGrantedButDisconnected')
        ->and($javascript)->not->toContain('Enable reading reminders for this browser')
        ->and($javascript)->not->toContain('Disable reading reminders for this browser')
        ->and($javascript)->toContain('Notifications are allowed. Turn this on to connect this browser to Delight reminders.')
        ->and($javascript)->toContain('showInlineStatus(preferenceStatus, message')
        ->and($javascript)->toContain('requestPermissionWithTimeout')
        ->and($javascript)->toContain('readyServiceWorkerRegistration')
        ->and($javascript)->toContain('currentPushSubscription')
        ->and($javascript)->toContain('syncCurrentDeviceState')
        ->and($javascript)->toContain('reminderSettings.dataset.statusUrl')
        ->and($javascript)->toContain('reminderSettings.dataset.unsubscribeUrl')
        ->and($javascript)->toContain('reminderSettings.dataset.disconnectAllUrl')
        ->and($javascript)->toContain("navigator.serviceWorker.register('/sw.js', { scope: '/' })")
        ->and($javascript)->toContain('Reading reminder setup failed')
        ->and($javascript)->toContain('Browser could not create a push subscription.')
        ->and($javascript)->toContain('browser push service may be blocked or unavailable')
        ->and($javascript)->not->toContain('push_notifications_enabled: false')
        ->and($javascript)->not->toContain('refreshServiceWorkerSubscription')
        ->and($javascript)->not->toContain('navigator.serviceWorker.getRegistration')
        ->and($javascript)->not->toContain('unregister()')
        ->and($javascript)->not->toContain('data-reading-reminders-enable')
        ->and($javascript)->not->toContain('data-reading-reminders-disable')
        ->and($javascript)->not->toContain('subscription.unsubscribe()');
});

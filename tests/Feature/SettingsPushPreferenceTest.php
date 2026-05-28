<?php

use App\Models\User;

it('shows reading reminder settings with explicit enable control and support guidance', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('settings.edit'));

    $response->assertSuccessful()
        ->assertSee('id="reading-reminders"', false)
        ->assertSee('data-reminders-enabled="false"', false)
        ->assertSee('Reading reminders')
        ->assertSee('Enable reading reminders')
        ->assertSee('data-reading-reminders-toggle', false)
        ->assertSee('role="switch"', false)
        ->assertSee('aria-label="Enable reading reminders"', false)
        ->assertSee('aria-checked="false"', false)
        ->assertSee('data-reading-reminders-toggle-label', false)
        ->assertSee('data-reading-reminders-status hidden', false)
        ->assertSee('Disabled')
        ->assertSee('data-reading-reminders-progress hidden', false)
        ->assertSee('data-reading-reminders-blocked hidden', false)
        ->assertSee('data-reading-reminders-error hidden', false)
        ->assertSee('data-reading-reminders-preference', false)
        ->assertSee('data-push-public-key', false)
        ->assertSee('Safari -> Add to Home Screen -> open Delight from the Home Screen icon -> enable notifications', false)
        ->assertSee('Schedule')
        ->assertDontSee('Browser notifications can remind you at 09:00', false)
        ->assertDontSee('Both reminders are included when browser notifications are enabled.');
});

it('updates reading reminder preferences without changing the deuterocanonical setting', function () {
    $user = User::factory()->create([
        'deuterocanonical_books_enabled_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($user)->patch(route('settings.update'), [
        'include_deuterocanonical' => '1',
        'daily_reading_reminder_enabled' => '1',
        'streak_warning_enabled' => '0',
        'push_notification_timezone' => 'America/Toronto',
    ]);

    $response->assertRedirect(route('settings.edit'))
        ->assertSessionHas('status', 'Settings saved.');

    $freshUser = $user->fresh();

    expect($freshUser->includesDeuterocanonicalBooks())->toBeTrue()
        ->and($freshUser->daily_reading_reminder_enabled_at)->not->toBeNull()
        ->and($freshUser->streak_warning_enabled_at)->toBeNull()
        ->and($freshUser->push_notification_timezone)->toBe('America/Toronto');
});

it('keeps reminder preferences disabled when unchecked', function () {
    $user = User::factory()->create([
        'push_notifications_enabled_at' => now(),
        'daily_reading_reminder_enabled_at' => now(),
        'streak_warning_enabled_at' => now(),
        'push_notification_timezone' => 'America/Toronto',
    ]);

    $response = $this->actingAs($user)->patch(route('settings.update'), [
        'include_deuterocanonical' => '0',
        'daily_reading_reminder_enabled' => '0',
        'streak_warning_enabled' => '0',
        'push_notification_timezone' => '',
    ]);

    $response->assertRedirect(route('settings.edit'));

    $freshUser = $user->fresh();

    expect($freshUser->daily_reading_reminder_enabled_at)->toBeNull()
        ->and($freshUser->streak_warning_enabled_at)->toBeNull()
        ->and($freshUser->push_notification_timezone)->toBe(config('app.timezone'));
});

it('shows the reminder toggle enabled when browser notifications are enabled', function () {
    $user = User::factory()->create([
        'push_notifications_enabled_at' => now(),
    ]);

    $this->actingAs($user)->get(route('settings.edit'))
        ->assertSuccessful()
        ->assertSee('data-reminders-enabled="true"', false)
        ->assertSee('data-reading-reminders-status hidden', false)
        ->assertSee('data-reading-reminders-toggle', false)
        ->assertSee('aria-checked="true"', false)
        ->assertSee('Enabled')
        ->assertDontSee('This browser can receive reading reminders.');
});

it('places the settings saved status in the submit footer', function () {
    $user = User::factory()->create();

    $this->withSession(['status' => 'Settings saved.'])
        ->actingAs($user)
        ->get(route('settings.edit'))
        ->assertSuccessful()
        ->assertSee('role="status"', false)
        ->assertSee('aria-live="polite"', false)
        ->assertSeeInOrder([
            'data-push-timezone',
            'Settings saved.',
            'Save settings',
        ], false);
});

it('keeps the save button aligned right when there is no status message', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.edit'))
        ->assertSuccessful()
        ->assertSee('sm:grid-cols-[minmax(0,1fr)_auto]', false)
        ->assertSee('justify-self-end', false)
        ->assertDontSee('Settings saved.');
});

it('uses a single browser-state toggle for reminder visibility', function () {
    $javascript = file_get_contents(resource_path('js/app.js'));

    expect($javascript)->toContain("reminderToggle.setAttribute('aria-checked'")
        ->and($javascript)->toContain("reminderToggle?.addEventListener('click'")
        ->and($javascript)->toContain('prompt.hidden = true')
        ->and($javascript)->toContain('Notification.permission === \'denied\'')
        ->and($javascript)->toContain('showPermissionGrantedButDisconnected')
        ->and($javascript)->toContain('Notifications are allowed. Turn this on to connect this browser to Delight reminders.')
        ->and($javascript)->toContain('initializeReadingRemindersDiscovery')
        ->and($javascript)->toContain("prompt.dataset.readingRemindersDiscoveryInitialized = 'true'")
        ->and($javascript)->toContain("document.body.addEventListener('htmx:afterSwap'")
        ->and($javascript)->toContain("target.id !== 'page-container'")
        ->and($javascript)->toContain('requestPermissionWithTimeout')
        ->and($javascript)->toContain('readyServiceWorkerRegistration')
        ->and($javascript)->toContain("navigator.serviceWorker.register('/sw.js', { scope: '/' })")
        ->and($javascript)->toContain('Reading reminder setup failed')
        ->and($javascript)->toContain('Browser could not create a push subscription.')
        ->and($javascript)->toContain('browser push service may be blocked or unavailable')
        ->and($javascript)->not->toContain('refreshServiceWorkerSubscription')
        ->and($javascript)->not->toContain('navigator.serviceWorker.getRegistration')
        ->and($javascript)->not->toContain('unregister()')
        ->and($javascript)->not->toContain('data-reading-reminders-enable')
        ->and($javascript)->not->toContain('data-reading-reminders-disable')
        ->and($javascript)->not->toContain('subscription.unsubscribe()');
});

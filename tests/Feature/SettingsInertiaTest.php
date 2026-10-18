<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('serves settings as an Inertia page without loading legacy frontend runtimes', function () {
    $user = User::factory()->create(['reading_timezone' => 'America/Toronto']);
    $this->actingAs($user)->get(route('settings.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Settings', false)
            ->where('reader.user.name', $user->name)
            ->missing('reader.user.password')
            ->missing('values.preferred_locale')->etc())
        ->assertDontSee('htmx.min.js', false)
        ->assertDontSee('alpinejs', false)
        ->assertDontSee('<form', false);
});

it('uses an Inertia redirect after saving and returns confirmed preferences', function () {
    $user = User::factory()->create(['reading_timezone' => 'America/Toronto']);
    $this->actingAs($user)->withHeader('X-Inertia', 'true')
        ->patch(route('settings.update'), ['reading_timezone' => 'Asia/Tokyo', 'daily_reading_reminder_enabled' => true])
        ->assertStatus(303)->assertRedirect(route('settings.edit'));

    $this->withoutHeader('X-Inertia')->get(route('settings.edit'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Settings', false)
            ->where('values.reading_timezone', 'Asia/Tokyo')
            ->where('values.daily_reading_reminder_enabled', true)
            ->where('status', 'Settings saved.')->etc());
    expect($user->fresh()->reading_timezone)->toBe('Asia/Tokyo');
});

it('returns redirected validation errors without persisting invalid settings', function () {
    $user = User::factory()->create(['reading_timezone' => 'America/Toronto']);
    $this->actingAs($user)->withHeader('X-Inertia', 'true')->from(route('settings.edit'))
        ->patch(route('settings.update'), ['reading_timezone' => 'Invalid/Zone', 'daily_reading_reminder_enabled' => true])
        ->assertStatus(303)->assertSessionHasErrors('reading_timezone');
    $this->withoutHeader('X-Inertia')->get(route('settings.edit'))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('errors.reading_timezone')->where('values.daily_reading_reminder_enabled', false)->etc());
    expect($user->fresh()->reading_timezone)->toBe('America/Toronto');
});

it('enters settings from legacy navigation with a document redirect', function () {
    $this->actingAs(User::factory()->create())->withHeader('HX-Request', 'true')
        ->get(route('settings.edit'))->assertOk()->assertHeader('HX-Redirect', route('settings.edit'));
});

it('takes an expired Inertia settings session to login', function () {
    $this->withHeader('X-Inertia', 'true')->get(route('settings.edit'))
        ->assertStatus(409)->assertHeader('X-Inertia-Location', route('login'));
});

it('keeps the reading form on its existing renderer', function () {
    $this->actingAs(User::factory()->create())->get(route('logs.create'))
        ->assertOk()->assertSee('htmx.min.js', false)->assertHeaderMissing('X-Inertia');
});

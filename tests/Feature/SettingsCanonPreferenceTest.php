<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;

it('requires authentication to view settings', function () {
    $this->get(route('settings.edit'))
        ->assertRedirect(route('login'));
});

it('shows the Catholic canon setting as disabled by default', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('settings.edit'));

    $response->assertSuccessful()
        ->assertViewIs('settings.edit')
        ->assertSee('Deuterocanonical books')
        ->assertSee('name="include_deuterocanonical"', false)
        ->assertSee('Disabled');

    expect($user->fresh()->includesDeuterocanonicalBooks())->toBeFalse();
});

it('enables the Catholic canon setting', function () {
    $user = User::factory()->create();
    Cache::put("user_dashboard_stats_{$user->id}", ['total_bible_books' => 66], 300);

    $response = $this->actingAs($user)
        ->patch(route('settings.update'), [
            'include_deuterocanonical' => '1',
        ]);

    $response->assertRedirect(route('settings.edit'))
        ->assertSessionHas('status', 'Settings saved.');

    expect($user->fresh()->includesDeuterocanonicalBooks())->toBeTrue();
    expect(Cache::has("user_dashboard_stats_{$user->id}"))->toBeFalse();
});

it('disables the Catholic canon setting', function () {
    $user = User::factory()->create([
        'deuterocanonical_books_enabled_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->patch(route('settings.update'), [
            'include_deuterocanonical' => '0',
        ]);

    $response->assertRedirect(route('settings.edit'))
        ->assertSessionHas('status', 'Settings saved.');

    expect($user->fresh()->includesDeuterocanonicalBooks())->toBeFalse();
});

it('links to settings from the profile dropdown', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertSuccessful()
        ->assertSee(route('settings.edit'), false)
        ->assertSee('Settings');
});

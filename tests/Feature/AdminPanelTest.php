<?php

use App\Models\User;

beforeEach(function () {
    config(['mail.admin_address' => 'admin@example.com']);
});

it('shows the admin shell and existing destinations to the configured admin', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);

    $this->actingAs($admin)->get('/admin')
        ->assertOk()
        ->assertSee('Welcome to Delight Admin')
        ->assertSee(route('admin.analytics.index'))
        ->assertSee(route('admin.announcements.index'))
        ->assertSee(route('dashboard'))
        ->assertSee('Back to Delight')
        ->assertSee('--default-theme-mode: system', false)
        ->assertSee('/css/filament/filament/app.css', false)
        ->assertDontSee('resources/js/app.js');
});

it('redirects guests to the existing reader login', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});

it('honors the panel access contract even for an admin identity', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $user = Mockery::mock(User::class)->makePartial();
    $user->setRawAttributes($admin->getAttributes(), true);
    $user->exists = true;
    $user->shouldReceive('canAccessPanel')->once()->andReturnFalse();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('forbids signed-in readers even in the local environment', function () {
    config(['app.env' => 'local']);
    $reader = User::factory()->create(['email' => 'reader@example.com']);

    $this->actingAs($reader)->get('/admin')->assertForbidden();
});

it('checks current admin access when the shell makes a Livewire update', function (bool $accessRevoked, int $status) {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $response = $this->actingAs($admin)->get('/admin')->assertOk();
    preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
    $snapshot = html_entity_decode($matches[1], ENT_QUOTES);
    $payload = [
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => [],
            'calls' => [],
        ]],
    ];

    if ($accessRevoked) {
        config(['mail.admin_address' => 'different@example.com']);
    }

    $this->postJson(app('livewire')->getUpdateUri(), $payload, ['X-Livewire' => 'true'])
        ->assertStatus($status);
})->with([
    'authorized admin' => [false, 200],
    'revoked admin' => [true, 403],
]);

it('keeps reader pages separate from Filament assets', function () {
    $reader = User::factory()->create();

    $this->actingAs($reader)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('/css/filament/filament/app.css', false)
        ->assertDontSee('/js/filament/filament/app.js', false);
});

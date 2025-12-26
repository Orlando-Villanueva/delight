<?php

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['mail.admin_address' => 'admin@example.com']);

    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
    ]);
});

it('it_can_show_the_announcement_index_for_admins', function () {
    Announcement::create([
        'title' => 'Weekly Update',
        'slug' => 'weekly-update-123',
        'content' => 'Test content',
        'type' => 'info',
        'starts_at' => now(),
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.announcements.index'));

    $response->assertOk();
    $response->assertSee('Weekly Update');
});

it('it_can_show_the_announcement_create_form_for_admins', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.announcements.create'));

    $response->assertOk();
    $response->assertSee('Create Announcement');
    $response->assertSee('Content');
    $response->assertSee('Markdown');
});

it('it_can_store_announcements_for_admins', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
        'title' => 'New Feature',
        'content' => 'Some markdown content.',
        'type' => 'info',
    ]);

    $response->assertRedirect(route('admin.announcements.index'));
    $response->assertSessionHas('success');

    $announcement = Announcement::first();

    expect($announcement)->not->toBeNull();
    expect($announcement->title)->toBe('New Feature');
    expect($announcement->type)->toBe('info');
    expect(Str::startsWith($announcement->slug, Str::slug('New Feature')))->toBeTrue();
});

it('it_can_render_a_markdown_preview_for_admins', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.announcements.preview'), [
        'content' => "# Hello\n\n**World**",
    ], [
        'HX-Request' => 'true',
    ]);

    $response->assertOk();
    $response->assertSee('<h1>Hello</h1>', false);
    $response->assertSee('<strong>World</strong>', false);
    $response->assertDontSee('<!DOCTYPE html>');
});

it('it_can_render_an_empty_preview_state_for_admins', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.announcements.preview'), [
        'content' => '   ',
    ], [
        'HX-Request' => 'true',
    ]);

    $response->assertOk();
    $response->assertSee('Nothing to preview yet', false);
    $response->assertDontSee('<!DOCTYPE html>');
});

it('it_can_block_non_admins_from_admin_announcement_routes', function (string $method, Closure $route, array $payload = []) {
    $user = User::factory()->create([
        'email' => 'user@example.com',
    ]);

    $response = $this->actingAs($user)->{$method}($route(), $payload, [
        'HX-Request' => 'true',
    ]);

    $response->assertForbidden();
})->with([
    ['get', fn () => route('admin.announcements.index')],
    ['get', fn () => route('admin.announcements.create')],
    ['post', fn () => route('admin.announcements.store'), [
        'title' => 'Blocked',
        'content' => 'Nope',
        'type' => 'info',
    ]],
    ['post', fn () => route('admin.announcements.preview'), [
        'content' => '# Preview',
    ]],
]);

it('it_can_redirect_guests_from_admin_announcement_routes', function (string $method, Closure $route, array $payload = []) {
    $response = $this->{$method}($route(), $payload, [
        'HX-Request' => 'true',
    ]);

    $response->assertRedirect(route('login'));
})->with([
    ['get', fn () => route('admin.announcements.index')],
    ['get', fn () => route('admin.announcements.create')],
    ['post', fn () => route('admin.announcements.store'), []],
    ['post', fn () => route('admin.announcements.preview'), [
        'content' => '# Preview',
    ]],
]);

it('it_can_validate_announcement_creation_inputs', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
        'title' => '',
        'content' => '',
        'type' => '',
    ]);

    $response->assertSessionHasErrors(['title', 'content', 'type']);
});

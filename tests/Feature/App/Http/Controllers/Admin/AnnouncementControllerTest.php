<?php

use App\Models\Announcement;
use App\Models\AnnouncementEmailDelivery;
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
    $response->assertSee('Hero Image Path');
    $response->assertSee('Social Image Path');
    $response->assertSee('Markdown');
});

it('it_can_store_announcements_for_admins', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
        'title' => 'New Feature',
        'content' => 'Some markdown content.',
        'type' => 'info',
        'hero_image_path' => 'images/new-feature-hero.png',
        'social_image_path' => 'images/new-feature-social.jpg',
    ]);

    $response->assertRedirect(route('admin.announcements.index'));
    $response->assertSessionHas('success');

    $announcement = Announcement::first();

    expect($announcement)->not->toBeNull();
    expect($announcement->title)->toBe('New Feature');
    expect($announcement->type)->toBe('info');
    expect($announcement->hero_image_path)->toBe('images/new-feature-hero.png');
    expect($announcement->social_image_path)->toBe('images/new-feature-social.jpg');
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
        'hero_image_path' => 'images/nope.png',
        'social_image_path' => 'images/nope-social.jpg',
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

    $response->assertSessionHasErrors(['title', 'content', 'type', 'hero_image_path']);
});

it('shows announcement email failures and routine recovery on the admin index', function () {
    $announcement = Announcement::factory()->create([
        'title' => 'Delivery status update',
        'email_broadcast_authorized_at' => now(),
        'email_audience_finalized_at' => now(),
    ]);
    $recipient = User::factory()->create();
    AnnouncementEmailDelivery::factory()->create([
        'announcement_id' => $announcement->id,
        'user_id' => $recipient->id,
        'recipient_email' => $recipient->email,
        'attempt_count' => 2,
        'failed_at' => now(),
        'failure_reason' => 'Mailgun unavailable (code 503).',
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.announcements.index'));

    $response->assertOk()
        ->assertSee('Needs attention')
        ->assertSee('1 failed')
        ->assertSee('Mailgun unavailable (code 503).')
        ->assertSee(route('admin.announcements.email-deliveries.retry', $announcement));
});

it('retries only terminally failed recipients for an announcement', function () {
    $announcement = Announcement::factory()->create([
        'email_broadcast_authorized_at' => now(),
        'email_audience_finalized_at' => now(),
    ]);
    $failed = AnnouncementEmailDelivery::factory()->create([
        'announcement_id' => $announcement->id,
        'attempt_count' => 2,
        'failed_at' => now(),
        'failure_reason' => 'Mailgun unavailable (code 503).',
    ]);
    $sent = AnnouncementEmailDelivery::factory()->create([
        'announcement_id' => $announcement->id,
        'sent_at' => now(),
    ]);
    $skipped = AnnouncementEmailDelivery::factory()->create([
        'announcement_id' => $announcement->id,
        'skipped_at' => now(),
    ]);
    $uncertain = AnnouncementEmailDelivery::factory()->create([
        'announcement_id' => $announcement->id,
        'uncertain_at' => now(),
    ]);
    $pending = AnnouncementEmailDelivery::factory()->create([
        'announcement_id' => $announcement->id,
        'next_attempt_at' => now()->addMinutes(5),
    ]);

    $response = $this->actingAs($this->admin)->post(
        route('admin.announcements.email-deliveries.retry', $announcement)
    );

    $response->assertRedirect(route('admin.announcements.index'))
        ->assertSessionHas('success', 'One failed announcement email will be retried.');

    expect($failed->fresh()->failed_at)->toBeNull()
        ->and($failed->fresh()->next_attempt_at)->not->toBeNull()
        ->and($failed->fresh()->failure_reason)->toBe('Mailgun unavailable (code 503).')
        ->and($sent->fresh()->sent_at)->not->toBeNull()
        ->and($skipped->fresh()->skipped_at)->not->toBeNull()
        ->and($uncertain->fresh()->uncertain_at)->not->toBeNull()
        ->and($pending->fresh()->next_attempt_at?->isFuture())->toBeTrue();
});

it('blocks non-admins and guests from retrying failed announcement emails', function () {
    $announcement = Announcement::factory()->create();
    $user = User::factory()->create(['email' => 'reader@example.com']);
    $route = route('admin.announcements.email-deliveries.retry', $announcement);

    $this->actingAs($user)->post($route)->assertForbidden();

    auth()->logout();

    $this->post($route)->assertRedirect(route('login'));
});

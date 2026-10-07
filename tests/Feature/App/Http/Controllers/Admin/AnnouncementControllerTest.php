<?php

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Models\Announcement;
use App\Models\AnnouncementEmailDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

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
        'starts_at' => now(),
    ]);

    $response = $this->actingAs($this->admin)->followingRedirects()->get(route('admin.announcements.index'));

    $response->assertOk();
    $response->assertSee('Weekly Update');
    $response->assertSee(route('announcements.show', 'weekly-update-123'))
        ->assertDontSee(route('admin.announcements.preview', 'weekly-update-123'));
});

it('shows persisted drafts to admins with a protected preview link', function () {
    $announcement = Announcement::factory()->draft()->create([
        'title' => 'Command-created draft',
        'slug' => 'command-created-draft',
        'ends_at' => now()->subMinute(),
    ]);
    $scheduledAnnouncement = Announcement::factory()->create([
        'title' => 'Scheduled announcement',
        'slug' => 'scheduled-announcement',
        'starts_at' => now()->addDay(),
    ]);

    $response = $this->actingAs($this->admin)->followingRedirects()->get(route('admin.announcements.index'));

    $response->assertSee($announcement->title)
        ->assertSee('Draft')
        ->assertSee('Not authorized')
        ->assertSee(route('admin.announcements.preview', $announcement->slug))
        ->assertDontSee(route('announcements.show', $announcement->slug))
        ->assertSee(route('admin.announcements.preview', $scheduledAnnouncement->slug))
        ->assertDontSee(route('announcements.show', $scheduledAnnouncement->slug));
});

it('shows an edit action only for persisted drafts', function () {
    $draft = Announcement::factory()->draft()->create();
    $scheduledAnnouncement = Announcement::factory()->create([
        'starts_at' => now()->addDay(),
    ]);
    $publishedAnnouncement = Announcement::factory()->create();

    $response = $this->actingAs($this->admin)->followingRedirects()->get(route('admin.announcements.index'));

    $response->assertSee(AnnouncementResource::getUrl('edit', ['record' => $draft], panel: 'admin'))
        ->assertDontSee(AnnouncementResource::getUrl('edit', ['record' => $scheduledAnnouncement], panel: 'admin'))
        ->assertDontSee(AnnouncementResource::getUrl('edit', ['record' => $publishedAnnouncement], panel: 'admin'));
});

it('renders a persisted draft preview for admins without side effects', function () {
    Mail::fake();
    $announcement = Announcement::factory()->draft()->create([
        'title' => 'Private release preview',
        'slug' => 'private-release-preview',
        'content' => "# Preview heading\n\n**Preview body**",
        'hero_image_path' => 'images/private-release.png',
        'starts_at' => now()->addDay(),
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.announcements.preview', $announcement->slug));

    $response->assertViewIs('announcements.show')
        ->assertViewHas('announcement', $announcement)
        ->assertSee('Draft preview')
        ->assertSee('This announcement is not publicly visible yet.')
        ->assertSee('Edit draft')
        ->assertSee('<h1>Preview heading</h1>', false)
        ->assertSee('<strong>Preview body</strong>', false)
        ->assertSee('images/private-release.png', false)
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertDontSee('<link rel="canonical"', false);
    expect(substr_count(
        $response->getContent(),
        AnnouncementResource::getUrl('edit', ['record' => $announcement], panel: 'admin')
    ))->toBe(2);
    expect($this->admin->announcements()->whereKey($announcement->id)->exists())->toBeFalse()
        ->and($announcement->emailDeliveries()->count())->toBe(0);
    Mail::assertNothingSent();
});

it('renders a scheduled announcement preview before its publication time', function () {
    $announcement = Announcement::factory()->create([
        'slug' => 'scheduled-release-preview',
        'starts_at' => now()->addDay(),
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.announcements.preview', $announcement->slug));

    $response->assertSee('Scheduled preview')
        ->assertSee('This announcement is not publicly visible yet.')
        ->assertDontSee('Edit draft')
        ->assertDontSee(AnnouncementResource::getUrl('edit', ['record' => $announcement], panel: 'admin'));
    $this->get(route('announcements.show', $announcement->slug))->assertNotFound();
});

it('redirects a publicly reachable announcement preview to its publication URL', function () {
    $announcement = Announcement::factory()->create([
        'slug' => 'published-release',
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->subSecond(),
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.announcements.preview', $announcement->slug));

    $response->assertRedirectToRoute('announcements.show', [
        'slug' => $announcement->slug,
    ]);
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

    $response = $this->actingAs($this->admin)->followingRedirects()->get(route('admin.announcements.index'));

    $response->assertOk()
        ->assertSee('Needs attention')
        ->assertSee('1 failed')
        ->assertDontSee('Mailgun unavailable (code 503).')
        ->assertDontSee(route('admin.announcements.email-deliveries.retry', $announcement));
});

it('shows live announcement email progress and polls while delivery is active', function () {
    $announcement = Announcement::factory()->create([
        'email_broadcast_authorized_at' => now()->subMinutes(6),
        'email_audience_finalized_at' => now()->subMinutes(5),
    ]);
    AnnouncementEmailDelivery::factory()->count(2)->create([
        'announcement_id' => $announcement->id,
        'sent_at' => now()->subMinute(),
    ]);
    AnnouncementEmailDelivery::factory()->create([
        'announcement_id' => $announcement->id,
    ]);

    $response = $this->actingAs($this->admin)->followingRedirects()->get(route('admin.announcements.index'));

    $response->assertOk()
        ->assertSee('Pending recipients')
        ->assertSee('2 of 3 handled')
        ->assertSee('1 pending')
        ->assertSee('wire:poll.15s', false);
});

it('does not poll for email progress before a scheduled announcement is published', function () {
    Announcement::factory()->create([
        'starts_at' => now()->addDay(),
        'email_broadcast_authorized_at' => now(),
    ]);

    $response = $this->actingAs($this->admin)->followingRedirects()->get(route('admin.announcements.index'));

    $response->assertOk()
        ->assertSee('Scheduled')
        ->assertDontSee('wire:poll.15s', false);
});

it('shows completed broadcast summary and stops polling', function () {
    $announcement = Announcement::factory()->create([
        'email_broadcast_authorized_at' => now()->subMinutes(11),
        'email_audience_finalized_at' => now()->subMinutes(10),
        'email_broadcast_completed_at' => now(),
    ]);
    AnnouncementEmailDelivery::factory()->create([
        'announcement_id' => $announcement->id,
        'sent_at' => now()->subMinute(),
    ]);

    $response = $this->actingAs($this->admin)->followingRedirects()->get(route('admin.announcements.index'));

    $response->assertOk()
        ->assertSee('Processing completed')
        ->assertSee('1 transport-submitted')
        ->assertDontSee('Delivered')
        ->assertSee('1 of 1 handled')
        ->assertDontSee('wire:poll.15s', false);
});

it('shows pending recipients without inferring a provider delay', function () {
    $announcement = Announcement::factory()->create([
        'email_broadcast_authorized_at' => now()->subMinutes(17),
        'email_audience_finalized_at' => now()->subMinutes(16),
    ]);
    AnnouncementEmailDelivery::factory()->create([
        'announcement_id' => $announcement->id,
    ]);

    $response = $this->actingAs($this->admin)->followingRedirects()->get(route('admin.announcements.index'));

    $response->assertOk()
        ->assertSee('Pending recipients')
        ->assertDontSee('Delayed')
        ->assertSee('0 of 1 handled')
        ->assertSee('1 pending');
});

it('retries only terminally failed recipients for an announcement', function () {
    $announcement = Announcement::factory()->create([
        'email_broadcast_authorized_at' => now(),
        'email_audience_finalized_at' => now(),
        'email_broadcast_completed_at' => now(),
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
        ->and($pending->fresh()->next_attempt_at?->isFuture())->toBeTrue()
        ->and($announcement->fresh()->email_broadcast_completed_at)->toBeNull();
});

it('blocks non-admins and guests from retrying failed announcement emails', function () {
    $announcement = Announcement::factory()->create();
    $user = User::factory()->create(['email' => 'reader@example.com']);
    $route = route('admin.announcements.email-deliveries.retry', $announcement);

    $this->actingAs($user)->post($route)->assertForbidden();

    auth()->logout();

    $this->post($route)->assertRedirect(route('login'));
});

it('shows historical email records without authorizing or changing failed recipients', function () {
    $this->freezeTime();
    Mail::fake();
    $announcement = Announcement::factory()->create();
    $delivery = AnnouncementEmailDelivery::factory()->for($announcement)->create([
        'failed_at' => now(),
        'attempt_count' => 2,
        'failure_reason' => 'Permanent SMTP rejection',
    ]);
    $before = $delivery->fresh()->getRawOriginal();

    $response = $this->actingAs($this->admin)->followingRedirects()->get(route('admin.announcements.index'));

    $response->assertSee('Historical email records')
        ->assertSee('Email not authorized')
        ->assertSee('1 failed')
        ->assertDontSee('Permanent SMTP rejection')
        ->assertDontSee('wire:poll.15s', false);
    expect($delivery->fresh()->getRawOriginal())->toBe($before);
    expect($announcement->fresh()->email_broadcast_authorized_at)->toBeNull();
    Mail::assertNothingSent();
});

it('shows a historical processing milestone without inventing a duration or a submitted recipient', function () {
    $this->freezeTime();
    $announcement = Announcement::factory()->create(['email_broadcast_completed_at' => now()->subMinute()]);

    $response = $this->actingAs($this->admin)->followingRedirects()->get(route('admin.announcements.index'));

    $response->assertSee('Historical email records')
        ->assertSee('Completed processing')
        ->assertDontSee('Completed in')
        ->assertDontSee('transport-submitted');
});
it('redirects legacy draft authoring links to Filament', function () {
    $draft = Announcement::factory()->draft()->create();
    $this->actingAs($this->admin);

    $this->get(route('admin.announcements.create'))->assertRedirect(AnnouncementResource::getUrl('create', panel: 'admin'));
    $this->get(route('admin.announcements.edit', $draft))->assertRedirect(AnnouncementResource::getUrl('edit', ['record' => $draft], panel: 'admin'));
    $this->followingRedirects()->get(route('admin.announcements.edit', $draft))->assertSee('Edit announcement draft')->assertSee($draft->title);
});

it('rejects legacy edit links for published scheduled and missing announcements', function () {
    $this->actingAs($this->admin);
    $published = Announcement::factory()->create();
    $scheduled = Announcement::factory()->create(['starts_at' => now()->addDay()]);

    $this->get(route('admin.announcements.edit', $published))->assertNotFound();
    $this->get(route('admin.announcements.edit', $scheduled))->assertNotFound();
    $this->get('/admin/announcements/999999/edit')->assertNotFound();
});

it('protects retained legacy navigation and preview routes', function (string $routeName) {
    $draft = Announcement::factory()->draft()->create();
    $url = route($routeName, $routeName === 'admin.announcements.preview' ? $draft->slug : $draft);

    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
})->with(['admin.announcements.index', 'admin.announcements.create', 'admin.announcements.edit', 'admin.announcements.preview']);

it('retires legacy authoring mutations without changing records', function (string $method, string $path) {
    $draft = Announcement::factory()->draft()->create();
    $before = $draft->fresh()->getRawOriginal();
    $this->actingAs($this->admin);
    Mail::fake();

    $response = $this->{$method}(str_replace('{id}', (string) $draft->id, $path), [
        'title' => 'Unwanted publication', 'content' => 'Changed content', 'is_draft' => false,
    ]);

    expect(in_array($response->status(), [404, 405], true))->toBeTrue();
    expect($draft->fresh()->getRawOriginal())->toBe($before);
    $this->assertDatabaseCount('announcements', 1);
    Mail::assertNothingSent();
})->with([
    ['post', '/admin/announcements'],
    ['put', '/admin/announcements/{id}'],
    ['patch', '/admin/announcements/{id}'],
    ['post', '/admin/announcements/preview-markdown'],
]);

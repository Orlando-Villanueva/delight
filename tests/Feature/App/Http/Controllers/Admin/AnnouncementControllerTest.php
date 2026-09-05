<?php

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

    $response = $this->actingAs($this->admin)->get(route('admin.announcements.index'));

    $response->assertOk();
    $response->assertSee('Weekly Update');
    $response->assertSee(route('announcements.show', 'weekly-update-123'))
        ->assertDontSee(route('admin.announcements.preview', 'weekly-update-123'));
});

it('it_can_show_the_announcement_create_form_for_admins', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.announcements.create'));

    $response->assertOk();
    $response->assertSee('Create Announcement');
    $response->assertSee('Content');
    $response->assertSee('Hero Image Path');
    $response->assertSee('Social Image Path');
    $response->assertSee('Publication Slug');
    $response->assertSee('Leave blank to');
    $response->assertSee('generate it from the title.');
    $response->assertSee('Markdown');
    $response->assertDontSee('name="type"', false);
    $response->assertSee('Publishing authorizes an email to every eligible user.');
    $response->assertSee('Publish or schedule announcement');
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

    $response = $this->actingAs($this->admin)->get(route('admin.announcements.index'));

    $response->assertSee($announcement->title)
        ->assertSee('Draft')
        ->assertSee('Not enabled')
        ->assertDontSee('Expired')
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

    $response = $this->actingAs($this->admin)->get(route('admin.announcements.index'));

    $response->assertSee(route('admin.announcements.edit', $draft))
        ->assertDontSee(route('admin.announcements.edit', $scheduledAnnouncement))
        ->assertDontSee(route('admin.announcements.edit', $publishedAnnouncement));
});

it('renders a persisted draft in the announcement edit form', function () {
    $announcement = Announcement::factory()->draft()->create([
        'title' => 'Editable announcement draft',
        'slug' => 'editable-announcement-draft',
        'content' => 'Original draft content.',
        'hero_image_path' => 'images/original-hero.png',
        'social_image_path' => 'images/original-social.png',
        'starts_at' => '2026-09-10 09:00:00',
        'ends_at' => '2026-09-17 09:00:00',
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.announcements.edit', $announcement));

    $response->assertSee('Edit Announcement Draft')
        ->assertSee('value="Editable announcement draft"', false)
        ->assertSee('value="editable-announcement-draft"', false)
        ->assertSee('Original draft content.')
        ->assertSee('value="images/original-hero.png"', false)
        ->assertSee('value="images/original-social.png"', false)
        ->assertSee('value="2026-09-10T09:00"', false)
        ->assertSee('value="2026-09-17T09:00"', false)
        ->assertSee('Saving keeps this announcement as a private draft.')
        ->assertSee('Save draft changes')
        ->assertSee('hx-include="closest form"', false)
        ->assertSee('hx-params="not _method"', false);
});

it('updates a persisted draft without publishing or authorizing email', function () {
    Mail::fake();
    $announcement = Announcement::factory()->draft()->create([
        'title' => 'Original title',
        'slug' => 'original-title',
        'content' => 'Original content.',
        'hero_image_path' => 'images/original.png',
    ]);

    $response = $this->actingAs($this->admin)->put(route('admin.announcements.update', $announcement), [
        'title' => 'Revised title',
        'slug' => 'Revised Editorial URL',
        'content' => 'Revised content.',
        'hero_image_path' => 'images/revised.png',
        'social_image_path' => 'images/revised-social.png',
        'starts_at' => '2026-09-10T09:00',
        'ends_at' => '2026-09-17T09:00',
        'is_draft' => false,
        'email_broadcast_authorized_at' => now(),
    ]);

    $response->assertRedirectToRoute('admin.announcements.preview', [
        'announcement' => 'revised-editorial-url',
    ])->assertSessionHas('success', 'Announcement draft updated.');

    $announcement->refresh();

    expect($announcement->title)->toBe('Revised title')
        ->and($announcement->slug)->toBe('revised-editorial-url')
        ->and($announcement->content)->toBe('Revised content.')
        ->and($announcement->hero_image_path)->toBe('images/revised.png')
        ->and($announcement->social_image_path)->toBe('images/revised-social.png')
        ->and($announcement->starts_at->format('Y-m-d H:i'))->toBe('2026-09-10 09:00')
        ->and($announcement->ends_at->format('Y-m-d H:i'))->toBe('2026-09-17 09:00')
        ->and($announcement->is_draft)->toBeTrue()
        ->and($announcement->email_broadcast_authorized_at)->toBeNull()
        ->and($announcement->emailDeliveries()->count())->toBe(0);
    Mail::assertNothingSent();
});

it('generates a publication slug from the title when updating a draft without one', function () {
    $announcement = Announcement::factory()->draft()->create([
        'slug' => 'original-publication-slug',
        'hero_image_path' => 'images/original.png',
    ]);

    $response = $this->actingAs($this->admin)->put(route('admin.announcements.update', $announcement), [
        'title' => 'Generated From Updated Title',
        'slug' => '',
        'content' => 'Updated content.',
        'hero_image_path' => 'images/updated.png',
    ]);

    $response->assertValid()
        ->assertRedirectToRoute('admin.announcements.preview', [
            'announcement' => 'generated-from-updated-title',
        ]);
    expect($announcement->fresh()->slug)->toBe('generated-from-updated-title');
});

it('allows a draft to retain its publication slug', function () {
    $announcement = Announcement::factory()->draft()->create([
        'slug' => 'retained-publication-slug',
        'hero_image_path' => 'images/original.png',
    ]);

    $response = $this->actingAs($this->admin)->put(route('admin.announcements.update', $announcement), [
        'title' => 'Updated title',
        'slug' => 'retained-publication-slug',
        'content' => 'Updated content.',
        'hero_image_path' => 'images/updated.png',
    ]);

    $response->assertValid()
        ->assertRedirectToRoute('admin.announcements.preview', [
            'announcement' => 'retained-publication-slug',
        ]);
    expect($announcement->fresh()->title)->toBe('Updated title');
});

it('rejects another announcement publication slug when updating a draft', function () {
    $announcement = Announcement::factory()->draft()->create([
        'title' => 'Unchanged title',
        'slug' => 'unchanged-slug',
        'hero_image_path' => 'images/original.png',
    ]);
    Announcement::factory()->create(['slug' => 'existing-slug']);

    $response = $this->actingAs($this->admin)
        ->from(route('admin.announcements.edit', $announcement))
        ->put(route('admin.announcements.update', $announcement), [
            'title' => 'Changed title',
            'slug' => 'existing-slug',
            'content' => 'Changed content.',
            'hero_image_path' => 'images/changed.png',
        ]);

    $response->assertRedirect(route('admin.announcements.edit', $announcement))
        ->assertInvalid(['slug']);
    expect($announcement->fresh()->title)->toBe('Unchanged title')
        ->and($announcement->fresh()->slug)->toBe('unchanged-slug');
});

it('does not allow published announcements to be edited', function () {
    $announcement = Announcement::factory()->create([
        'title' => 'Published announcement',
        'hero_image_path' => 'images/published.png',
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.announcements.edit', $announcement))
        ->assertNotFound();

    $this->actingAs($this->admin)->put(route('admin.announcements.update', $announcement), [
        'title' => 'Changed title',
        'slug' => $announcement->slug,
        'content' => 'Changed content.',
        'hero_image_path' => 'images/changed.png',
    ])->assertNotFound();

    expect($announcement->fresh()->title)->toBe('Published announcement');
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
        route('admin.announcements.edit', $announcement)
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
        ->assertDontSee(route('admin.announcements.edit', $announcement));
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

it('publishes and authorizes an immediate email broadcast without sending in the request', function () {
    Mail::fake();
    $recipient = User::factory()->create();

    $response = $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
        'title' => 'New Feature',
        'content' => 'Some markdown content.',
        'hero_image_path' => 'images/new-feature-hero.png',
        'social_image_path' => 'images/new-feature-social.jpg',
        'is_draft' => true,
    ]);

    $response->assertRedirect(route('admin.announcements.index'));
    $response->assertSessionHas(
        'success',
        'Announcement published. Email delivery will begin within five minutes.'
    );

    $announcement = Announcement::first();

    expect($announcement)->not->toBeNull()
        ->and($announcement->title)->toBe('New Feature')
        ->and($announcement->hero_image_path)->toBe('images/new-feature-hero.png')
        ->and($announcement->social_image_path)->toBe('images/new-feature-social.jpg')
        ->and($announcement->is_draft)->toBeFalse()
        ->and($announcement->slug)->toBe('new-feature')
        ->and($announcement->email_broadcast_authorized_at)->not->toBeNull()
        ->and($announcement->email_audience_finalized_at)->toBeNull()
        ->and($announcement->emailDeliveries()->count())->toBe(0);

    Mail::assertNothingSent();

    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    expect($announcement->fresh()->email_audience_finalized_at)->not->toBeNull()
        ->and($announcement->emailDeliveries()->count())->toBe(2)
        ->and($announcement->emailDeliveries()->pluck('user_id')->all())
        ->toEqualCanonicalizing([$this->admin->id, $recipient->id]);
    Mail::assertSentCount(2);
});

it('shows a duplicate slug error and restores the announcement form', function () {
    Announcement::factory()->create(['slug' => 'new-feature']);

    $response = $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
        'title' => 'New Feature',
        'content' => 'Some markdown content.',
        'hero_image_path' => 'images/new-feature-hero.png',
        'social_image_path' => 'images/new-feature-social.png',
        'starts_at' => '2026-09-10T09:00',
        'ends_at' => '2026-09-17T09:00',
    ]);

    $response->assertInvalid(['slug']);
    $form = $this->get(route('admin.announcements.create'));

    $form->assertSee('The announcement could not be saved.')
        ->assertSee('The slug has already been taken.')
        ->assertSee('value="New Feature"', false)
        ->assertSee('value="new-feature"', false)
        ->assertSee('value="images/new-feature-hero.png"', false)
        ->assertSee('value="images/new-feature-social.png"', false)
        ->assertSee('Some markdown content.')
        ->assertSee('value="2026-09-10T09:00"', false)
        ->assertSee('value="2026-09-17T09:00"', false);
    expect(Announcement::query()->count())->toBe(1);
});

it('publishes with an explicit clean publication slug', function () {
    Mail::fake();

    $response = $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
        'title' => 'New Feature',
        'slug' => 'Editorial Release URL',
        'content' => 'Some markdown content.',
        'hero_image_path' => 'images/new-feature-hero.png',
    ]);

    $response->assertRedirect(route('admin.announcements.index'));
    expect(Announcement::sole()->slug)->toBe('editorial-release-url');
    Mail::assertNothingSent();
});

it('authorizes a scheduled email broadcast without sending before publication', function () {
    Mail::fake();
    $recipient = User::factory()->create();
    $startsAt = now()->addHour()->startOfMinute();

    $response = $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
        'title' => 'Scheduled Feature',
        'content' => 'Some scheduled markdown content.',
        'hero_image_path' => 'images/scheduled-feature-hero.png',
        'starts_at' => $startsAt->format('Y-m-d\TH:i'),
    ]);

    $response->assertRedirect(route('admin.announcements.index'))
        ->assertSessionHas(
            'success',
            'Announcement scheduled. Eligible users will be emailed after it is published.'
        );

    $announcement = Announcement::sole();

    expect($announcement->email_broadcast_authorized_at)->not->toBeNull()
        ->and($announcement->email_audience_finalized_at)->toBeNull()
        ->and($announcement->emailDeliveries()->count())->toBe(0);
    Mail::assertNothingSent();

    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    expect($announcement->fresh()->email_audience_finalized_at)->toBeNull()
        ->and($announcement->emailDeliveries()->count())->toBe(0);
    Mail::assertNothingSent();

    $this->travelTo($startsAt);
    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    expect($announcement->fresh()->email_audience_finalized_at)->not->toBeNull()
        ->and($announcement->emailDeliveries()->count())->toBe(2)
        ->and($announcement->emailDeliveries()->pluck('user_id')->all())
        ->toEqualCanonicalizing([$this->admin->id, $recipient->id]);
    Mail::assertSentCount(2);
});

it('it_can_render_a_markdown_preview_for_admins', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.announcements.preview-markdown'), [
        'content' => "# Hello\n\n**World**",
    ], [
        'HX-Request' => 'true',
    ]);

    $response->assertOk();
    $response->assertSee('<h1>Hello</h1>', false);
    $response->assertSee('<strong>World</strong>', false);
    $response->assertDontSee('<!DOCTYPE html>');
    expect(Announcement::query()->count())->toBe(0);
});

it('it_can_render_an_empty_preview_state_for_admins', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.announcements.preview-markdown'), [
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
        'hero_image_path' => 'images/nope.png',
        'social_image_path' => 'images/nope-social.jpg',
    ]],
    ['post', fn () => route('admin.announcements.preview-markdown'), [
        'content' => '# Preview',
    ]],
    ['get', fn () => route('admin.announcements.preview', [
        'announcement' => Announcement::factory()->draft()->create()->slug,
    ])],
    ['get', fn () => route('admin.announcements.edit', Announcement::factory()->draft()->create())],
    ['put', fn () => route('admin.announcements.update', Announcement::factory()->draft()->create()), [
        'title' => 'Blocked update',
        'content' => 'Nope',
        'hero_image_path' => 'images/nope.png',
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
    ['post', fn () => route('admin.announcements.preview-markdown'), [
        'content' => '# Preview',
    ]],
    ['get', fn () => route('admin.announcements.preview', [
        'announcement' => Announcement::factory()->draft()->create()->slug,
    ])],
    ['get', fn () => route('admin.announcements.edit', Announcement::factory()->draft()->create())],
    ['put', fn () => route('admin.announcements.update', Announcement::factory()->draft()->create())],
]);

it('it_can_validate_announcement_creation_inputs', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
        'title' => '',
        'content' => '',
    ]);

    $response->assertSessionHasErrors(['title', 'content', 'hero_image_path']);
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

    $response = $this->actingAs($this->admin)->get(route('admin.announcements.index'));

    $response->assertOk()
        ->assertSee('Sending')
        ->assertSee('2 of 3 handled')
        ->assertSee('1 pending')
        ->assertSee('Started 5 minutes ago')
        ->assertSee('Last activity')
        ->assertSee('hx-trigger="every 15s"', false)
        ->assertSee('hx-select="#announcement-table-body"', false);
});

it('does not poll for email progress before a scheduled announcement is published', function () {
    Announcement::factory()->create([
        'starts_at' => now()->addDay(),
        'email_broadcast_authorized_at' => now(),
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.announcements.index'));

    $response->assertOk()
        ->assertSee('Scheduled')
        ->assertDontSee('hx-trigger="every 15s"', false);
});

it('shows completed broadcast duration and stops polling', function () {
    $announcement = Announcement::factory()->create([
        'email_broadcast_authorized_at' => now()->subMinutes(11),
        'email_audience_finalized_at' => now()->subMinutes(10),
        'email_broadcast_completed_at' => now(),
    ]);
    AnnouncementEmailDelivery::factory()->create([
        'announcement_id' => $announcement->id,
        'sent_at' => now()->subMinute(),
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.announcements.index'));

    $response->assertOk()
        ->assertSee('Delivered')
        ->assertSee('1 of 1 handled')
        ->assertSee('Completed in 10m')
        ->assertDontSee('hx-trigger="every 15s"', false);
});

it('warns when pending announcement email delivery is delayed', function () {
    $announcement = Announcement::factory()->create([
        'email_broadcast_authorized_at' => now()->subMinutes(17),
        'email_audience_finalized_at' => now()->subMinutes(16),
    ]);
    AnnouncementEmailDelivery::factory()->create([
        'announcement_id' => $announcement->id,
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.announcements.index'));

    $response->assertOk()
        ->assertSee('Delayed')
        ->assertSee('0 of 1 handled')
        ->assertSee('1 pending')
        ->assertSee('Started 16 minutes ago');
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

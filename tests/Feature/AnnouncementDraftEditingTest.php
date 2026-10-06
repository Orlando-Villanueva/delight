<?php

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    config(['mail.admin_address' => 'admin@example.com']);
});

it('loads stored draft values and stays in the editor after saving', function () {
    Mail::fake();
    $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
    $draft = Announcement::factory()->draft()->create([
        'title' => 'Original title', 'slug' => 'original-draft', 'content' => 'Original body',
        'hero_image_path' => 'images/updates/legacy.png', 'social_image_path' => 'images/updates/social/preview.png',
        'starts_at' => '2027-01-01 10:00:00', 'ends_at' => '2027-01-02 10:00:00',
    ]);

    Livewire::test(EditAnnouncement::class, ['record' => $draft->id])
        ->assertFormSet([
            'title' => 'Original title', 'slug' => 'original-draft', 'content' => 'Original body',
            'hero_image_path' => 'images/updates/legacy.png', 'social_image_path' => 'images/updates/social/preview.png',
            'starts_at' => '2027-01-01 10:00:00', 'ends_at' => '2027-01-02 10:00:00',
        ])
        ->fillForm(['title' => 'Revised title', 'slug' => 'Revised Draft!', 'content' => '# Revised body'])
        ->set('data.is_draft', false)->set('data.email_broadcast_authorized_at', now()->toDateTimeString())
        ->call('save')->assertHasNoFormErrors()->assertNotified('Announcement draft updated.')
        ->assertNoRedirect()
        ->assertSet('data.slug', 'revised-draft')
        ->assertSee(route('admin.announcements.preview', 'revised-draft'))
        ->assertSee('Preview saved draft');

    $draft->refresh();
    expect($draft->title)->toBe('Revised title')
        ->and($draft->slug)->toBe('revised-draft')
        ->and($draft->content)->toBe('# Revised body')
        ->and($draft->hero_image_path)->toBe('images/updates/legacy.png')
        ->and($draft->social_image_path)->toBe('images/updates/social/preview.png')
        ->and($draft->starts_at->toDateTimeString())->toBe('2027-01-01 10:00:00')
        ->and($draft->ends_at->toDateTimeString())->toBe('2027-01-02 10:00:00')
        ->and($draft->is_draft)->toBeTrue()
        ->and($draft->email_broadcast_authorized_at)->toBeNull()
        ->and($draft->email_audience_finalized_at)->toBeNull()
        ->and($draft->email_broadcast_completed_at)->toBeNull();
    $this->assertDatabaseCount('announcements', 1);
    $this->assertDatabaseCount('announcement_email_deliveries', 0);
    $editUrl = AnnouncementResource::getUrl('edit', ['record' => $draft], panel: 'admin');
    $this->get(route('admin.announcements.preview', $draft->slug))->assertOk()->assertSee($editUrl)->assertSee('Revised body');
    auth()->logout();
    $this->get(route('announcements.show', $draft->slug))->assertNotFound();
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

it('retains its own slug and clears optional fields', function () {
    $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
    $draft = Announcement::factory()->draft()->create(['hero_image_path' => 'images/hero.png', 'social_image_path' => 'images/social.png', 'ends_at' => now()->addMonth()]);
    $slug = $draft->slug;
    Livewire::test(EditAnnouncement::class, ['record' => $draft->id])
        ->fillForm(['social_image_path' => null, 'ends_at' => null])->call('save')->assertHasNoFormErrors();
    expect($draft->fresh()->slug)->toBe($slug)
        ->and($draft->fresh()->social_image_path)->toBeNull()
        ->and($draft->fresh()->ends_at)->toBeNull();
});

it('normalizes a blank slug from the title and defaults a cleared proposed time', function () {
    $this->freezeTime();
    $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
    $draft = Announcement::factory()->draft()->create(['hero_image_path' => 'images/hero.png']);
    Livewire::test(EditAnnouncement::class, ['record' => $draft->id])
        ->fillForm(['title' => 'New draft title', 'slug' => '', 'starts_at' => null])
        ->call('save')->assertHasNoFormErrors();
    expect($draft->fresh()->slug)->toBe('new-draft-title')
        ->and($draft->fresh()->starts_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('reports invalid edits without changing the stored draft', function (array $input, string $field) {
    $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
    $draft = Announcement::factory()->draft()->create(['title' => 'Original title', 'hero_image_path' => 'images/hero.png', 'starts_at' => '2027-01-01 10:00:00']);
    Announcement::factory()->create(['slug' => 'reserved-slug']);
    $original = $draft->fresh()->getAttributes();
    Livewire::test(EditAnnouncement::class, ['record' => $draft->id])
        ->fillForm($input)->call('save')->assertHasFormErrors([$field])->assertNotNotified();
    expect($draft->fresh()->getAttributes())->toBe($original);
})->with([
    'duplicate normalized slug' => [['slug' => 'Reserved Slug!'], 'slug'],
    'invalid expiry' => [['ends_at' => '2026-01-01 10:00:00'], 'ends_at'],
    'required title' => [['title' => ''], 'title'],
]);

it('restricts editing to admins and draft records', function () {
    $draft = Announcement::factory()->draft()->create();
    $published = Announcement::factory()->create(['is_draft' => false]);
    $draftUrl = AnnouncementResource::getUrl('edit', ['record' => $draft], panel: 'admin');
    $publishedUrl = AnnouncementResource::getUrl('edit', ['record' => $published], panel: 'admin');
    $this->get($draftUrl)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get($draftUrl)->assertForbidden();
    $this->actingAs(User::factory()->create(['email' => 'admin@example.com']))->get($draftUrl)->assertOk();
    $this->get($publishedUrl)->assertForbidden();
});

it('rejects a save if publication or admin access changed while the form was open', function (string $change) {
    $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
    $draft = Announcement::factory()->draft()->create(['title' => 'Original title']);
    $page = Livewire::test(EditAnnouncement::class, ['record' => $draft->id])->fillForm(['title' => 'Blocked edit']);
    if ($change === 'publication') {
        $draft->update(['is_draft' => false]);
    } else {
        config(['mail.admin_address' => 'revoked@example.com']);
    }
    $page->call('save')->assertForbidden();
    expect($draft->fresh()->title)->toBe('Original title');
})->with(['publication', 'admin access']);

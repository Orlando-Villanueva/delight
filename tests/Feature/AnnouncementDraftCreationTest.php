<?php

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->freezeTime();
    Mail::fake();
    config(['mail.admin_address' => 'admin@example.com']);
});

it('creates a private draft with Artisan defaults and opens its preview', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $this->actingAs($admin);
    $content = "# Draft heading\n\nA **Markdown** paragraph.";

    $page = Livewire::test(CreateAnnouncement::class)->fillForm([
        'title' => 'A Private Draft', 'content' => $content, 'hero_image_path' => 'images/updates/example.png',
    ])->set('data.is_draft', false)->set('data.email_broadcast_authorized_at', now()->toDateTimeString())
        ->call('create')->assertHasNoFormErrors()->assertNotified('Announcement draft created.');
    $announcement = Announcement::sole();
    $page->assertRedirect(route('admin.announcements.preview', $announcement->slug));
    expect($announcement->slug)->toBe('a-private-draft')
        ->and($announcement->content)->toBe($content)
        ->and($announcement->is_draft)->toBeTrue()
        ->and($announcement->starts_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($announcement->email_broadcast_authorized_at)->toBeNull()
        ->and($announcement->email_audience_finalized_at)->toBeNull()
        ->and($announcement->email_broadcast_completed_at)->toBeNull();
    $this->get(route('admin.announcements.preview', $announcement->slug))->assertOk()->assertSee('Draft heading');
    auth()->logout();
    $this->get(route('announcements.show', $announcement->slug))->assertNotFound();
    $this->assertDatabaseCount('announcement_email_deliveries', 0);
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

it('normalizes a custom slug and preserves proposed times and image paths', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $this->actingAs($admin);
    $startsAt = now()->addDay()->startOfSecond();
    $endsAt = now()->addDays(2)->startOfSecond();

    Livewire::test(CreateAnnouncement::class)->fillForm([
        'title' => 'Draft title', 'slug' => 'Custom Publication Slug!', 'content' => '[Draft link](https://)',
        'hero_image_path' => 'images/updates/hero.png', 'social_image_path' => 'images/updates/social.png',
        'starts_at' => $startsAt->toDateTimeString(), 'ends_at' => $endsAt->toDateTimeString(),
    ])->call('create')->assertHasNoFormErrors();
    $announcement = Announcement::sole();
    expect($announcement->slug)->toBe('custom-publication-slug')
        ->and($announcement->starts_at->toDateTimeString())->toBe($startsAt->toDateTimeString())
        ->and($announcement->ends_at->toDateTimeString())->toBe($endsAt->toDateTimeString())
        ->and($announcement->hero_image_path)->toBe('images/updates/hero.png')
        ->and($announcement->social_image_path)->toBe('images/updates/social.png')
        ->and($announcement->is_draft)->toBeTrue();
    Mail::assertNothingSent();
});

it('reports validation errors on form fields without persisting a draft', function (array $input, string $field) {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    Announcement::factory()->create(['slug' => 'existing-slug']);
    $this->actingAs($admin);

    Livewire::test(CreateAnnouncement::class)->fillForm(array_merge([
        'title' => 'New draft', 'content' => 'Draft content', 'hero_image_path' => 'images/updates/example.png',
    ], $input))->call('create')->assertHasFormErrors([$field]);
    $this->assertDatabaseCount('announcements', 1);
    Mail::assertNothingSent();
})->with([
    'required title' => [['title' => ''], 'title'],
    'required content' => [['content' => ''], 'content'],
    'required hero image' => [['hero_image_path' => ''], 'hero_image_path'],
    'duplicate normalized slug' => [['slug' => 'Existing Slug!'], 'slug'],
    'duplicate default slug' => [['title' => 'Existing Slug'], 'slug'],
    'invalid expiry' => [fn () => ['starts_at' => now()->addDay()->toDateTimeString(), 'ends_at' => now()->toDateTimeString()], 'ends_at'],
    'expiry before default time' => [fn () => ['ends_at' => now()->subDay()->toDateTimeString()], 'ends_at'],
]);

it('restricts draft creation to admins and rechecks access on submission', function () {
    $url = AnnouncementResource::getUrl('create', panel: 'admin');
    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $this->actingAs($admin)->get($url)->assertOk();
    $page = Livewire::test(CreateAnnouncement::class)->fillForm([
        'title' => 'Blocked draft', 'content' => 'Content', 'hero_image_path' => 'images/updates/example.png',
    ]);
    config(['mail.admin_address' => 'revoked@example.com']);
    $page->call('create')->assertForbidden();
    $this->assertDatabaseCount('announcements', 0);
});

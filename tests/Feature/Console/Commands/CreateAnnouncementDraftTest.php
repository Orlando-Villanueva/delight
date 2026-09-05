<?php

use App\Mail\AnnouncementEmail;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

it('creates a persisted draft without publication or delivery side effects', function () {
    Mail::fake();
    $this->travelTo('2026-09-02 12:00:00');
    User::factory()->create();
    $contentFile = announcementDraftContentFile("# A careful update\n\nDraft content.");

    $exitCode = Artisan::call('announcements:draft', [
        '--title' => 'A Careful Update',
        '--content-file' => $contentFile,
        '--hero-image-path' => 'images/careful-update.png',
        '--social-image-path' => 'images/careful-update-social.png',
        '--starts-at' => '2026-09-03 09:30:00',
        '--ends-at' => '2026-09-10 09:30:00',
        '--json' => true,
    ]);
    $output = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);

    $announcement = Announcement::sole();

    expect($exitCode)->toBe(Command::SUCCESS);
    expect($output)->toMatchArray([
        'id' => $announcement->id,
        'slug' => 'a-careful-update',
        'state' => 'draft',
        'preview_url' => route('admin.announcements.preview', [
            'announcement' => 'a-careful-update',
        ]),
        'publication_url' => route('announcements.show', ['slug' => 'a-careful-update']),
    ])
        ->and($output['proposed_starts_at'])->not->toBeNull()
        ->and($output['proposed_ends_at'])->not->toBeNull();
    expect($announcement->title)->toBe('A Careful Update')
        ->and($announcement->content)->toBe("# A careful update\n\nDraft content.")
        ->and($announcement->slug)->toBe('a-careful-update')
        ->and($announcement->hero_image_path)->toBe('images/careful-update.png')
        ->and($announcement->social_image_path)->toBe('images/careful-update-social.png')
        ->and($announcement->is_draft)->toBeTrue()
        ->and($announcement->email_broadcast_authorized_at)->toBeNull()
        ->and($announcement->email_audience_finalized_at)->toBeNull()
        ->and($announcement->emailDeliveries()->count())->toBe(0);
    Mail::assertNothingSent();

    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    expect($announcement->fresh()->email_audience_finalized_at)->toBeNull()
        ->and($announcement->emailDeliveries()->count())->toBe(0);
    Mail::assertNotSent(AnnouncementEmail::class);
});

it('uses an explicit clean publication slug', function () {
    $contentFile = announcementDraftContentFile('Draft content.');

    $exitCode = Artisan::call('announcements:draft', [
        '--title' => 'A Careful Update',
        '--slug' => 'Editorial Release URL',
        '--content-file' => $contentFile,
        '--hero-image-path' => 'images/careful-update.png',
        '--json' => true,
    ]);

    $announcement = Announcement::sole();
    $output = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($announcement->slug)->toBe('editorial-release-url')
        ->and($output['preview_url'])->toBe(route('admin.announcements.preview', [
            'announcement' => 'editorial-release-url',
        ]))
        ->and($output['publication_url'])->toBe(route('announcements.show', [
            'slug' => 'editorial-release-url',
        ]));
});

it('rejects a duplicate publication slug without creating a draft', function () {
    Announcement::factory()->create(['slug' => 'reserved-release']);
    $contentFile = announcementDraftContentFile('Draft content.');

    $exitCode = Artisan::call('announcements:draft', [
        '--title' => 'Another Release',
        '--slug' => 'Reserved Release',
        '--content-file' => $contentFile,
        '--hero-image-path' => 'images/another-release.png',
        '--json' => true,
    ]);
    $output = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($output['errors'])->toHaveKey('slug')
        ->and(Announcement::query()->count())->toBe(1);
});

it('keeps a draft off every public announcement surface', function () {
    $announcement = Announcement::factory()->draft()->create([
        'title' => 'Private draft announcement',
        'slug' => 'private-draft-announcement',
        'starts_at' => now()->subMinute(),
    ]);
    $user = User::factory()->create();

    $this->get(route('announcements.index'))
        ->assertDontSee($announcement->title);
    $this->get(route('announcements.show', $announcement->slug))
        ->assertNotFound();
    $this->get(route('sitemap'))
        ->assertDontSee(route('announcements.show', $announcement->slug));
    $this->actingAs($user)->get(route('notifications.index'))
        ->assertDontSee($announcement->title);
    $this->actingAs($user)
        ->post(route('notifications.markAsRead', $announcement))
        ->assertNotFound();

    expect($user->announcements()->whereKey($announcement->id)->exists())->toBeFalse();
});

it('defaults a draft proposed publication time without making it public', function () {
    Mail::fake();
    $this->travelTo('2026-09-02 12:00:00');
    User::factory()->create();
    $contentFile = announcementDraftContentFile('Draft content.');

    $this->artisan('announcements:draft', [
        '--title' => 'Immediate after approval',
        '--content-file' => $contentFile,
        '--hero-image-path' => 'images/immediate.png',
    ])->expectsOutputToContain(route('admin.announcements.preview', [
        'announcement' => 'immediate-after-approval',
    ]))->expectsOutputToContain(route('announcements.show', [
        'slug' => 'immediate-after-approval',
    ]))->assertSuccessful();

    $announcement = Announcement::sole();

    expect($announcement->starts_at?->toDateTimeString())->toBe('2026-09-02 12:00:00')
        ->and($announcement->is_draft)->toBeTrue();
    $this->get(route('announcements.show', $announcement->slug))->assertNotFound();
    $this->artisan('announcements:send-published-emails')->assertSuccessful();
    expect($announcement->fresh()->email_audience_finalized_at)->toBeNull();
    Mail::assertNothingSent();
});

it('returns validation errors and failure status without persisting a draft', function () {
    $contentFile = announcementDraftContentFile('Draft content.');

    $exitCode = Artisan::call('announcements:draft', [
        '--content-file' => $contentFile,
        '--json' => true,
    ]);
    $output = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(Command::FAILURE);
    expect($output['errors'])->toHaveKeys(['title', 'hero_image_path']);
    expect(Announcement::query()->count())->toBe(0);
});

it('fails without reading or persisting an unavailable content file', function () {
    $this->artisan('announcements:draft', [
        '--title' => 'Unavailable content',
        '--content-file' => storage_path('framework/testing/missing-announcement.md'),
        '--hero-image-path' => 'images/unavailable.png',
    ])->expectsOutputToContain('The content file must be an existing readable file.')
        ->assertFailed();

    expect(Announcement::query()->count())->toBe(0);
});

function announcementDraftContentFile(string $content): string
{
    Storage::fake('local');
    Storage::disk('local')->put('announcement-draft.md', $content);

    return Storage::disk('local')->path('announcement-draft.md');
}

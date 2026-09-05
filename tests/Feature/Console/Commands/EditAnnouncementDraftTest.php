<?php

use App\Mail\AnnouncementEmail;
use App\Models\Announcement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

it('updates only the requested draft fields without publication or delivery side effects', function () {
    Mail::fake();
    $announcement = Announcement::factory()->draft()->create([
        'title' => 'Original title',
        'slug' => 'original-title',
        'content' => 'Original content.',
        'hero_image_path' => 'images/original-hero.png',
        'social_image_path' => 'images/original-social.png',
        'starts_at' => '2026-09-10 09:00:00',
        'ends_at' => '2026-09-17 09:00:00',
    ]);
    $contentFile = editableAnnouncementContentFile("# Revised heading\n\nRevised content.");

    $exitCode = Artisan::call('announcements:edit', [
        'draft' => 'Original Title',
        '--title' => 'Revised title',
        '--slug' => 'Revised Editorial URL',
        '--content-file' => $contentFile,
        '--starts-at' => '2026-09-11 10:30:00',
        '--json' => true,
    ]);
    $output = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);
    $announcement->refresh();

    expect($exitCode)->toBe(Command::SUCCESS);
    expect($output)->toMatchArray([
        'id' => $announcement->id,
        'slug' => 'revised-editorial-url',
        'state' => 'draft',
        'preview_url' => route('admin.announcements.preview', [
            'announcement' => 'revised-editorial-url',
        ]),
        'publication_url' => route('announcements.show', ['slug' => 'revised-editorial-url']),
    ]);
    expect($announcement->title)->toBe('Revised title')
        ->and($announcement->content)->toBe("# Revised heading\n\nRevised content.")
        ->and($announcement->slug)->toBe('revised-editorial-url')
        ->and($announcement->hero_image_path)->toBe('images/original-hero.png')
        ->and($announcement->social_image_path)->toBe('images/original-social.png')
        ->and($announcement->starts_at->toDateTimeString())->toBe('2026-09-11 10:30:00')
        ->and($announcement->ends_at->toDateTimeString())->toBe('2026-09-17 09:00:00')
        ->and($announcement->is_draft)->toBeTrue()
        ->and($announcement->email_broadcast_authorized_at)->toBeNull()
        ->and($announcement->emailDeliveries()->count())->toBe(0);
    Mail::assertNothingSent();

    $this->artisan('announcements:send-published-emails')->assertSuccessful();
    expect($announcement->fresh()->email_audience_finalized_at)->toBeNull();
    Mail::assertNotSent(AnnouncementEmail::class);
});

it('clears optional draft fields explicitly', function () {
    $announcement = Announcement::factory()->draft()->create([
        'hero_image_path' => 'images/hero.png',
        'social_image_path' => 'images/social.png',
        'starts_at' => '2026-09-10 09:00:00',
        'ends_at' => '2026-09-17 09:00:00',
    ]);

    $this->artisan('announcements:edit', [
        'draft' => $announcement->slug,
        '--clear-social-image' => true,
        '--clear-ends-at' => true,
    ])->expectsOutput('Announcement draft updated.')
        ->assertSuccessful();

    expect($announcement->fresh()->social_image_path)->toBeNull()
        ->and($announcement->fresh()->ends_at)->toBeNull();
});

it('rejects a duplicate publication slug without changing the draft', function () {
    $announcement = Announcement::factory()->draft()->create([
        'slug' => 'editable-draft',
        'title' => 'Original title',
    ]);
    Announcement::factory()->create(['slug' => 'reserved-release']);

    $exitCode = Artisan::call('announcements:edit', [
        'draft' => $announcement->slug,
        '--title' => 'Changed title',
        '--slug' => 'Reserved Release',
        '--json' => true,
    ]);
    $output = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($output['errors'])->toHaveKey('slug');
    expect($announcement->fresh()->title)->toBe('Original title')
        ->and($announcement->fresh()->slug)->toBe('editable-draft');
});

it('rejects missing and non-draft announcements', function (string $slug) {
    $this->artisan('announcements:edit', [
        'draft' => $slug,
        '--title' => 'Changed title',
    ])->expectsOutputToContain('Only an existing draft announcement can be edited.')
        ->assertFailed();
})->with([
    'missing announcement' => 'missing-announcement',
    'published announcement' => fn (): string => Announcement::factory()->create()->slug,
    'scheduled announcement' => fn (): string => Announcement::factory()->create([
        'starts_at' => now()->addDay(),
    ])->slug,
]);

it('rejects an edit without any change options', function () {
    $announcement = Announcement::factory()->draft()->create();

    $this->artisan('announcements:edit', [
        'draft' => $announcement->slug,
    ])->expectsOutputToContain('At least one draft change option is required.')
        ->assertFailed();
});

it('rejects conflicting set and clear options without changing the draft', function (array $options, string $message) {
    $announcement = Announcement::factory()->draft()->create([
        'social_image_path' => 'images/original-social.png',
        'ends_at' => now()->addWeek(),
    ]);

    $this->artisan('announcements:edit', [
        'draft' => $announcement->slug,
        ...$options,
    ])->expectsOutputToContain($message)
        ->assertFailed();

    expect($announcement->fresh()->social_image_path)->toBe('images/original-social.png')
        ->and($announcement->fresh()->ends_at)->not->toBeNull();
})->with([
    'social image' => [
        ['--social-image-path' => 'images/new-social.png', '--clear-social-image' => true],
        'The social image path cannot be set and cleared together.',
    ],
    'expiry time' => [
        ['--ends-at' => '2026-09-20 09:00:00', '--clear-ends-at' => true],
        'The expiry time cannot be set and cleared together.',
    ],
]);

it('fails without changing the draft when the content file is unavailable', function () {
    $announcement = Announcement::factory()->draft()->create([
        'content' => 'Original content.',
    ]);

    $this->artisan('announcements:edit', [
        'draft' => $announcement->slug,
        '--content-file' => storage_path('framework/testing/missing-announcement.md'),
    ])->expectsOutputToContain('The content file must be an existing readable file.')
        ->assertFailed();

    expect($announcement->fresh()->content)->toBe('Original content.');
});

function editableAnnouncementContentFile(string $content): string
{
    Storage::fake('local');
    Storage::disk('local')->put('editable-announcement.md', $content);

    return Storage::disk('local')->path('editable-announcement.md');
}

<?php

use App\Mail\AnnouncementEmail;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

it('reports a dry run without changing the draft or creating deliveries', function () {
    $this->freezeTime();
    Mail::fake();
    $draft = Announcement::factory()->draft()->create(['hero_image_path' => 'images/hero.png', 'starts_at' => now()->subWeek()]);
    $original = $draft->fresh()->getAttributes();
    User::factory()->unverified()->create();
    User::factory()->create(['marketing_emails_opted_out_at' => now()]);
    User::factory()->create(['email' => 'invalid']);
    User::factory()->create(['created_at' => now()->addDay()]);

    expect(Artisan::call('announcements:publish', ['draft' => $draft->slug, '--dry-run' => true, '--json' => true]))->toBe(0);
    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($output)->toMatchArray([
        'state' => 'published',
        'dry_run' => true,
        'starts_at' => now()->toIso8601String(),
        'eligible_recipients' => 1,
        'excluded_recipients' => 3,
    ]);
    expect($draft->fresh()->getAttributes())->toBe($original);
    $this->assertDatabaseCount('announcement_email_deliveries', 0);
    Mail::assertNothingSent();
});

it('publishes at the actual confirmation time and leaves sending to the mailer', function () {
    $this->freezeTime();
    Mail::fake();
    $recipient = User::factory()->create();
    $draft = Announcement::factory()->draft()->create(['hero_image_path' => 'images/hero.png', 'starts_at' => now()->subWeek()]);

    $this->artisan('announcements:publish', ['draft' => $draft->slug])
        ->expectsConfirmation('Publish or schedule this announcement and authorize email delivery?', 'yes')
        ->assertSuccessful();

    expect($draft->fresh()->is_draft)->toBeFalse()
        ->and($draft->fresh()->starts_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($draft->fresh()->email_broadcast_authorized_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($draft->fresh()->email_audience_finalized_at)->toBeNull();
    $this->assertDatabaseCount('announcement_email_deliveries', 0);
    Mail::assertNothingSent();

    $this->artisan('announcements:send-published-emails')->assertSuccessful();
    Mail::assertSent(AnnouncementEmail::class, fn ($mail) => $mail->hasTo($recipient->email));
});

it('preserves a future publication date and sends only once it becomes due', function () {
    $this->freezeTime();
    Mail::fake();
    User::factory()->create();
    $startsAt = now()->addDay();
    $draft = Announcement::factory()->draft()->create(['hero_image_path' => 'images/hero.png', 'starts_at' => $startsAt]);

    expect(Artisan::call('announcements:publish', ['draft' => $draft->slug, '--yes' => true, '--json' => true]))->toBe(0);
    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($output)->toMatchArray(['state' => 'scheduled', 'starts_at' => $startsAt->toIso8601String()]);
    expect($draft->fresh()->isPublished())->toBeFalse();
    $this->artisan('announcements:send-published-emails')->assertSuccessful();
    Mail::assertNothingSent();
    $this->assertDatabaseCount('announcement_email_deliveries', 0);

    $this->travelTo($startsAt);
    $this->artisan('announcements:send-published-emails')->assertSuccessful();
    Mail::assertSentCount(1);
});

it('leaves a cancelled publication untouched', function () {
    $draft = Announcement::factory()->draft()->create(['hero_image_path' => 'images/hero.png']);
    $original = $draft->fresh()->getAttributes();

    $this->artisan('announcements:publish', ['draft' => $draft->slug])
        ->expectsConfirmation('Publish or schedule this announcement and authorize email delivery?', 'no')
        ->expectsOutput('Publication cancelled.')
        ->assertFailed();

    expect($draft->fresh()->getAttributes())->toBe($original);
});

it('requires explicit confirmation for automated publication', function (array $options) {
    $draft = Announcement::factory()->draft()->create(['hero_image_path' => 'images/hero.png']);

    $this->artisan('announcements:publish', ['draft' => $draft->slug, ...$options])->assertFailed();

    expect($draft->fresh()->is_draft)->toBeTrue()
        ->and($draft->fresh()->email_broadcast_authorized_at)->toBeNull();
})->with([
    'JSON alone' => [['--json' => true]],
    'noninteractive alone' => [['--no-interaction' => true]],
]);

it('rejects expiry at or before publication without authorizing delivery', function (int $expiryOffset, bool $dryRun) {
    $this->freezeTime();
    $draft = Announcement::factory()->draft()->create([
        'hero_image_path' => 'images/hero.png',
        'starts_at' => now()->subWeek(),
        'ends_at' => now()->addSeconds($expiryOffset),
    ]);

    expect(Artisan::call('announcements:publish', ['draft' => $draft->slug, '--yes' => true, '--json' => true, '--dry-run' => $dryRun]))->toBe(1);
    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($output['errors'])->toBe(['ends_at' => ['The expiry time must be after the publication time.']]);
    expect($draft->fresh()->is_draft)->toBeTrue()
        ->and($draft->fresh()->email_broadcast_authorized_at)->toBeNull();
})->with([
    'expired draft' => -86400,
    'expires at publication' => 0,
])->with(['dry run' => true, 'publication' => false]);

it('rejects a missing announcement', function () {
    $this->artisan('announcements:publish', ['draft' => 'missing', '--yes' => true])
        ->expectsOutputToContain('Only an existing draft announcement can be published.')
        ->assertFailed();
});

it('rejects repeated publication without changing the original authorization', function () {
    $this->freezeTime();
    $draft = Announcement::factory()->draft()->create(['hero_image_path' => 'images/hero.png']);
    $this->artisan('announcements:publish', ['draft' => $draft->slug, '--yes' => true])->assertSuccessful();
    $published = $draft->fresh()->getAttributes();
    $this->travel(1)->hour();

    $this->artisan('announcements:publish', ['draft' => $draft->slug, '--yes' => true])->assertFailed();

    expect($draft->fresh()->getAttributes())->toBe($published);
});

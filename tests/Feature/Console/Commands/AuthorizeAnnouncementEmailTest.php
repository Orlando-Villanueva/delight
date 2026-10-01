<?php

use App\Mail\AnnouncementEmail;
use App\Models\Announcement;
use App\Models\AnnouncementEmailDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

it('previews identity timing and audience eligibility without changing data', function () {
    $this->freezeSecond();
    Mail::fake();
    $announcement = Announcement::factory()->create(['starts_at' => now()->subDay()]);
    $original = $announcement->fresh()->getAttributes();
    User::factory()->unverified()->create(['created_at' => now()->subDays(2)]);
    User::factory()->create(['created_at' => now()->subDays(2), 'marketing_emails_opted_out_at' => now()]);
    User::factory()->create(['created_at' => now()->subDays(2), 'email' => 'invalid']);
    User::factory()->create(['created_at' => now()]);

    expect(Artisan::call('announcements:authorize-email', [
        'announcement' => $announcement->slug, '--dry-run' => true, '--json' => true,
    ]))->toBe(0);
    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($output)->toMatchArray([
        'id' => $announcement->id,
        'slug' => $announcement->slug,
        'title' => $announcement->title,
        'starts_at' => $announcement->starts_at->toIso8601String(),
        'delivery_due_at' => $announcement->starts_at->toIso8601String(),
        'eligible_recipients' => 1,
        'excluded_recipients' => 3,
        'email_broadcast_authorized_at' => null,
        'dry_run' => true,
        'result' => 'preview',
    ])->and($output['delivery_note'])->toContain('does not confirm sending');
    expect($announcement->fresh()->getAttributes())->toBe($original);
    $this->assertDatabaseCount('announcement_email_deliveries', 0);
    Mail::assertNothingSent();
});

it('requires explicit email authorization confirmation for automated calls', function (array $options) {
    $announcement = Announcement::factory()->create();
    $original = $announcement->fresh()->getAttributes();

    $this->artisan('announcements:authorize-email', ['announcement' => $announcement->slug, ...$options])
        ->assertFailed();

    expect($announcement->fresh()->getAttributes())->toBe($original);
    $this->assertDatabaseCount('announcement_email_deliveries', 0);
})->with([
    'json' => [['--json' => true]],
    'noninteractive' => [['--no-interaction' => true]],
]);

it('leaves a cancelled authorization untouched', function () {
    $announcement = Announcement::factory()->create();
    $original = $announcement->fresh()->getAttributes();

    $this->artisan('announcements:authorize-email', ['announcement' => $announcement->slug])
        ->expectsConfirmation('Authorize email delivery for this announcement?', 'no')
        ->expectsOutput('Email authorization cancelled.')
        ->assertFailed();

    expect($announcement->fresh()->getAttributes())->toBe($original);
});

it('authorizes a published announcement interactively without sending', function () {
    $this->freezeSecond();
    Mail::fake();
    $announcement = Announcement::factory()->create(['starts_at' => now()->subDay()]);
    $startsAt = $announcement->starts_at->toIso8601String();

    $this->artisan('announcements:authorize-email', ['announcement' => $announcement->slug])
        ->expectsConfirmation('Authorize email delivery for this announcement?', 'yes')
        ->expectsOutput('Announcement email authorized.')
        ->assertSuccessful();

    expect($announcement->fresh()->email_broadcast_authorized_at->toIso8601String())->toBe(now()->toIso8601String())
        ->and($announcement->fresh()->starts_at->toIso8601String())->toBe($startsAt)
        ->and($announcement->fresh()->email_audience_finalized_at)->toBeNull();
    $this->assertDatabaseCount('announcement_email_deliveries', 0);
    Mail::assertNothingSent();
});

it('authorizes scheduled email now while the worker waits for publication', function () {
    $this->freezeSecond();
    Mail::fake();
    $recipient = User::factory()->create();
    $startsAt = now()->addDay();
    $announcement = Announcement::factory()->create(['starts_at' => $startsAt]);

    expect(Artisan::call('announcements:authorize-email', [
        'announcement' => $announcement->slug, '--yes' => true, '--json' => true,
    ]))->toBe(0);
    expect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR))->toMatchArray([
        'result' => 'authorized',
        'state' => 'scheduled',
        'email_broadcast_authorized_at' => now()->toIso8601String(),
        'delivery_due_at' => $startsAt->toIso8601String(),
    ]);
    $this->assertDatabaseCount('announcement_email_deliveries', 0);
    Mail::assertNothingSent();

    $this->artisan('announcements:send-published-emails')->assertSuccessful();
    Mail::assertNothingSent();

    $this->travelTo($startsAt);
    $this->artisan('announcements:send-published-emails')->assertSuccessful();
    Mail::assertSent(AnnouncementEmail::class, fn ($mail) => $mail->hasTo($recipient->email));
    $this->assertDatabaseCount('announcement_email_deliveries', 1);
});

it('reports already authorized without resetting broadcasts or recipient states', function () {
    $this->freezeSecond();
    Mail::fake();
    $announcement = Announcement::factory()->create([
        'email_broadcast_authorized_at' => now()->subDay(),
        'email_audience_finalized_at' => now()->subHour(),
        'email_broadcast_completed_at' => now()->subMinute(),
        'content' => '[Legacy link](https://)',
    ]);
    foreach (['failed_at', 'uncertain_at', 'sent_at'] as $state) {
        AnnouncementEmailDelivery::factory()->create([
            'announcement_id' => $announcement->id, $state => now()->subMinutes(5),
        ]);
    }
    $original = $announcement->fresh()->getAttributes();
    $deliveries = $announcement->emailDeliveries()->get()->map->getAttributes()->all();

    expect(Artisan::call('announcements:authorize-email', [
        'announcement' => $announcement->slug, '--json' => true,
    ]))->toBe(0);
    expect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['result'])->toBe('already_authorized');
    expect($announcement->fresh()->getAttributes())->toBe($original)
        ->and($announcement->emailDeliveries()->get()->map->getAttributes()->all())->toBe($deliveries);
    $this->artisan('announcements:send-published-emails')->assertSuccessful();
    expect($announcement->emailDeliveries()->get()->map->getAttributes()->all())->toBe($deliveries);
    Mail::assertNothingSent();
});

it('rejects drafts and malformed email content in preview and authorization', function (array $attributes, string $errorKey, bool $dryRun) {
    $announcement = Announcement::factory()->create($attributes);
    $original = $announcement->fresh()->getAttributes();

    expect(Artisan::call('announcements:authorize-email', [
        'announcement' => $announcement->slug, '--yes' => true, '--json' => true, '--dry-run' => $dryRun,
    ]))->toBe(1);
    expect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['errors'])->toHaveKey($errorKey);
    expect($announcement->fresh()->getAttributes())->toBe($original);
    $this->assertDatabaseCount('announcement_email_deliveries', 0);
})->with([
    'draft' => [['is_draft' => true], 'announcement'],
    'missing publication time' => [['starts_at' => null], 'starts_at'],
    'malformed link' => [['content' => '[Broken](https://)'], 'content'],
])->with(['preview' => true, 'authorize' => false]);

it('reports a missing announcement as a structured error', function () {
    expect(Artisan::call('announcements:authorize-email', [
        'announcement' => 'missing', '--yes' => true, '--json' => true,
    ]))->toBe(1);
    expect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['errors'])
        ->toBe(['announcement' => ['The announcement does not exist.']]);
});

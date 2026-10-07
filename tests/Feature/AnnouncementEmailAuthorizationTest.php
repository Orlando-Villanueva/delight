<?php

use App\Filament\Resources\Announcements\Pages\ViewAnnouncement;
use App\Models\Announcement;
use App\Models\AnnouncementEmailDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    config(['mail.admin_address' => 'admin@example.com']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
    $this->freezeSecond();
    Mail::fake();
});

it('previews audience estimates and permits cancellation without authorizing email', function () {
    $announcement = Announcement::factory()->create(['starts_at' => now()->subDay()]);
    User::factory()->create(['created_at' => now()->subDays(2)]);
    User::factory()->create(['created_at' => now()->subDays(2), 'marketing_emails_opted_out_at' => now()]);
    User::factory()->create(['created_at' => now()->subDays(2), 'email' => 'invalid']);
    $original = $announcement->fresh()->getAttributes();

    Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id])
        ->mountAction('authorizeEmail')
        ->assertActionMounted('authorizeEmail')
        ->assertSet('emailAuthorizationPreview.eligible_recipients', 1)
        ->assertSet('emailAuthorizationPreview.excluded_recipients', 3)
        ->assertSet('emailAuthorizationPreview.starts_at', $announcement->starts_at->toIso8601String())
        ->unmountAction();

    expect($announcement->fresh()->getAttributes())->toBe($original);
    $this->assertDatabaseCount('announcement_email_deliveries', 0);
    Mail::assertNothingSent();
});

it('authorizes published or scheduled email without sending or changing publication time', function (bool $scheduled) {
    $startsAt = $scheduled ? now()->addDay() : now()->subDay();
    $announcement = Announcement::factory()->create(['starts_at' => $startsAt]);

    Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id])
        ->callAction('authorizeEmail')
        ->assertNotified('Announcement email authorized.')
        ->assertActionHidden('authorizeEmail');

    expect($announcement->fresh()->email_broadcast_authorized_at->equalTo(now()))->toBeTrue()
        ->and($announcement->fresh()->starts_at->equalTo($startsAt))->toBeTrue()
        ->and($announcement->fresh()->email_audience_finalized_at)->toBeNull();
    if ($scheduled) {
        $this->artisan('announcements:send-published-emails')->assertSuccessful();
    }
    $this->assertDatabaseCount('announcement_email_deliveries', 0);
    Mail::assertNothingSent();
})->with(['published' => false, 'scheduled' => true]);

it('does not offer authorization for drafts or previously authorized announcements', function (array $attributes) {
    $announcement = Announcement::factory()->create($attributes);

    Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id])
        ->assertActionHidden('authorizeEmail');
})->with([
    'draft' => [['is_draft' => true]],
    'authorized' => fn (): array => ['email_broadcast_authorized_at' => now()->subHour()],
]);

it('reports invalid email authorization before opening confirmation', function (array $attributes) {
    $announcement = Announcement::factory()->create($attributes);
    $original = $announcement->fresh()->getAttributes();

    Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id])
        ->mountAction('authorizeEmail')
        ->assertActionNotMounted()
        ->assertNotified('Email could not be authorized');

    expect($announcement->fresh()->getAttributes())->toBe($original);
    $this->assertDatabaseCount('announcement_email_deliveries', 0);
    Mail::assertNothingSent();
})->with([
    'missing publication time' => [['starts_at' => null]],
    'malformed content' => [['content' => '[Broken](https://)']],
    'expired' => fn (): array => ['ends_at' => now()->subSecond()],
    'legacy email history' => fn (): array => ['sent_via_email_at' => now()->subDay()],
]);

it('preserves recipient history when authorization requires reconciliation', function () {
    $announcement = Announcement::factory()->create();
    $delivery = AnnouncementEmailDelivery::factory()->for($announcement)->create(['failed_at' => now()]);
    $original = $delivery->fresh()->getAttributes();

    Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id])
        ->mountAction('authorizeEmail')
        ->assertNotified('Email could not be authorized');

    expect($announcement->fresh()->email_broadcast_authorized_at)->toBeNull();
    expect($delivery->fresh()->getAttributes())->toBe($original);
    Mail::assertNothingSent();
});

it('rechecks authorization guards when the confirmation is submitted', function (array $changes) {
    $announcement = Announcement::factory()->create();
    $page = Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id])->mountAction('authorizeEmail');
    $announcement->update($changes);
    $original = $announcement->fresh()->getAttributes();

    $page->callMountedAction()->assertNotified('Email could not be authorized');

    expect($announcement->fresh()->getAttributes())->toBe($original);
    Mail::assertNothingSent();
})->with([
    'expired' => fn (): array => ['ends_at' => now()->subSecond()],
    'content became invalid' => [['content' => '[Broken](https://)']],
]);

it('does not authorize an announcement that became a draft while confirmation was open', function () {
    $announcement = Announcement::factory()->create();
    $page = Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id])->mountAction('authorizeEmail');
    $announcement->update(['is_draft' => true]);

    $page->callMountedAction();

    expect($announcement->fresh()->email_broadcast_authorized_at)->toBeNull();
    Mail::assertNothingSent();
});

it('preserves an authorization and delivery records created while confirmation was open', function () {
    $announcement = Announcement::factory()->create();
    $page = Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id])->mountAction('authorizeEmail');
    $announcement->update([
        'email_broadcast_authorized_at' => now()->subMinute(),
        'email_audience_finalized_at' => now(),
    ]);
    $delivery = AnnouncementEmailDelivery::factory()->for($announcement)->create(['sent_at' => now()]);
    $original = $announcement->fresh()->getAttributes();
    $originalDelivery = $delivery->fresh()->getAttributes();

    $page->callMountedAction();

    expect($announcement->fresh()->getAttributes())->toBe($original);
    expect($delivery->fresh()->getAttributes())->toBe($originalDelivery);
    Mail::assertNothingSent();
});

it('rechecks admin access when email authorization is confirmed', function () {
    $announcement = Announcement::factory()->create();
    $page = Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id])->mountAction('authorizeEmail');
    config(['mail.admin_address' => 'other@example.com']);

    $page->callMountedAction()->assertForbidden();

    expect($announcement->fresh()->email_broadcast_authorized_at)->toBeNull();
    Mail::assertNothingSent();
});

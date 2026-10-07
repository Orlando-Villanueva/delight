<?php

use App\Filament\Resources\Announcements\Pages\ViewAnnouncement;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    config(['mail.admin_address' => 'admin@example.com']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
    $this->freezeSecond();
    Mail::fake();
});

it('publishes or schedules the saved draft without authorizing or sending email', function (bool $scheduled) {
    $startsAt = $scheduled ? now()->addDays(2) : now()->subDay();
    $draft = Announcement::factory()->draft()->create(['starts_at' => $startsAt]);

    Livewire::test(ViewAnnouncement::class, ['record' => $draft->id])
        ->assertActionVisible('publishDraft')
        ->mountAction('publishDraft')
        ->assertActionMounted('publishDraft')
        ->callMountedAction()
        ->assertNotified($scheduled ? 'Announcement scheduled.' : 'Announcement published.')
        ->assertActionHidden('publishDraft')
        ->assertActionHidden('editDraft');

    expect($draft->fresh()->is_draft)->toBeFalse()
        ->and($draft->fresh()->starts_at->equalTo($scheduled ? $startsAt : now()))->toBeTrue()
        ->and($draft->fresh()->email_broadcast_authorized_at)->toBeNull();
    $this->assertDatabaseCount('announcement_email_deliveries', 0);
    Mail::assertNothingSent();
})->with(['publish now' => false, 'schedule' => true]);

it('leaves publication untouched until confirmation', function () {
    $draft = Announcement::factory()->draft()->create();
    $original = $draft->fresh()->getAttributes();

    Livewire::test(ViewAnnouncement::class, ['record' => $draft->id])
        ->mountAction('publishDraft')
        ->unmountAction();

    expect($draft->fresh()->getAttributes())->toBe($original);
    Mail::assertNothingSent();
});

it('does not offer publication for announcements that are already published or scheduled', function (bool $scheduled) {
    $announcement = Announcement::factory()->create(['starts_at' => $scheduled ? now()->addDay() : now()->subDay()]);

    Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id])
        ->assertActionHidden('publishDraft');
})->with([false, true]);

it('rejects invalid publication without modifying the draft', function (array $attributes) {
    $draft = Announcement::factory()->draft()->create($attributes);
    $original = $draft->fresh()->getAttributes();

    Livewire::test(ViewAnnouncement::class, ['record' => $draft->id])
        ->callAction('publishDraft')
        ->assertNotified('Announcement could not be published');

    expect($draft->fresh()->getAttributes())->toBe($original);
    $this->assertDatabaseCount('announcement_email_deliveries', 0);
    Mail::assertNothingSent();
})->with([
    'invalid link' => [['content' => '[Broken](https://)']],
    'expired' => fn (): array => ['starts_at' => now()->subWeek(), 'ends_at' => now()->subDay()],
    'expiry at publication' => fn (): array => ['ends_at' => now()],
    'expiry before scheduled time' => fn (): array => ['starts_at' => now()->addDays(2), 'ends_at' => now()->addDay()],
]);

it('rechecks draft state after the publication confirmation opens', function () {
    $draft = Announcement::factory()->draft()->create();
    $page = Livewire::test(ViewAnnouncement::class, ['record' => $draft->id])->mountAction('publishDraft');
    $draft->update(['is_draft' => false, 'starts_at' => now()->subHour()]);
    $original = $draft->fresh()->getAttributes();

    $page->callMountedAction();

    expect($draft->fresh()->getAttributes())->toBe($original);
    Mail::assertNothingSent();
});

it('rechecks admin access when publication is confirmed', function () {
    $draft = Announcement::factory()->draft()->create();
    $page = Livewire::test(ViewAnnouncement::class, ['record' => $draft->id])->mountAction('publishDraft');
    config(['mail.admin_address' => 'other@example.com']);

    $page->callMountedAction()->assertForbidden();

    expect($draft->fresh()->is_draft)->toBeTrue();
    Mail::assertNothingSent();
});

it('rejects a draft that expires while the confirmation is open', function () {
    $draft = Announcement::factory()->draft()->create(['ends_at' => now()->addMinute()]);
    $page = Livewire::test(ViewAnnouncement::class, ['record' => $draft->id])->mountAction('publishDraft');
    $this->travel(2)->minutes();

    $page->callMountedAction()->assertNotified('Announcement could not be published');

    expect($draft->fresh()->is_draft)->toBeTrue()
        ->and($draft->fresh()->email_broadcast_authorized_at)->toBeNull();
    Mail::assertNothingSent();
});

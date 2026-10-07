<?php

use App\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Filament\Resources\Announcements\Pages\ViewAnnouncement;
use App\Mail\AnnouncementEmail;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    config(['mail.admin_address' => 'admin@example.org']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.org']));
    Mail::fake();
    Livewire::withoutLazyLoading();
});

it('confirms one saved draft test without changing publication or delivery state', function (string $pageClass) {
    $draft = Announcement::factory()->draft()->create();
    $before = $draft->fresh()->getAttributes();
    $page = Livewire::test($pageClass, ['record' => $draft->id])->mountAction('sendTestEmail')
        ->assertActionMounted('sendTestEmail');
    expect(implode('', $page->effects['partials']))->toContain('admin@example.org', '[TEST] '.e($draft->title));
    Mail::assertNothingSent();

    $page->callMountedAction()->assertNotified('Test email submitted to the mail transport');

    Mail::assertSent(AnnouncementEmail::class, fn (AnnouncementEmail $mail): bool => $mail->isTest
        && $mail->hasTo('admin@example.org') && count($mail->to) === 1 && $mail->announcement->is($draft));
    Mail::assertSentCount(1);
    expect($draft->fresh()->getAttributes())->toBe($before);
    $this->assertDatabaseCount('announcement_email_deliveries', 0);
})->with(['details' => [ViewAnnouncement::class], 'editor' => [EditAnnouncement::class]]);

it('tests saved content instead of unsaved editor changes', function () {
    $draft = Announcement::factory()->draft()->create(['title' => 'Saved title']);

    Livewire::test(EditAnnouncement::class, ['record' => $draft->id])->fillForm(['title' => 'Unsaved title'])
        ->callAction('sendTestEmail');

    Mail::assertSent(AnnouncementEmail::class, fn (AnnouncementEmail $mail): bool => $mail->announcement->title === 'Saved title');
});

it('hides testing for published announcements', function () {
    $announcement = Announcement::factory()->create();

    Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id])->assertActionHidden('sendTestEmail')
        ->call('mountAction', 'sendTestEmail')->assertActionNotMounted();

    Mail::assertNothingSent();
});

it('rechecks draft eligibility after confirmation', function () {
    $draft = Announcement::factory()->draft()->create();
    $page = Livewire::test(ViewAnnouncement::class, ['record' => $draft->id])->mountAction('sendTestEmail');
    $draft->update(['is_draft' => false]);

    $page->callMountedAction();

    Mail::assertNothingSent();
});

it('reports invalid saved content without sending', function () {
    $draft = Announcement::factory()->draft()->create(['content' => '[Broken](https://)']);

    Livewire::test(ViewAnnouncement::class, ['record' => $draft->id])->callAction('sendTestEmail')
        ->assertNotified('Test email could not be sent');

    Mail::assertNothingSent();
});

it('rechecks admin access after confirmation', function () {
    $draft = Announcement::factory()->draft()->create();
    $page = Livewire::test(ViewAnnouncement::class, ['record' => $draft->id])->mountAction('sendTestEmail');
    config(['mail.admin_address' => 'admin@example.org,invalid']);

    $page->callMountedAction()->assertForbidden();

    Mail::assertNothingSent();
});

it('reports transport failure without changing the draft', function () {
    $draft = Announcement::factory()->draft()->create();
    $before = $draft->fresh()->getAttributes();
    Mail::shouldReceive('to')->once()->with('admin@example.org')->andReturnSelf();
    Mail::shouldReceive('send')->once()->andThrow(new TransportException('Rejected'));

    Livewire::test(ViewAnnouncement::class, ['record' => $draft->id])->callAction('sendTestEmail')
        ->assertNotified('Test email could not be sent');

    expect($draft->fresh()->getAttributes())->toBe($before);
    $this->assertDatabaseCount('announcement_email_deliveries', 0);
});

it('denies non admins access to the action page', function () {
    $draft = Announcement::factory()->draft()->create();
    $this->actingAs(User::factory()->create());

    Livewire::test(ViewAnnouncement::class, ['record' => $draft->id])->assertForbidden();

    Mail::assertNothingSent();
});

it('cancels a test confirmation without sending', function () {
    $draft = Announcement::factory()->draft()->create();

    Livewire::test(ViewAnnouncement::class, ['record' => $draft->id])->mountAction('sendTestEmail')
        ->unmountAction()->assertActionNotMounted();

    Mail::assertNothingSent();
});

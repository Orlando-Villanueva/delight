<?php

use App\Mail\AnnouncementEmail;
use App\Models\Announcement;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

it('sends one test only to the configured admin without changing the draft or deliveries', function () {
    config(['mail.admin_address' => 'admin@example.org']);
    $draft = Announcement::factory()->draft()->create();
    $original = $draft->fresh()->getAttributes();
    Mail::fake();

    $this->artisan('announcements:test-email', ['draft' => $draft->slug, '--yes' => true])
        ->expectsOutput('Test email submitted to the mail transport. Check your inbox to confirm delivery.')
        ->assertSuccessful();

    Mail::assertSent(AnnouncementEmail::class, function (AnnouncementEmail $mail) use ($draft) {
        return $mail->hasTo('admin@example.org') && count($mail->to) === 1
            && $mail->isTest && $mail->announcement->is($draft);
    });
    expect($draft->fresh()->getAttributes())->toBe($original);
    $this->assertDatabaseCount('announcement_email_deliveries', 0);
    $this->assertDatabaseCount('users', 0);
});

it('reports a dry run without sending', function () {
    config(['mail.admin_address' => 'admin@example.org']);
    $draft = Announcement::factory()->draft()->create();
    Mail::fake();

    $this->artisan('announcements:test-email', ['draft' => $draft->slug, '--dry-run' => true])
        ->expectsOutput('Test recipient: admin@example.org')
        ->expectsOutput('Dry run. No email sent.')
        ->assertSuccessful();

    Mail::assertNothingSent();
});

it('rejects a missing or non draft announcement', function (string $state) {
    $slug = 'missing';
    if ($state !== 'missing') {
        $slug = Announcement::factory()->create(['starts_at' => $state === 'scheduled' ? now()->addDay() : now()])->slug;
    }
    Mail::fake();

    $this->artisan('announcements:test-email', ['draft' => $slug, '--yes' => true])
        ->expectsOutput('Only an existing draft announcement can be tested.')
        ->assertFailed();

    Mail::assertNothingSent();
})->with(['missing', 'published', 'scheduled']);

it('rejects invalid admin configuration', function (?string $recipient) {
    config(['mail.admin_address' => $recipient]);
    $draft = Announcement::factory()->draft()->create();
    Mail::fake();

    $this->artisan('announcements:test-email', ['draft' => $draft->slug, '--yes' => true])
        ->expectsOutput('Configure a valid ADMIN_EMAIL before sending a test.')
        ->assertFailed();

    Mail::assertNothingSent();
})->with([null, '', 'invalid']);

it('requires explicit confirmation without interaction', function () {
    $draft = Announcement::factory()->draft()->create();
    Mail::fake();

    $this->artisan('announcements:test-email', ['draft' => $draft->slug, '--no-interaction' => true])
        ->expectsOutput('Test sending requires --yes when running without interactive confirmation.')
        ->assertFailed();

    Mail::assertNothingSent();
});

it('cancels a declined test send', function () {
    $draft = Announcement::factory()->draft()->create();
    Mail::fake();

    $this->artisan('announcements:test-email', ['draft' => $draft->slug])
        ->expectsConfirmation('Send one test email to this admin inbox?', 'no')
        ->assertFailed();

    Mail::assertNothingSent();
});

it('returns failure when the transport rejects a test', function () {
    $draft = Announcement::factory()->draft()->create();
    Mail::shouldReceive('to')->once()->andReturnSelf();
    Mail::shouldReceive('send')->once()->andThrow(new TransportException('Daily quota exceeded'));

    $this->artisan('announcements:test-email', ['draft' => $draft->slug, '--yes' => true])
        ->expectsOutput('The mail transport rejected the test email. Check the application logs for details.')
        ->assertFailed();

    $this->assertDatabaseCount('announcement_email_deliveries', 0);
    expect($draft->fresh()->is_draft)->toBeTrue();
});

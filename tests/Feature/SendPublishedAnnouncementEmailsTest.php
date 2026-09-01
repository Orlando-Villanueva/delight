<?php

use App\Mail\AnnouncementEmail;
use App\Models\Announcement;
use App\Models\AnnouncementEmailDelivery;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;

afterEach(function () {
    Carbon::setTestNow();
});

it('finalizes only due authorized audiences and sends to eligible registered users', function () {
    Mail::fake();
    Carbon::setTestNow('2026-08-31 12:00:00');
    $eligible = User::factory()->create(['created_at' => now()->subDay()]);
    $unverified = User::factory()->unverified()->create(['created_at' => now()->subDay()]);
    User::factory()->create([
        'created_at' => now()->subDay(),
        'marketing_emails_opted_out_at' => now()->subHour(),
    ]);
    User::factory()->create([
        'email' => 'not-an-email',
        'created_at' => now()->subDay(),
    ]);
    User::factory()->create(['created_at' => now()->addSecond()]);
    $dueAnnouncement = Announcement::factory()->create([
        'starts_at' => now(),
        'email_broadcast_authorized_at' => now()->subMinute(),
    ]);
    $historicalAnnouncement = Announcement::factory()->create(['starts_at' => now()->subDay()]);
    $futureAnnouncement = Announcement::factory()->create([
        'starts_at' => now()->addHour(),
        'email_broadcast_authorized_at' => now(),
    ]);

    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    expect($dueAnnouncement->fresh()->email_audience_finalized_at)->not->toBeNull()
        ->and($historicalAnnouncement->fresh()->email_audience_finalized_at)->toBeNull()
        ->and($futureAnnouncement->fresh()->email_audience_finalized_at)->toBeNull()
        ->and($dueAnnouncement->emailDeliveries()->count())->toBe(2)
        ->and($dueAnnouncement->emailDeliveries()->pluck('user_id')->all())
        ->toEqualCanonicalizing([$eligible->id, $unverified->id]);

    Mail::assertSentCount(2);
    Mail::assertSent(AnnouncementEmail::class, fn (AnnouncementEmail $mail): bool => $mail->hasTo($eligible->email));
    Mail::assertSent(AnnouncementEmail::class, fn (AnnouncementEmail $mail): bool => $mail->hasTo($unverified->email));
});

it('does not recreate an audience or resend a successful delivery', function () {
    Mail::fake();
    Carbon::setTestNow('2026-08-31 12:00:00');
    $user = User::factory()->create(['created_at' => now()->subDay()]);
    $announcement = Announcement::factory()->create([
        'starts_at' => now(),
        'email_broadcast_authorized_at' => now(),
    ]);

    $this->artisan('announcements:send-published-emails')->assertSuccessful();
    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    expect($announcement->emailDeliveries()->count())->toBe(1)
        ->and($announcement->emailDeliveries()->first()?->attempt_count)->toBe(1);
    Mail::assertSentCount(1);
    Mail::assertSent(AnnouncementEmail::class, fn (AnnouncementEmail $mail): bool => $mail->hasTo($user->email));
});

it('logs meaningful processing and broadcast completion once', function () {
    Mail::fake();
    Log::spy();
    Carbon::setTestNow('2026-09-01 12:00:00');
    User::factory()->create(['created_at' => now()->subDay()]);
    $announcement = Announcement::factory()->create([
        'starts_at' => now(),
        'email_broadcast_authorized_at' => now(),
    ]);

    $this->artisan('announcements:send-published-emails')
        ->expectsOutputToContain('1 broadcasts completed, 0 pending')
        ->assertSuccessful();

    expect($announcement->fresh()->email_broadcast_completed_at)->not->toBeNull();
    Log::shouldHaveReceived('info')
        ->with('Announcement email broadcast completed.', Mockery::on(
            fn (array $context): bool => $context['announcement_id'] === $announcement->id
                && $context['recipient_count'] === 1
                && $context['sent_count'] === 1
                && $context['duration_seconds'] === 0
        ))
        ->once();
    Log::shouldHaveReceived('info')
        ->with('Announcement email processing run completed.', Mockery::on(
            fn (array $context): bool => $context['audiences_finalized'] === 1
                && $context['sent'] === 1
                && $context['broadcasts_completed'] === 1
                && $context['pending_count'] === 0
                && is_int($context['duration_ms'])
        ))
        ->once();

    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    Log::shouldHaveReceived('info')
        ->with('Announcement email broadcast completed.', Mockery::any())
        ->once();
    Log::shouldHaveReceived('info')
        ->with('Announcement email processing run completed.', Mockery::any())
        ->once();
});

it('continues an audience larger than one run without duplicating recipients', function () {
    Mail::fake();
    Carbon::setTestNow('2026-08-31 12:00:00');
    User::factory()->count(160)->create(['created_at' => now()->subDay()]);
    $announcement = Announcement::factory()->create([
        'starts_at' => now(),
        'email_broadcast_authorized_at' => now(),
    ]);

    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    expect($announcement->emailDeliveries()->count())->toBe(160)
        ->and($announcement->emailDeliveries()->whereNotNull('sent_at')->count())->toBe(100)
        ->and($announcement->emailDeliveries()->whereNull('sent_at')->count())->toBe(60)
        ->and($announcement->fresh()->email_broadcast_completed_at)->toBeNull();
    Mail::assertSentCount(100);

    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    expect($announcement->emailDeliveries()->count())->toBe(160)
        ->and($announcement->emailDeliveries()->whereNotNull('sent_at')->count())->toBe(160)
        ->and($announcement->emailDeliveries()->where('attempt_count', 1)->count())->toBe(160)
        ->and($announcement->fresh()->email_broadcast_completed_at)->not->toBeNull();
    Mail::assertSentCount(160);
});

it('skips a recipient who opted out after audience finalization', function () {
    Mail::fake();
    $user = User::factory()->create();
    $announcement = Announcement::factory()->create([
        'email_broadcast_authorized_at' => now(),
        'email_audience_finalized_at' => now(),
    ]);
    $delivery = AnnouncementEmailDelivery::factory()->create([
        'announcement_id' => $announcement->id,
        'user_id' => $user->id,
        'recipient_email' => $user->email,
    ]);
    $user->update(['marketing_emails_opted_out_at' => now()]);

    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    Mail::assertNothingSent();
    expect($delivery->fresh()->skipped_at)->not->toBeNull()
        ->and($delivery->fresh()->attempt_count)->toBe(0);
});

it('schedules one clearly transient failure for five minutes later', function () {
    Carbon::setTestNow('2026-08-31 12:00:00');
    $delivery = pendingAnnouncementEmailDelivery();
    Mail::shouldReceive('to')->once()->andThrow(transientAnnouncementEmailException());

    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    expect($delivery->fresh()->attempt_count)->toBe(1)
        ->and($delivery->fresh()->next_attempt_at?->equalTo(now()->addMinutes(5)))->toBeTrue()
        ->and($delivery->fresh()->failed_at)->toBeNull();
});

it('submits the one retry when its five minute delay has elapsed', function () {
    Mail::fake();
    Carbon::setTestNow('2026-08-31 12:05:00');
    $delivery = pendingAnnouncementEmailDelivery([
        'attempt_count' => 1,
        'next_attempt_at' => now(),
        'failure_reason' => 'Mailgun unavailable (code 503).',
    ]);

    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    expect($delivery->fresh()->attempt_count)->toBe(2)
        ->and($delivery->fresh()->sent_at)->not->toBeNull();
    Mail::assertSentCount(1);
});

it('marks a second transient failure terminally failed', function () {
    Carbon::setTestNow('2026-08-31 12:00:00');
    $delivery = pendingAnnouncementEmailDelivery();
    Mail::shouldReceive('to')->twice()->andThrow(transientAnnouncementEmailException());

    $this->artisan('announcements:send-published-emails')->assertSuccessful();
    Carbon::setTestNow(now()->addMinutes(5));
    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    expect($delivery->fresh()->attempt_count)->toBe(2)
        ->and($delivery->fresh()->next_attempt_at)->toBeNull()
        ->and($delivery->fresh()->failed_at)->not->toBeNull()
        ->and($delivery->fresh()->failure_reason)->toContain('Mailgun unavailable');
});

it('marks a permanent submission failure terminal on its first attempt', function () {
    $delivery = pendingAnnouncementEmailDelivery();
    Mail::shouldReceive('to')->once()->andThrow(permanentAnnouncementEmailException());

    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    expect($delivery->fresh()->attempt_count)->toBe(1)
        ->and($delivery->fresh()->next_attempt_at)->toBeNull()
        ->and($delivery->fresh()->failed_at)->not->toBeNull();
});

it('schedules a temporary smtp rejection for five minutes later', function () {
    Carbon::setTestNow('2026-08-31 12:00:00');
    $delivery = pendingAnnouncementEmailDelivery();
    Mail::shouldReceive('to')->once()->andThrow(
        new UnexpectedResponseException('SMTP server temporarily unavailable.', 451)
    );

    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    expect($delivery->fresh()->attempt_count)->toBe(1)
        ->and($delivery->fresh()->next_attempt_at?->equalTo(now()->addMinutes(5)))->toBeTrue()
        ->and($delivery->fresh()->failed_at)->toBeNull()
        ->and($delivery->fresh()->uncertain_at)->toBeNull();
});

it('marks a permanent smtp rejection terminal on its first attempt', function () {
    $delivery = pendingAnnouncementEmailDelivery();
    Mail::shouldReceive('to')->once()->andThrow(
        new UnexpectedResponseException('SMTP recipient rejected.', 550)
    );

    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    expect($delivery->fresh()->attempt_count)->toBe(1)
        ->and($delivery->fresh()->next_attempt_at)->toBeNull()
        ->and($delivery->fresh()->failed_at)->not->toBeNull()
        ->and($delivery->fresh()->uncertain_at)->toBeNull();
});

it('fails visibly and preserves an unexpected sending error for uncertain reconciliation', function () {
    Carbon::setTestNow('2026-08-31 12:00:00');
    $delivery = pendingAnnouncementEmailDelivery();
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Announcement view could not render.'));

    expect(fn () => Artisan::call('announcements:send-published-emails'))
        ->toThrow(RuntimeException::class, 'Announcement view could not render.');

    expect($delivery->fresh()->attempt_count)->toBe(1)
        ->and($delivery->fresh()->sending_at)->not->toBeNull()
        ->and($delivery->fresh()->failed_at)->toBeNull()
        ->and($delivery->fresh()->uncertain_at)->toBeNull()
        ->and($delivery->fresh()->failure_reason)->toBeNull();

    Carbon::setTestNow(now()->addMinutes(16));

    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    expect($delivery->fresh()->sending_at)->toBeNull()
        ->and($delivery->fresh()->failed_at)->toBeNull()
        ->and($delivery->fresh()->uncertain_at)->not->toBeNull()
        ->and($delivery->fresh()->failure_reason)
        ->toBe('Delivery outcome is uncertain after an interrupted send attempt.');
});

it('does not report a provider-accepted delivery as failed when recording success breaks', function () {
    Mail::fake();
    Carbon::setTestNow('2026-08-31 12:00:00');
    $delivery = pendingAnnouncementEmailDelivery();

    AnnouncementEmailDelivery::saving(function (AnnouncementEmailDelivery $savingDelivery): void {
        if ($savingDelivery->sent_at !== null) {
            throw new RuntimeException('Delivery success could not be recorded.');
        }
    });

    expect(fn () => Artisan::call('announcements:send-published-emails'))
        ->toThrow(RuntimeException::class, 'Delivery success could not be recorded.');

    Mail::assertSentCount(1);
    expect($delivery->fresh()->attempt_count)->toBe(1)
        ->and($delivery->fresh()->sending_at)->not->toBeNull()
        ->and($delivery->fresh()->sent_at)->toBeNull()
        ->and($delivery->fresh()->failed_at)->toBeNull();

    Carbon::setTestNow(now()->addMinutes(16));

    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    expect($delivery->fresh()->sending_at)->toBeNull()
        ->and($delivery->fresh()->failed_at)->toBeNull()
        ->and($delivery->fresh()->uncertain_at)->not->toBeNull();
});

it('marks an interrupted send uncertain without retrying it', function () {
    Mail::fake();
    Carbon::setTestNow('2026-08-31 12:30:00');
    $delivery = pendingAnnouncementEmailDelivery([
        'attempt_count' => 1,
        'sending_at' => now()->subMinutes(16),
    ]);

    $this->artisan('announcements:send-published-emails')->assertSuccessful();
    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    Mail::assertNothingSent();
    expect($delivery->fresh()->uncertain_at)->not->toBeNull()
        ->and($delivery->fresh()->attempt_count)->toBe(1);
});

it('an exact emergency retry accepts only a terminally failed delivery', function () {
    Mail::fake();
    $failed = pendingAnnouncementEmailDelivery([
        'attempt_count' => 2,
        'failed_at' => now(),
        'failure_reason' => 'Mailgun unavailable',
    ]);
    $sent = pendingAnnouncementEmailDelivery(['sent_at' => now()]);
    $uncertain = pendingAnnouncementEmailDelivery(['uncertain_at' => now()]);

    $this->artisan("announcements:send-published-emails --retry-delivery={$failed->id}")
        ->assertSuccessful();

    expect($failed->fresh()->sent_at)->not->toBeNull()
        ->and($sent->fresh()->attempt_count)->toBe(0)
        ->and($uncertain->fresh()->attempt_count)->toBe(0);
    Mail::assertSentCount(1);

    $this->artisan("announcements:send-published-emails --retry-delivery={$uncertain->id}")
        ->assertFailed();
});

it('registers queue-less announcement delivery every five minutes with scheduler protection', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'announcements:send-published-emails'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/5 * * * *')
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe(30)
        ->and($event->runInBackground)->toBeTrue();
});

it('does not register the obsolete bulk PWA announcement command', function () {
    expect(Artisan::all())->not->toHaveKey('send:pwa-announcement');
});

/**
 * @param  array<string, mixed>  $overrides
 */
function pendingAnnouncementEmailDelivery(array $overrides = []): AnnouncementEmailDelivery
{
    $user = User::factory()->create();
    $announcement = Announcement::factory()->create([
        'email_broadcast_authorized_at' => now(),
        'email_audience_finalized_at' => now(),
    ]);

    return AnnouncementEmailDelivery::factory()->create(array_merge([
        'announcement_id' => $announcement->id,
        'user_id' => $user->id,
        'recipient_email' => $user->email,
    ], $overrides));
}

function transientAnnouncementEmailException(): HttpTransportException
{
    return new HttpTransportException(
        'Mailgun unavailable (code 503).',
        new MockResponse('', ['http_code' => 503])
    );
}

function permanentAnnouncementEmailException(): HttpTransportException
{
    return new HttpTransportException(
        'Recipient rejected (code 400).',
        new MockResponse('', ['http_code' => 400])
    );
}

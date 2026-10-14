<?php

use App\Mail\AnnouncementEmail;
use App\Models\Announcement;
use App\Models\AnnouncementEmailDelivery;
use App\Models\User;
use App\Services\AnnouncementEmailDeliveryService;
use App\Services\OperationalFailureReporter;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Log\Logger as LaravelLogger;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use Sentry\ClientBuilder;
use Sentry\Serializer\PayloadSerializer;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Tests\Support\RecordingSentryTransport;

use function Pest\Laravel\mock;

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
    $transport = RecordingSentryTransport::bind();
    Carbon::setTestNow('2026-08-31 12:00:00');
    $delivery = pendingAnnouncementEmailDelivery();
    Mail::shouldReceive('to')->twice()->andThrow(transientAnnouncementEmailException());

    $this->artisan('announcements:send-published-emails')->assertSuccessful();
    expect($transport->events)->toBeEmpty();
    Carbon::setTestNow(now()->addMinutes(5));
    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    expect($delivery->fresh()->attempt_count)->toBe(2)
        ->and($delivery->fresh()->next_attempt_at)->toBeNull()
        ->and($delivery->fresh()->failed_at)->not->toBeNull()
        ->and($delivery->fresh()->failure_reason)->toContain('Mailgun unavailable');
    expect($transport->events)->toHaveCount(1);
});

it('reports newly failed or uncertain announcement outcomes once per run without changing persisted outcomes', function (string $monitoringMode, string $outcome): void {
    $logStream = fopen('php://memory', 'w+');
    Log::swap(new LaravelLogger(new MonologLogger('announcement-test', [new StreamHandler($logStream)])));
    $deliveries = [pendingAnnouncementEmailDelivery(), pendingAnnouncementEmailDelivery()];
    config(['sentry.environment' => 'testing', 'sentry.release' => 'delight@test-release']);
    $transport = RecordingSentryTransport::bind($monitoringMode === 'transport unavailable');
    if ($monitoringMode === 'disabled') {
        config(['sentry.dsn' => null]);
    }
    if ($monitoringMode === 'cache unavailable') {
        $lock = Cache::lock('send-published-announcement-emails', 1200);
        Cache::shouldReceive('lock')->with('send-published-announcement-emails', 1200)->twice()->andReturn($lock);
        Cache::shouldReceive('add')->once()->andThrow(new RuntimeException('Cache unavailable'));
    }
    Mail::shouldReceive('to')->twice()->andThrow(new HttpTransportException(
        'Provider response could not be processed.',
        new MockResponse('', ['http_code' => $outcome === 'terminal_failure' ? 400 : 200]),
        previous: new RuntimeException('private-recipient@example.com'),
    ));

    $this->artisan('announcements:send-published-emails')->assertSuccessful();
    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    rewind($logStream);
    $logOutput = stream_get_contents($logStream);
    fclose($logStream);
    expect($logOutput)->toContain('Announcement email delivery attempt did not succeed.')
        ->not->toContain('private-recipient@example.com');
    foreach ($deliveries as $delivery) {
        expect($delivery->fresh()->failed_at !== null)->toBe($outcome === 'terminal_failure');
        expect($delivery->fresh()->uncertain_at !== null)->toBe($outcome === 'uncertain_outcome');
        expect($delivery->fresh()->attempt_count)->toBe(1);
        expect($delivery->fresh()->next_attempt_at)->toBeNull();
    }
    expect($transport->events)->toHaveCount(in_array($monitoringMode, ['available', 'transport unavailable'], true) ? 1 : 0);
    if ($transport->events !== []) {
        $event = $transport->events[0];
        expect($logOutput)->toContain($outcome === 'terminal_failure'
            ? 'Announcement email processing recorded terminal delivery failures.'
            : 'Announcement email processing recorded uncertain delivery outcomes.');
        expect($event->getTags())->toBe(['operation' => 'announcement_email_delivery', 'outcome' => $outcome]);
        expect($event->getEnvironment())->toBe('testing');
        expect($event->getRelease())->toBe('delight@test-release');
        expect($event->getExceptions())->toHaveCount(1);
        $payload = (new PayloadSerializer(app(ClientBuilder::class)->getOptions()))->serialize($event);
        expect($payload)->not->toContain('private-recipient@example.com')->not->toContain('Provider response could not be processed');
    }
})->with(['available', 'transport unavailable', 'cache unavailable', 'disabled'])
    ->with(['terminal_failure', 'uncertain_outcome']);

it('suppresses repeated announcement incidents for an hour with separate outcome gates', function (string $outcome): void {
    $this->travelTo(now());
    config(['cache.default' => 'database']);
    $transport = RecordingSentryTransport::bind();
    Mail::shouldReceive('to')->times(3)->andThrow($outcome === 'terminal_failure'
        ? permanentAnnouncementEmailException()
        : new TransportException('Unknown send outcome'));

    if ($outcome === 'terminal_failure') {
        app(OperationalFailureReporter::class)->reportAnnouncementEmailUncertainOutcome();
    } else {
        app(OperationalFailureReporter::class)->reportAnnouncementEmailTerminalFailure();
    }
    pendingAnnouncementEmailDelivery();
    $this->artisan('announcements:send-published-emails')->assertSuccessful();
    expect($transport->events)->toHaveCount(2);
    $this->travel(59)->minutes();
    pendingAnnouncementEmailDelivery();
    $this->artisan('announcements:send-published-emails')->assertSuccessful();
    expect($transport->events)->toHaveCount(2);
    $this->travel(1)->minutes();
    pendingAnnouncementEmailDelivery();
    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    expect($transport->events)->toHaveCount(3);
    report(new LogicException('Unrelated operation'));
    expect($transport->events[3]->getTags())->toBeEmpty();
})->with(['terminal_failure', 'uncertain_outcome']);

it('keeps nonterminal and expected announcement outcomes quiet', function (string $outcome): void {
    $transport = RecordingSentryTransport::bind();
    $delivery = pendingAnnouncementEmailDelivery($outcome === 'historical failure' ? ['failed_at' => now()] : []);
    if ($outcome === 'skipped') {
        $delivery->user->update(['marketing_emails_opted_out_at' => now()]);
    }
    if ($outcome === 'retryable') {
        Mail::shouldReceive('to')->once()->andThrow(transientAnnouncementEmailException());
    } else {
        Mail::fake();
    }

    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    expect($transport->events)->toBeEmpty();
})->with(['sent', 'skipped', 'retryable', 'historical failure']);

it('marks a permanent submission failure terminal on its first attempt', function () {
    $delivery = pendingAnnouncementEmailDelivery();
    Mail::shouldReceive('to')->once()->andThrow(permanentAnnouncementEmailException());

    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    expect($delivery->fresh()->attempt_count)->toBe(1)
        ->and($delivery->fresh()->next_attempt_at)->toBeNull()
        ->and($delivery->fresh()->failed_at)->not->toBeNull();
});

it('quarantines an accepted http response with an unreadable provider body', function () {
    $delivery = pendingAnnouncementEmailDelivery();
    Mail::shouldReceive('to')->once()->andThrow(
        new HttpTransportException(
            'Unable to decode the provider response.',
            new MockResponse('', ['http_code' => 200])
        )
    );

    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    expect($delivery->fresh()->attempt_count)->toBe(1)
        ->and($delivery->fresh()->next_attempt_at)->toBeNull()
        ->and($delivery->fresh()->failed_at)->toBeNull()
        ->and($delivery->fresh()->uncertain_at)->not->toBeNull();
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
    $transport = RecordingSentryTransport::bind();
    Carbon::setTestNow('2026-08-31 12:00:00');
    $delivery = pendingAnnouncementEmailDelivery();
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Announcement view could not render.'));

    expect(fn () => Artisan::call('announcements:send-published-emails'))
        ->toThrow(RuntimeException::class, 'Announcement view could not render.');
    expect($transport->events)->toBeEmpty();

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
    expect($transport->events)->toHaveCount(1);
    expect($transport->events[0]->getTags())->toBe(['operation' => 'announcement_email_delivery', 'outcome' => 'uncertain_outcome']);
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
    $transport = RecordingSentryTransport::bind();
    Mail::fake();
    Carbon::setTestNow('2026-08-31 12:30:00');
    $delivery = pendingAnnouncementEmailDelivery([
        'attempt_count' => 1,
        'sending_at' => now()->subMinutes(16),
    ]);

    $this->artisan('announcements:send-published-emails')->assertSuccessful();
    Carbon::setTestNow(now()->addHour());
    $this->artisan('announcements:send-published-emails')->assertSuccessful();

    Mail::assertNothingSent();
    expect($delivery->fresh()->uncertain_at)->not->toBeNull()
        ->and($delivery->fresh()->attempt_count)->toBe(1);
    expect($transport->events)->toHaveCount(1);
});

it('an exact emergency retry accepts only a terminally failed delivery', function () {
    $transport = RecordingSentryTransport::bind();
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
    expect($transport->events)->toBeEmpty();
});

it('fails cleanly if an emergency retry delivery disappears before sending', function () {
    $delivery = pendingAnnouncementEmailDelivery([
        'attempt_count' => 2,
        'failed_at' => now(),
        'failure_reason' => 'Mailgun unavailable',
    ]);
    $deliveryService = mock(AnnouncementEmailDeliveryService::class);
    $deliveryService->shouldReceive('retryFailedDelivery')
        ->once()
        ->andReturnUsing(function (AnnouncementEmailDelivery $candidate): bool {
            $candidate->delete();

            return true;
        });
    $deliveryService->shouldNotReceive('processDelivery');

    $this->artisan("announcements:send-published-emails --retry-delivery={$delivery->id}")
        ->expectsOutputToContain('The announcement email delivery no longer exists.')
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

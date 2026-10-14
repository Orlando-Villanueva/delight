<?php

use App\Mail\OnboardingReminderEmail;
use App\Models\ReadingLog;
use App\Models\User;
use App\Services\EmailService;
use App\Services\OnboardingReminderProcessor;
use App\Services\OperationalFailureReporter;
use Carbon\Carbon;
use Illuminate\Log\Logger as LaravelLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use Sentry\ClientBuilder;
use Sentry\Serializer\PayloadSerializer;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\Support\RecordingSentryTransport;

beforeEach(function () {
    Mail::fake();
    $this->now = Carbon::create(2026, 2, 23, 9, 0, 0);
    Carbon::setTestNow($this->now);

    $this->requestedAt = $this->now->copy()->subDay();
    $this->user = User::factory()->create([
        'onboarding_reminder_requested_at' => $this->requestedAt,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('sends once and clears marker when reminder is due and user is eligible', function () {
    $processor = app(OnboardingReminderProcessor::class);
    $status = $processor->process($this->user->id, $this->now);

    Mail::assertSent(OnboardingReminderEmail::class, function (OnboardingReminderEmail $mail) {
        return $mail->hasTo($this->user->email);
    });

    expect($status)->toBe(OnboardingReminderProcessor::STATUS_SENT);
    expect($this->user->fresh()->onboarding_reminder_requested_at)->toBeNull();
});

it('does not send and clears marker when user has a reading before send time', function () {
    ReadingLog::factory()->for($this->user)->create([
        'date_read' => $this->now->toDateString(),
    ]);

    $processor = app(OnboardingReminderProcessor::class);
    $status = $processor->process($this->user->id, $this->now);

    Mail::assertNothingSent();
    expect($status)->toBe(OnboardingReminderProcessor::STATUS_SKIPPED);
    expect($this->user->fresh()->onboarding_reminder_requested_at)->toBeNull();
});

it('does not send and clears marker when user opted out after scheduling', function () {
    $this->user->update([
        'marketing_emails_opted_out_at' => $this->now->copy()->subHour(),
    ]);

    $processor = app(OnboardingReminderProcessor::class);
    $status = $processor->process($this->user->id, $this->now);

    Mail::assertNothingSent();
    expect($status)->toBe(OnboardingReminderProcessor::STATUS_SKIPPED);
    expect($this->user->fresh()->onboarding_reminder_requested_at)->toBeNull();
});

it('does nothing when reminder marker is already missing', function () {
    $this->user->update([
        'onboarding_reminder_requested_at' => null,
    ]);

    $processor = app(OnboardingReminderProcessor::class);
    $status = $processor->process($this->user->id, $this->now);

    Mail::assertNothingSent();
    expect($status)->toBe(OnboardingReminderProcessor::STATUS_SKIPPED);
    expect($this->user->fresh()->onboarding_reminder_requested_at)->toBeNull();
});

it('does nothing when reminder is not yet due', function () {
    $notYetDueRequestedAt = $this->now->copy()->subHours(2);
    $this->user->update([
        'onboarding_reminder_requested_at' => $notYetDueRequestedAt,
    ]);

    $processor = app(OnboardingReminderProcessor::class);
    $status = $processor->process($this->user->id, $this->now);

    Mail::assertNothingSent();
    expect($status)->toBe(OnboardingReminderProcessor::STATUS_SKIPPED);
    expect($this->user->fresh()->onboarding_reminder_requested_at?->equalTo($notYetDueRequestedAt))->toBeTrue();
});

it('does not send reminders older than 48 hours and clears the stale marker', function () {
    $expiredRequestedAt = $this->now->copy()->subDays(3);
    $this->user->update([
        'onboarding_reminder_requested_at' => $expiredRequestedAt,
    ]);

    $processor = app(OnboardingReminderProcessor::class);
    $status = $processor->process($this->user->id, $this->now);

    Mail::assertNothingSent();
    expect($status)->toBe(OnboardingReminderProcessor::STATUS_SKIPPED);
    expect($this->user->fresh()->onboarding_reminder_requested_at)->toBeNull();
});

it('does nothing when user no longer exists', function () {
    $processor = app(OnboardingReminderProcessor::class);
    $status = $processor->process(999999, $this->now);

    Mail::assertNothingSent();
    expect($status)->toBe(OnboardingReminderProcessor::STATUS_SKIPPED);
});

it('does not send when user opts out between marker clear and callback send', function () {
    $raceEmailService = $this->mock(EmailService::class);
    $raceEmailService->shouldReceive('sendWithErrorHandling')
        ->once()
        ->andReturnUsing(function (callable $mailCallback, string $context = 'email') {
            $this->user->update([
                'marketing_emails_opted_out_at' => $this->now->copy()->addMinute(),
            ]);

            $mailCallback();

            return true;
        });

    $processor = new OnboardingReminderProcessor($raceEmailService);
    $status = $processor->process($this->user->id, $this->now);

    Mail::assertNothingSent();

    $freshUser = $this->user->fresh();
    expect($status)->toBe(OnboardingReminderProcessor::STATUS_SKIPPED);
    expect($freshUser->marketing_emails_opted_out_at)->not->toBeNull();
    expect($freshUser->onboarding_reminder_requested_at)->toBeNull();
});

it('restores marker when email sending fails', function () {
    $failingEmailService = $this->mock(EmailService::class);
    $failingEmailService->shouldReceive('sendWithErrorHandling')
        ->once()
        ->andReturn(false);

    $processor = new OnboardingReminderProcessor($failingEmailService);
    $status = $processor->process($this->user->id, $this->now);

    expect($status)->toBe(OnboardingReminderProcessor::STATUS_FAILED);
    expect($this->user->fresh()->onboarding_reminder_requested_at?->equalTo($this->requestedAt))->toBeTrue();
});

it('scheduled command processes due reminders and reports a summary', function () {
    $transport = RecordingSentryTransport::bind();
    $notDueUser = User::factory()->create([
        'onboarding_reminder_requested_at' => $this->now->copy()->subHours(3),
    ]);

    $this->artisan('onboarding:send-reminders')
        ->expectsOutput('Onboarding reminders processed: 1 sent, 1 skipped, 0 failed.')
        ->assertSuccessful();

    Mail::assertSent(OnboardingReminderEmail::class, function (OnboardingReminderEmail $mail) {
        return $mail->hasTo($this->user->email);
    });

    expect($this->user->fresh()->onboarding_reminder_requested_at)->toBeNull();
    expect($notDueUser->fresh()->onboarding_reminder_requested_at?->equalTo($this->now->copy()->subHours(3)))->toBeTrue();
    expect($transport->events)->toBeEmpty();
});

it('scheduled command continues after a failed reminder send', function () {
    $transport = RecordingSentryTransport::bind();
    Mail::fake();

    $secondUser = User::factory()->create([
        'onboarding_reminder_requested_at' => $this->requestedAt,
    ]);

    $emailService = $this->mock(EmailService::class);
    $emailService->shouldReceive('sendWithErrorHandling')
        ->twice()
        ->andReturnUsing(function (callable $callback) {
            $userId = (new ReflectionFunction($callback))->getStaticVariables()['userId'] ?? null;

            if ($userId === $this->user->id) {
                return false;
            }

            $callback();

            return true;
        });

    $this->app->instance(EmailService::class, $emailService);

    $this->artisan('onboarding:send-reminders')
        ->expectsOutput('Onboarding reminders processed: 1 sent, 0 skipped, 1 failed.')
        ->assertSuccessful();

    Mail::assertSent(OnboardingReminderEmail::class, function (OnboardingReminderEmail $mail) use ($secondUser) {
        return $mail->hasTo($secondUser->email);
    });

    expect($this->user->fresh()->onboarding_reminder_requested_at?->equalTo($this->requestedAt))->toBeTrue();
    expect($secondUser->fresh()->onboarding_reminder_requested_at)->toBeNull();
    expect($transport->events)->toHaveCount(1);
});

it('aggregates onboarding failures safely while preserving reminder restoration', function (string $failureMode, string $monitoringMode): void {
    $secondUser = User::factory()->create(['onboarding_reminder_requested_at' => $this->requestedAt]);
    config(['sentry.environment' => 'testing', 'sentry.release' => 'delight@test-release']);
    $transport = RecordingSentryTransport::bind($monitoringMode === 'transport unavailable');
    $logs = new TestHandler;
    Log::swap(new LaravelLogger(new MonologLogger('onboarding-test', [$logs])));
    if ($monitoringMode === 'disabled') {
        config(['sentry.dsn' => null]);
    }
    if ($monitoringMode === 'cache unavailable') {
        Cache::shouldReceive('add')->once()->andThrow(new RuntimeException('Cache unavailable'));
    }
    if ($failureMode === 'false result') {
        $this->mock(EmailService::class)->shouldReceive('sendWithErrorHandling')->twice()->andReturn(false);
    } elseif ($failureMode === 'caught throwable') {
        $this->mock(EmailService::class)->shouldReceive('sendWithErrorHandling')->twice()
            ->andThrow(new Error('private-recipient@example.com'));
    } else {
        Mail::shouldReceive('to')->twice()->andThrow(new TransportException(
            'private-recipient@example.com', previous: new RuntimeException('private-provider-secret'),
        ));
    }

    $this->artisan('onboarding:send-reminders')
        ->expectsOutput('Onboarding reminders processed: 0 sent, 0 skipped, 2 failed.')
        ->assertSuccessful();

    foreach ([$this->user, $secondUser] as $user) {
        expect($user->fresh()->onboarding_reminder_requested_at?->equalTo($this->requestedAt))->toBeTrue();
    }
    $expectedReports = in_array($monitoringMode, ['available', 'transport unavailable'], true) ? 1 : 0;
    expect($transport->events)->toHaveCount($expectedReports);
    $reportLogs = array_values(array_filter($logs->getRecords(), fn ($record): bool => isset($record->context['exception'])));
    expect($reportLogs)->toHaveCount($expectedReports);
    if ($expectedReports === 1) {
        $event = $transport->events[0];
        expect($event->getTags())->toBe(['operation' => 'onboarding_reminder', 'outcome' => 'processing_failure']);
        expect($event->getEnvironment())->toBe('testing');
        expect($event->getRelease())->toBe('delight@test-release');
        expect($event->getExceptions())->toHaveCount(1);
        $payload = (new PayloadSerializer(app(ClientBuilder::class)->getOptions()))->serialize($event);
        expect($payload)->not->toContain('private-recipient@example.com')->not->toContain('private-provider-secret');
        $logOutput = (new LineFormatter)->format($reportLogs[0]);
        expect($logOutput)->toContain('Onboarding reminder processing recorded failures.')
            ->not->toContain('private-recipient@example.com')->not->toContain('private-provider-secret');
    }
})->with([
    'false result' => ['false result', 'available'],
    'outer catch' => ['caught throwable', 'available'],
    'caught mail transport' => ['caught transport', 'available'],
    'unavailable transport' => ['caught transport', 'transport unavailable'],
    'unavailable cache' => ['caught transport', 'cache unavailable'],
    'disabled capture' => ['caught transport', 'disabled'],
]);

it('bounds repeated onboarding failures independently and restores reporting after an hour', function (): void {
    config(['cache.default' => 'database']);
    $transport = RecordingSentryTransport::bind();
    $this->mock(EmailService::class)->shouldReceive('sendWithErrorHandling')->times(3)->andReturn(false);

    app(OperationalFailureReporter::class)->reportAnnouncementEmailTerminalFailure();
    $this->artisan('onboarding:send-reminders')->assertSuccessful();
    expect($transport->events)->toHaveCount(2);
    $this->travel(59)->minutes();
    $this->artisan('onboarding:send-reminders')->assertSuccessful();
    expect($transport->events)->toHaveCount(2);
    $this->travel(1)->minutes();
    $this->artisan('onboarding:send-reminders')->assertSuccessful();

    expect($transport->events)->toHaveCount(3);
    expect($this->user->fresh()->onboarding_reminder_requested_at?->equalTo($this->requestedAt))->toBeTrue();
    report(new LogicException('Unrelated operation'));
    expect($transport->events[3]->getTags())->toBeEmpty();
});

<?php

use App\Mail\OnboardingReminderEmail;
use App\Models\ReadingLog;
use App\Models\User;
use App\Services\EmailService;
use App\Services\OnboardingReminderProcessor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

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
});

it('scheduled command continues after a failed reminder send', function () {
    Mail::fake();

    $secondUser = User::factory()->create([
        'onboarding_reminder_requested_at' => $this->requestedAt,
    ]);

    $attempts = 0;
    $emailService = $this->mock(EmailService::class);
    $emailService->shouldReceive('sendWithErrorHandling')
        ->twice()
        ->andReturnUsing(function (callable $callback) use (&$attempts) {
            $attempts++;

            if ($attempts === 1) {
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
});

<?php

use App\Jobs\SendOnboardingReminderJob;
use App\Mail\OnboardingReminderEmail;
use App\Models\ReadingLog;
use App\Models\User;
use App\Services\EmailService;
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
    $job = new SendOnboardingReminderJob($this->user->id, $this->requestedAt->toIso8601String());
    $job->handle(app(EmailService::class));

    Mail::assertSent(OnboardingReminderEmail::class, function (OnboardingReminderEmail $mail) {
        return $mail->hasTo($this->user->email);
    });

    expect($this->user->fresh()->onboarding_reminder_requested_at)->toBeNull();
});

it('does not send and clears marker when user has a reading before send time', function () {
    ReadingLog::factory()->for($this->user)->create([
        'date_read' => $this->now->toDateString(),
    ]);

    $job = new SendOnboardingReminderJob($this->user->id, $this->requestedAt->toIso8601String());
    $job->handle(app(EmailService::class));

    Mail::assertNothingSent();
    expect($this->user->fresh()->onboarding_reminder_requested_at)->toBeNull();
});

it('does not send and clears marker when user opted out after scheduling', function () {
    $this->user->update([
        'marketing_emails_opted_out_at' => $this->now->copy()->subHour(),
    ]);

    $job = new SendOnboardingReminderJob($this->user->id, $this->requestedAt->toIso8601String());
    $job->handle(app(EmailService::class));

    Mail::assertNothingSent();
    expect($this->user->fresh()->onboarding_reminder_requested_at)->toBeNull();
});

it('does nothing when job payload marker does not match current marker', function () {
    $staleRequestedAt = $this->requestedAt->copy()->subMinute();

    $job = new SendOnboardingReminderJob($this->user->id, $staleRequestedAt->toIso8601String());
    $job->handle(app(EmailService::class));

    Mail::assertNothingSent();
    expect($this->user->fresh()->onboarding_reminder_requested_at?->equalTo($this->requestedAt))->toBeTrue();
});

it('throws to trigger retry when email sending fails and preserves marker', function () {
    $failingEmailService = new class extends EmailService
    {
        public function sendWithErrorHandling(callable $mailCallback, string $context = 'email'): bool
        {
            return false;
        }
    };

    $job = new SendOnboardingReminderJob($this->user->id, $this->requestedAt->toIso8601String());

    expect(fn() => $job->handle($failingEmailService))
        ->toThrow(\RuntimeException::class);

    expect($this->user->fresh()->onboarding_reminder_requested_at?->equalTo($this->requestedAt))->toBeTrue();
});

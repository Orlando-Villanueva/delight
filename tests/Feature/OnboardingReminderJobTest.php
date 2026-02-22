<?php

use App\Jobs\SendOnboardingReminderJob;
use App\Mail\OnboardingReminderEmail;
use App\Models\ReadingLog;
use App\Models\User;
use App\Services\EmailService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

afterEach(function () {
    Carbon::setTestNow();
});

it('sends once and clears marker when reminder is due and user is eligible', function () {
    Mail::fake();
    $now = Carbon::create(2026, 2, 23, 9, 0, 0);
    Carbon::setTestNow($now);

    $requestedAt = $now->copy()->subDay();
    $user = User::factory()->create([
        'onboarding_reminder_requested_at' => $requestedAt,
    ]);

    $job = new SendOnboardingReminderJob($user->id, $requestedAt->toIso8601String());
    $job->handle(app(EmailService::class));

    Mail::assertSent(OnboardingReminderEmail::class, function (OnboardingReminderEmail $mail) use ($user) {
        return $mail->hasTo($user->email);
    });

    expect($user->fresh()->onboarding_reminder_requested_at)->toBeNull();
});

it('does not send and clears marker when user has a reading before send time', function () {
    Mail::fake();
    $now = Carbon::create(2026, 2, 23, 9, 0, 0);
    Carbon::setTestNow($now);

    $requestedAt = $now->copy()->subDay();
    $user = User::factory()->create([
        'onboarding_reminder_requested_at' => $requestedAt,
    ]);

    ReadingLog::factory()->for($user)->create([
        'date_read' => $now->toDateString(),
    ]);

    $job = new SendOnboardingReminderJob($user->id, $requestedAt->toIso8601String());
    $job->handle(app(EmailService::class));

    Mail::assertNothingSent();
    expect($user->fresh()->onboarding_reminder_requested_at)->toBeNull();
});

it('does not send and clears marker when user opted out after scheduling', function () {
    Mail::fake();
    $now = Carbon::create(2026, 2, 23, 9, 0, 0);
    Carbon::setTestNow($now);

    $requestedAt = $now->copy()->subDay();
    $user = User::factory()->create([
        'onboarding_reminder_requested_at' => $requestedAt,
        'marketing_emails_opted_out_at' => $now->copy()->subHour(),
    ]);

    $job = new SendOnboardingReminderJob($user->id, $requestedAt->toIso8601String());
    $job->handle(app(EmailService::class));

    Mail::assertNothingSent();
    expect($user->fresh()->onboarding_reminder_requested_at)->toBeNull();
});

it('does nothing when job payload marker does not match current marker', function () {
    Mail::fake();
    $now = Carbon::create(2026, 2, 23, 9, 0, 0);
    Carbon::setTestNow($now);

    $requestedAt = $now->copy()->subDay();
    $user = User::factory()->create([
        'onboarding_reminder_requested_at' => $requestedAt,
    ]);

    $staleRequestedAt = $requestedAt->copy()->subMinute();

    $job = new SendOnboardingReminderJob($user->id, $staleRequestedAt->toIso8601String());
    $job->handle(app(EmailService::class));

    Mail::assertNothingSent();
    expect($user->fresh()->onboarding_reminder_requested_at?->equalTo($requestedAt))->toBeTrue();
});

it('throws to trigger retry when email sending fails and preserves marker', function () {
    $now = Carbon::create(2026, 2, 23, 9, 0, 0);
    Carbon::setTestNow($now);

    $requestedAt = $now->copy()->subDay();
    $user = User::factory()->create([
        'onboarding_reminder_requested_at' => $requestedAt,
    ]);

    $failingEmailService = new class extends EmailService
    {
        public function sendWithErrorHandling(callable $mailCallback, string $context = 'email'): bool
        {
            return false;
        }
    };

    $job = new SendOnboardingReminderJob($user->id, $requestedAt->toIso8601String());

    expect(fn () => $job->handle($failingEmailService))
        ->toThrow(\RuntimeException::class);

    expect($user->fresh()->onboarding_reminder_requested_at?->equalTo($requestedAt))->toBeTrue();
});

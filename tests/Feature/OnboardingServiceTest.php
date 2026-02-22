<?php

use App\Jobs\SendOnboardingReminderJob;
use App\Models\ReadingLog;
use App\Models\User;
use App\Services\OnboardingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;

afterEach(function () {
    Carbon::setTestNow();
});

it('dismisses onboarding by setting the timestamp', function () {
    $user = User::factory()->create();
    $service = new OnboardingService;

    expect($user->onboarding_dismissed_at)->toBeNull();

    $service->dismiss($user);

    expect($user->fresh()->onboarding_dismissed_at)->not->toBeNull();
});

it('remind schedules a reminder and dismisses onboarding for eligible users', function () {
    Queue::fake();

    $now = Carbon::create(2026, 2, 22, 9, 30, 0);
    Carbon::setTestNow($now);

    $user = User::factory()->create();
    $service = new OnboardingService;

    expect($user->onboarding_dismissed_at)->toBeNull();
    expect($user->onboarding_reminder_requested_at)->toBeNull();

    $service->remind($user->id);

    $freshUser = $user->fresh();
    expect($freshUser->onboarding_dismissed_at?->equalTo($now))->toBeTrue();
    expect($freshUser->onboarding_reminder_requested_at?->equalTo($now))->toBeTrue();

    Queue::assertPushed(SendOnboardingReminderJob::class, function (SendOnboardingReminderJob $job) use ($user, $now) {
        $delay = $job->delay;

        return $job->userId === $user->id
            && $job->expectedRequestedAtIso === $now->toIso8601String()
            && $delay instanceof DateTimeInterface
            && Carbon::instance($delay)->equalTo($now->copy()->addDay());
    });
});

it('remind does nothing if user already dismissed onboarding', function () {
    Queue::fake();

    $user = User::factory()->create([
        'onboarding_dismissed_at' => now()->subDay(),
    ]);

    $service = new OnboardingService;
    $service->remind($user->id);

    expect($user->fresh()->onboarding_reminder_requested_at)->toBeNull();
    Queue::assertNothingPushed();
});

it('remind does nothing if user already celebrated first reading', function () {
    Queue::fake();

    $user = User::factory()->create([
        'celebrated_first_reading_at' => now()->subDay(),
    ]);

    $service = new OnboardingService;
    $service->remind($user->id);

    expect($user->fresh()->onboarding_reminder_requested_at)->toBeNull();
    Queue::assertNothingPushed();
});

it('remind does nothing if user already has reading logs', function () {
    Queue::fake();

    $user = User::factory()->create();
    ReadingLog::factory()->for($user)->create();

    $service = new OnboardingService;
    $service->remind($user->id);

    expect($user->fresh()->onboarding_reminder_requested_at)->toBeNull();
    Queue::assertNothingPushed();
});

it('remind dismisses but does not schedule if user opted out of marketing', function () {
    Queue::fake();

    $user = User::factory()->create([
        'marketing_emails_opted_out_at' => now()->subDay(),
    ]);

    $service = new OnboardingService;
    $service->remind($user->id);

    $freshUser = $user->fresh();
    expect($freshUser->onboarding_dismissed_at)->not->toBeNull();
    expect($freshUser->onboarding_reminder_requested_at)->toBeNull();
    Queue::assertNothingPushed();
});

it('remind does not schedule multiple reminders if requested again', function () {
    Queue::fake();

    $now = Carbon::create(2026, 2, 22, 9, 30, 0);
    Carbon::setTestNow($now);

    $user = User::factory()->create();
    $service = new OnboardingService;

    // First request
    $service->remind($user->id);

    $freshUser = $user->fresh();
    expect($freshUser->onboarding_reminder_requested_at?->equalTo($now))->toBeTrue();

    // Reset onboarding_dismissed_at to simulate forcing another eligibility check
    // even though the UI normally wouldn't show it. We want to test the atomic block.
    $freshUser->update(['onboarding_dismissed_at' => null]);

    // Second request slightly later
    Carbon::setTestNow($now->copy()->addMinutes(10));
    $service->remind($user->id);

    $evenFresherUser = $user->fresh();
    // It should have preserved the ORIGINAL requested_at timestamp and not scheduled another job
    expect($evenFresherUser->onboarding_reminder_requested_at?->equalTo($now))->toBeTrue();

    // Queue should still only have 1 job pushed from the very first one
    Queue::assertPushed(SendOnboardingReminderJob::class, 1);
});

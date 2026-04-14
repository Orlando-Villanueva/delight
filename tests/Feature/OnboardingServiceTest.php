<?php

use App\Models\ReadingLog;
use App\Models\User;
use App\Services\OnboardingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

afterEach(function () {
    Carbon::setTestNow();
});

it('dismisses onboarding by setting the timestamp', function () {
    $user = User::factory()->create();
    $service = new OnboardingService;

    expect($user->onboarding_dismissed_at)->toBeNull();

    $service->dismiss($user);

    expect($user->fresh()->onboarding_dismissed_at)->not->toBeNull();
    $this->assertDatabaseHas('onboarding_step_events', [
        'user_id' => $user->id,
        'step' => 'dismissed',
    ]);
});

it('remind stores a reminder request and dismisses onboarding for eligible users', function () {
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
    $this->assertDatabaseHas('onboarding_step_events', [
        'user_id' => $user->id,
        'step' => 'dismissed',
    ]);
    $this->assertDatabaseHas('onboarding_step_events', [
        'user_id' => $user->id,
        'step' => 'reminder_requested',
    ]);
});

it('remind does nothing if user already dismissed onboarding', function () {
    $user = User::factory()->create([
        'onboarding_dismissed_at' => now()->subDay(),
    ]);

    $service = new OnboardingService;
    $service->remind($user->id);

    expect($user->fresh()->onboarding_reminder_requested_at)->toBeNull();
});

it('remind does nothing if user already celebrated first reading', function () {
    $user = User::factory()->create([
        'celebrated_first_reading_at' => now()->subDay(),
    ]);

    $service = new OnboardingService;
    $service->remind($user->id);

    expect($user->fresh()->onboarding_reminder_requested_at)->toBeNull();
});

it('remind does nothing if user already has reading logs', function () {
    $user = User::factory()->create();
    ReadingLog::factory()->for($user)->create();

    $service = new OnboardingService;
    $service->remind($user->id);

    expect($user->fresh()->onboarding_reminder_requested_at)->toBeNull();
});

it('remind dismisses but does not store a reminder if user opted out of marketing', function () {
    $user = User::factory()->create([
        'marketing_emails_opted_out_at' => now()->subDay(),
    ]);

    $service = new OnboardingService;
    $service->remind($user->id);

    $freshUser = $user->fresh();
    expect($freshUser->onboarding_dismissed_at)->not->toBeNull();
    expect($freshUser->onboarding_reminder_requested_at)->toBeNull();
    $this->assertDatabaseHas('onboarding_step_events', [
        'user_id' => $user->id,
        'step' => 'dismissed',
    ]);
    $this->assertDatabaseMissing('onboarding_step_events', [
        'user_id' => $user->id,
        'step' => 'reminder_requested',
    ]);
});

it('remind does not store multiple reminders if requested again', function () {
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
    expect(DB::table('onboarding_step_events')
        ->where('user_id', $user->id)
        ->where('step', 'dismissed')
        ->count())->toBe(1);
    expect(DB::table('onboarding_step_events')
        ->where('user_id', $user->id)
        ->where('step', 'reminder_requested')
        ->count())->toBe(1);
});

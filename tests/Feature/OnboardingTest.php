<?php

use App\Jobs\SendOnboardingReminderJob;
use App\Models\ReadingLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;

afterEach(function () {
    Carbon::setTestNow();
});

it('shows onboarding modal for new users on dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertSee('onboarding-modal')
        ->assertSee('Welcome to Delight!');
});

it('does not show onboarding for users with readings', function () {
    $user = User::factory()->create();
    ReadingLog::factory()->for($user)->create([
        'passage_text' => 'John 1',
        'date_read' => now(),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertDontSee('onboarding-modal');
});

it('dismisses onboarding and sets timestamp', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/onboarding/dismiss')
        ->assertNoContent();

    expect($user->fresh()->onboarding_dismissed_at)->not->toBeNull();
});

it('schedules onboarding reminder for eligible users', function () {
    Queue::fake();
    $now = Carbon::create(2026, 2, 22, 9, 30, 0);
    Carbon::setTestNow($now);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.remind'))
        ->assertNoContent();

    $freshUser = $user->fresh();

    expect($freshUser->onboarding_dismissed_at)->not->toBeNull();
    expect($freshUser->onboarding_reminder_requested_at)->not->toBeNull();

    Queue::assertPushed(SendOnboardingReminderJob::class);
});

it('does not dispatch duplicate reminders on repeated clicks', function () {
    Queue::fake();
    $firstClick = Carbon::create(2026, 2, 22, 11, 0, 0);
    Carbon::setTestNow($firstClick);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.remind'))
        ->assertNoContent();

    Carbon::setTestNow($firstClick->copy()->addMinutes(10));

    $this->actingAs($user)
        ->post(route('onboarding.remind'))
        ->assertNoContent();

    expect($user->fresh()->onboarding_reminder_requested_at?->equalTo($firstClick))->toBeTrue();

    Queue::assertPushed(SendOnboardingReminderJob::class, 1);
});

it('does nothing when reminder requested for dismissed users', function () {
    Queue::fake();

    $user = User::factory()->create([
        'onboarding_dismissed_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->post(route('onboarding.remind'))
        ->assertNoContent();

    expect($user->fresh()->onboarding_reminder_requested_at)->toBeNull();
    Queue::assertNothingPushed();
});

it('does nothing when reminder requested for users with readings', function () {
    Queue::fake();

    $user = User::factory()->create();
    ReadingLog::factory()->for($user)->create([
        'passage_text' => 'John 1',
        'date_read' => now()->toDateString(),
    ]);

    $this->actingAs($user)
        ->post(route('onboarding.remind'))
        ->assertNoContent();

    expect($user->fresh()->onboarding_reminder_requested_at)->toBeNull();
    Queue::assertNothingPushed();
});

it('does nothing when reminder requested for already celebrated users', function () {
    Queue::fake();

    $user = User::factory()->create([
        'celebrated_first_reading_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->post(route('onboarding.remind'))
        ->assertNoContent();

    expect($user->fresh()->onboarding_reminder_requested_at)->toBeNull();
    Queue::assertNothingPushed();
});

it('dismisses onboarding without scheduling reminder for opted-out users', function () {
    Queue::fake();

    $user = User::factory()->create([
        'marketing_emails_opted_out_at' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->post(route('onboarding.remind'))
        ->assertNoContent();

    $freshUser = $user->fresh();

    expect($freshUser->onboarding_dismissed_at)->not->toBeNull();
    expect($freshUser->onboarding_reminder_requested_at)->toBeNull();
    Queue::assertNothingPushed();
});

it('shows reminder CTA for opted-in users and hides it for opted-out users', function () {
    $optedInUser = User::factory()->create();
    $optedOutUser = User::factory()->create([
        'marketing_emails_opted_out_at' => now()->subDay(),
    ]);

    $this->actingAs($optedInUser)
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertSee('Remind me tomorrow');

    $this->actingAs($optedOutUser)
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertDontSee('Remind me tomorrow');
});

it('onboarding modal cannot be dismissed without action', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/dashboard')
        ->assertStatus(200);

    // Assert no X button (data-modal-hide attribute)
    $response->assertDontSee('data-modal-hide="onboarding-modal"');

    // Assert no ESC key handler (no onboarding.dismiss route in modal script)
    $response->assertDontSee('onboarding.dismiss');

    // Assert modal is configured as not closable
    $response->assertSee('closable: false');
});

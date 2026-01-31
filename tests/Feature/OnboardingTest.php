<?php

use App\Models\ReadingLog;
use App\Models\User;

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

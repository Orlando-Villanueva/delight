<?php

use App\Models\User;
use App\Models\ReadingLog;
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

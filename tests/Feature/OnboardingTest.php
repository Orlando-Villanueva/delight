<?php

use App\Models\User;
use App\Models\ReadingLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('shows onboarding modal for new users on dashboard', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user)
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertSee('onboarding-modal')
        ->assertSee('Welcome to Delight!');
});

test('does not show onboarding for users with readings', function () {
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

test('dismisses onboarding and sets timestamp', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user)
        ->post('/onboarding/dismiss')
        ->assertNoContent();
    
    expect($user->fresh()->onboarding_dismissed_at)->not->toBeNull();
});
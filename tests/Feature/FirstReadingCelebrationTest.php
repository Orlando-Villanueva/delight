<?php

use App\Models\ReadingLog;
use App\Models\ReadingPlan;
use App\Models\ReadingPlanSubscription;
use App\Models\User;

/**
 * Create a test reading plan.
 */
function createTestCelebrationPlan(): ReadingPlan
{
    return ReadingPlan::create([
        'slug' => 'celebration-plan',
        'name' => 'Celebration Plan',
        'description' => 'A test plan for celebration',
        'days' => [
            [
                'day' => 1,
                'label' => 'Genesis 1',
                'chapters' => [
                    ['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 1],
                ],
            ],
            [
                'day' => 2,
                'label' => 'Genesis 2-3',
                'chapters' => [
                    ['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 2],
                    ['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 3],
                ],
            ],
        ],
        'is_active' => true,
    ]);
}

it('shows celebration for first reading', function () {
    $user = User::factory()->create();

    $readingData = [
        'book_id' => 43, // John
        'start_chapter' => 1,
        'date_read' => today()->toDateString(),
    ];

    $response = $this->actingAs($user)
        ->withHeaders(['HX-Request' => 'true'])
        ->post('/logs', $readingData);

    $response->assertStatus(200)
        ->assertSee('data-is-first-reading')
        ->assertSee('1 down, 365 to go');
});

it('does not show celebration for subsequent readings', function () {
    $user = User::factory()->create();
    $readingLog = ReadingLog::factory()->for($user)->create([
        'book_id' => 1, // Genesis
        'chapter' => 1,
        'date_read' => now(),
    ]);

    $readingData = [
        'book_id' => 43, // John
        'start_chapter' => 1,
        'date_read' => today()->toDateString(),
    ];

    $response = $this->actingAs($user)
        ->withHeaders(['HX-Request' => 'true'])
        ->post('/logs', $readingData);

    $response->assertStatus(200)
        ->assertDontSee("You've started! 1 down, 365 to go");
});

it('does not re-celebrate if user deletes and logs again', function () {
    $user = User::factory()->create([
        'celebrated_first_reading_at' => now(),
    ]);

    $readingData = [
        'book_id' => 43, // John
        'start_chapter' => 1,
        'date_read' => today()->toDateString(),
    ];

    $response = $this->actingAs($user)
        ->withHeaders(['HX-Request' => 'true'])
        ->post('/logs', $readingData);

    $response->assertStatus(200)
        ->assertDontSee("You've started! 1 down, 365 to go");
});

it('shows celebration when logging via reading plan', function () {
    $user = User::factory()->create();
    $plan = createTestCelebrationPlan();

    // Subscribe user
    ReadingPlanSubscription::create([
        'user_id' => $user->id,
        'reading_plan_id' => $plan->id,
        'started_at' => now(),
        'is_active' => true,
    ]);

    // Log first chapter via plan
    $response = $this->actingAs($user)
        ->post(route('plans.logChapter', $plan), [
            'book_id' => 1,
            'chapter' => 1,
            'day' => 1,
        ]);

    $response->assertOk()
        ->assertSee('data-is-first-reading')
        ->assertSee('1 down, 365 to go');

    // Verify user was celebrated
    expect($user->fresh()->hasEverCelebratedFirstReading())->toBeTrue();
});

it('shows celebration when logging all chapters via reading plan', function () {
    $user = User::factory()->create();
    $plan = createTestCelebrationPlan();

    // Subscribe user
    ReadingPlanSubscription::create([
        'user_id' => $user->id,
        'reading_plan_id' => $plan->id,
        'started_at' => now(),
        'is_active' => true,
    ]);

    // Log all chapters for day 2 (Genesis 2-3)
    $response = $this->actingAs($user)
        ->post(route('plans.logAll', $plan), [
            'day' => 2,
        ]);

    $response->assertOk()
        ->assertSee('data-is-first-reading')
        ->assertSee('1 down, 365 to go');

    // Verify user was celebrated
    expect($user->fresh()->hasEverCelebratedFirstReading())->toBeTrue();

    // Verify it was marked at roughly now
    expect($user->fresh()->celebrated_first_reading_at->isToday())->toBeTrue();
});

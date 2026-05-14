<?php

use App\Models\ReadingLog;
use App\Models\ReadingPlan;
use App\Models\ReadingPlanSubscription;
use App\Models\User;
use App\Services\AchievementService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Carbon::setTestNow('2026-05-10 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

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

function createCelebrationReading(User $user, string $date, int $chapter): void
{
    ReadingLog::factory()->for($user)->create([
        'book_id' => 1,
        'chapter' => $chapter,
        'passage_text' => "Genesis {$chapter}",
        'date_read' => $date,
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
        ->assertSee('achievement-celebration-modal')
        ->assertSee('Achievement unlocked')
        ->assertSee('First reading')
        ->assertSee('images/achievements/badge-first-reading.png')
        ->assertSee('images/achievements/badge-calendar.png')
        ->assertSee('Next up');
});

it('clears onboarding reminder marker when first reading is celebrated', function () {
    $user = User::factory()->create([
        'onboarding_reminder_requested_at' => now()->subHours(4),
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['HX-Request' => 'true'])
        ->post('/logs', [
            'book_id' => 43,
            'start_chapter' => 1,
            'date_read' => today()->toDateString(),
        ]);

    $response->assertStatus(200);

    $freshUser = $user->fresh();
    expect($freshUser->celebrated_first_reading_at)->not->toBeNull();
    expect($freshUser->onboarding_reminder_requested_at)->toBeNull();
    $this->assertDatabaseHas('onboarding_step_events', [
        'user_id' => $user->id,
        'step' => 'first_reading_completed',
    ]);
});

it('does not show celebration for subsequent readings', function () {
    $user = User::factory()->create();
    $readingLog = ReadingLog::factory()->for($user)->create([
        'book_id' => 1, // Genesis
        'chapter' => 1,
        'date_read' => now(),
    ]);
    app(AchievementService::class)->evaluateAndAward($user);

    $readingData = [
        'book_id' => 43, // John
        'start_chapter' => 1,
        'date_read' => today()->toDateString(),
    ];

    $response = $this->actingAs($user)
        ->withHeaders(['HX-Request' => 'true'])
        ->post('/logs', $readingData);

    $response->assertStatus(200)
        ->assertDontSee('achievement-celebration-modal')
        ->assertDontSee('Achievement unlocked');
});

it('shows a personal best modal without trophy shelf copy for a record-only moment', function () {
    $user = User::factory()->create();

    foreach (['2026-04-01', '2026-04-02', '2026-05-08', '2026-05-09'] as $index => $date) {
        createCelebrationReading($user, $date, $index + 1);
    }

    app(AchievementService::class)->evaluateAndAward($user);

    $response = $this->actingAs($user)
        ->withHeaders(['HX-Request' => 'true'])
        ->post('/logs', [
            'book_id' => 43,
            'start_chapter' => 1,
            'date_read' => today()->toDateString(),
        ]);

    $response->assertOk()
        ->assertSee('achievement-celebration-modal')
        ->assertSee('Personal best')
        ->assertSee('Longest streak: 3 days')
        ->assertSee('You beat your previous best of 2 days.')
        ->assertDontSee('Achievement unlocked')
        ->assertDontSee('View trophy shelf');

    expect($user->achievements()->where('achievement_key', 'personal_best_streak')->exists())->toBeFalse();
});

it('does not repeat a personal best modal for another same-day reading after the record is broken', function () {
    $user = User::factory()->create();

    foreach (['2026-04-01', '2026-04-02', '2026-05-08', '2026-05-09'] as $index => $date) {
        createCelebrationReading($user, $date, $index + 1);
    }

    app(AchievementService::class)->evaluateAndAward($user);

    $firstResponse = $this->actingAs($user)
        ->withHeaders(['HX-Request' => 'true'])
        ->post('/logs', [
            'book_id' => 43,
            'start_chapter' => 1,
            'date_read' => today()->toDateString(),
        ]);

    $secondResponse = $this->actingAs($user)
        ->withHeaders(['HX-Request' => 'true'])
        ->post('/logs', [
            'book_id' => 43,
            'start_chapter' => 2,
            'date_read' => today()->toDateString(),
        ]);

    $firstResponse->assertOk()
        ->assertSee('achievement-celebration-modal')
        ->assertSee('Longest streak: 3 days');

    $secondResponse->assertOk()
        ->assertDontSee('achievement-celebration-modal')
        ->assertDontSee('Longest streak: 3 days');
});

it('keeps a fixed streak achievement primary when it also breaks a personal best', function () {
    $user = User::factory()->create();

    foreach (range(0, 5) as $offset) {
        createCelebrationReading($user, Carbon::parse('2026-04-01')->addDays($offset)->toDateString(), $offset + 1);
    }

    foreach (range(0, 5) as $offset) {
        createCelebrationReading($user, Carbon::parse('2026-05-04')->addDays($offset)->toDateString(), $offset + 10);
    }

    app(AchievementService::class)->evaluateAndAward($user);

    $response = $this->actingAs($user)
        ->withHeaders(['HX-Request' => 'true'])
        ->post('/logs', [
            'book_id' => 43,
            'start_chapter' => 1,
            'date_read' => today()->toDateString(),
        ]);

    $response->assertOk()
        ->assertSee('Achievement unlocked')
        ->assertSee('7-day reading streak')
        ->assertSee('Personal best')
        ->assertSee('Longest streak: 7 days')
        ->assertSee('View trophy shelf');

    expect($user->achievements()->where('achievement_key', 'reading_streak_7')->exists())->toBeTrue()
        ->and($user->achievements()->where('achievement_key', 'personal_best_streak')->exists())->toBeFalse();
});

it('does not re-celebrate if user deletes and logs again', function () {
    $user = User::factory()->create([
        'celebrated_first_reading_at' => now(),
    ]);
    ReadingLog::factory()->for($user)->create([
        'book_id' => 1,
        'chapter' => 1,
        'date_read' => now()->subDays(10),
    ]);
    app(AchievementService::class)->evaluateAndAward($user);

    $readingData = [
        'book_id' => 43, // John
        'start_chapter' => 1,
        'date_read' => today()->toDateString(),
    ];

    $response = $this->actingAs($user)
        ->withHeaders(['HX-Request' => 'true'])
        ->post('/logs', $readingData);

    $response->assertStatus(200)
        ->assertDontSee('achievement-celebration-modal')
        ->assertDontSee('Achievement unlocked');
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
        ->assertSee('achievement-celebration-modal')
        ->assertSee('Achievement unlocked')
        ->assertSee('First reading');

    // Verify user was celebrated
    expect($user->fresh()->hasEverCelebratedFirstReading())->toBeTrue();
    $this->assertDatabaseHas('onboarding_step_events', [
        'user_id' => $user->id,
        'step' => 'first_reading_completed',
    ]);
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
        ->assertSee('achievement-celebration-modal')
        ->assertSee('Achievement unlocked')
        ->assertSee('First reading');

    // Verify user was celebrated
    expect($user->fresh()->hasEverCelebratedFirstReading())->toBeTrue();

    // Verify it was marked at roughly now
    expect($user->fresh()->celebrated_first_reading_at->isToday())->toBeTrue();
    $this->assertDatabaseHas('onboarding_step_events', [
        'user_id' => $user->id,
        'step' => 'first_reading_completed',
    ]);
});

it('records first reading completion once even when a prior log-flow step exists', function () {
    $user = User::factory()->create();

    DB::table('onboarding_step_events')->insert([
        'user_id' => $user->id,
        'step' => 'log_flow_reached',
        'occurred_at' => now()->subMinutes(5),
        'metadata' => null,
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['HX-Request' => 'true'])
        ->post('/logs', [
            'book_id' => 43,
            'start_chapter' => 1,
            'date_read' => today()->toDateString(),
        ]);

    $response->assertOk();

    expect(DB::table('onboarding_step_events')
        ->where('user_id', $user->id)
        ->where('step', 'first_reading_completed')
        ->count())->toBe(1);
});

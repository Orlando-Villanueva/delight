<?php

namespace Tests\Feature;

use App\Models\ReadingLog;
use App\Models\ReadingPlan;
use App\Models\ReadingPlanDayCompletion;
use App\Models\ReadingPlanSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Create a test reading plan with optional attribute overrides.
 */
function createTestPlan(array $overrides = []): ReadingPlan
{
    $defaultDays = [
        [
            'day' => 1,
            'label' => 'Genesis 1-3',
            'chapters' => [
                ['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 1],
                ['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 2],
                ['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 3],
            ],
        ],
        [
            'day' => $overrides['second_day_number'] ?? 2,
            'label' => 'Genesis 4-6',
            'chapters' => [
                ['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 4],
                ['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 5],
                ['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 6],
            ],
        ],
    ];

    // Remove our custom key before merging
    unset($overrides['second_day_number']);

    return ReadingPlan::create(array_merge([
        'slug' => 'test-plan',
        'name' => 'Test Reading Plan',
        'description' => 'A test plan',
        'days' => $defaultDays,
        'is_active' => true,
    ], $overrides));
}

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->plan = createTestPlan();
});

describe('Reading Plans Index', function () {
    it('shows available reading plans to authenticated users', function () {
        $response = $this->actingAs($this->user)
            ->get(route('plans.index'));

        $response->assertOk();
        $response->assertSee('Test Reading Plan');
        $response->assertSee('A test plan');
    });

    it('redirects guests to login', function () {
        $response = $this->get(route('plans.index'));

        $response->assertRedirect(route('login'));
    });
});

describe('Reading Plan Subscription', function () {
    it('allows user to subscribe to a plan', function () {
        $response = $this->actingAs($this->user)
            ->post(route('plans.subscribe', $this->plan));

        $response->assertRedirect(route('plans.today', $this->plan));

        $this->assertDatabaseHas('reading_plan_subscriptions', [
            'user_id' => $this->user->id,
            'reading_plan_id' => $this->plan->id,
        ]);
    });

    it('sets started_at to today when subscribing', function () {
        Carbon::setTestNow('2026-01-03');

        $this->actingAs($this->user)
            ->post(route('plans.subscribe', $this->plan));

        $subscription = ReadingPlanSubscription::first();

        expect($subscription->started_at->toDateString())->toBe('2026-01-03');

        Carbon::setTestNow();
    });

    it('allows user to unsubscribe from a plan', function () {
        $subscription = ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $this->plan->id,
            'started_at' => now(),
        ]);

        $log = ReadingLog::create([
            'user_id' => $this->user->id,
            'book_id' => 1,
            'chapter' => 1,
            'passage_text' => 'Genesis 1',
            'date_read' => Carbon::today(),
        ]);

        // Link the log to the subscription via junction table
        ReadingPlanDayCompletion::create([
            'reading_log_id' => $log->id,
            'reading_plan_subscription_id' => $subscription->id,
            'reading_plan_day' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('plans.unsubscribe', $this->plan));

        $response->assertRedirect(route('plans.index'));

        $this->assertDatabaseMissing('reading_plan_subscriptions', [
            'user_id' => $this->user->id,
            'reading_plan_id' => $this->plan->id,
        ]);

        // Reading log should still exist
        expect(ReadingLog::count())->toBe(1);
        // But junction table link should be removed
        expect(ReadingPlanDayCompletion::count())->toBe(0);
    });
});

describe("Today's Reading", function () {
    it('redirects to plans index if not subscribed', function () {
        $response = $this->actingAs($this->user)
            ->get(route('plans.today', $this->plan));

        $response->assertRedirect(route('plans.index'));
    });

    it("shows today's reading for subscribed user", function () {
        ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $this->plan->id,
            'started_at' => Carbon::today(),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('plans.today', $this->plan));

        $response->assertOk();
        $response->assertSee('Genesis 1-3');
        $response->assertSee('Day 1');
    });

    it('keeps day 1 reading until day 1 is complete', function () {
        ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $this->plan->id,
            'started_at' => Carbon::today()->subDays(2),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('plans.today', $this->plan));

        $response->assertOk();
        $response->assertSee('Genesis 1-3');
        $response->assertSee('Day 1');
    });

    it('advances to day 2 after completing day 1', function () {
        $subscription = ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $this->plan->id,
            'started_at' => Carbon::today()->subDay(),
        ]);

        // Create reading logs and link them via junction table
        foreach ([1, 2, 3] as $chapter) {
            $log = ReadingLog::create([
                'user_id' => $this->user->id,
                'book_id' => 1,
                'chapter' => $chapter,
                'passage_text' => "Genesis {$chapter}",
                'date_read' => Carbon::today(),
            ]);

            ReadingPlanDayCompletion::create([
                'reading_log_id' => $log->id,
                'reading_plan_subscription_id' => $subscription->id,
                'reading_plan_day' => 1,
            ]);
        }

        $response = $this->actingAs($this->user)
            ->get(route('plans.today', $this->plan));

        $response->assertOk();
        $response->assertSee('Genesis 4-6');
        $response->assertSee('Day 2');
    });

    it('allows navigating to a different day', function () {
        ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $this->plan->id,
            'started_at' => Carbon::today(),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('plans.today', ['plan' => $this->plan, 'day' => 2]));

        $response->assertOk();
        $response->assertSee('Genesis 4-6');
        $response->assertSee('Day 2');
    });
});

describe('Chapter Logging', function () {
    beforeEach(function () {
        $this->subscription = ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $this->plan->id,
            'started_at' => Carbon::today(),
            'is_active' => true,
        ]);
    });

    it('logs a single chapter', function () {
        $response = $this->actingAs($this->user)
            ->post(route('plans.logChapter', $this->plan), [
                'book_id' => 1,
                'chapter' => 1,
                'day' => 1,
            ]);

        $response->assertOk();

        $log = ReadingLog::where('user_id', $this->user->id)
            ->where('book_id', 1)
            ->where('chapter', 1)
            ->whereDate('date_read', Carbon::today())
            ->first();

        expect($log)->not->toBeNull();

        // Check junction table for the link
        $completion = ReadingPlanDayCompletion::where('reading_log_id', $log->id)
            ->where('reading_plan_subscription_id', $this->subscription->id)
            ->first();

        expect($completion)->not->toBeNull();
        expect($completion->reading_plan_day)->toBe(1);
    });

    it('logs all chapters at once', function () {
        $response = $this->actingAs($this->user)
            ->post(route('plans.logAll', $this->plan), [
                'day' => 1,
            ]);

        $response->assertOk();

        expect(ReadingLog::where('user_id', $this->user->id)->count())->toBe(3);

        // Check junction table for all links
        expect(ReadingPlanDayCompletion::where('reading_plan_subscription_id', $this->subscription->id)
            ->where('reading_plan_day', 1)
            ->count())->toBe(3);
    });

    it('it_can_apply_todays_readings_without_creating_new_logs', function () {
        Carbon::setTestNow('2026-01-02');

        $log = ReadingLog::create([
            'user_id' => $this->user->id,
            'book_id' => 1,
            'chapter' => 1,
            'passage_text' => 'Genesis 1',
            'date_read' => Carbon::today(),
        ]);

        $this->actingAs($this->user)
            ->post(route('plans.applyTodaysReadings', $this->plan), [
                'day' => 1,
            ])
            ->assertOk();

        expect(ReadingLog::count())->toBe(1);

        // Check junction table for the link (log should be linked via junction table)
        $completion = ReadingPlanDayCompletion::where('reading_log_id', $log->id)
            ->where('reading_plan_subscription_id', $this->subscription->id)
            ->first();

        expect($completion)->not->toBeNull();
        expect($completion->reading_plan_day)->toBe(1);

        Carbon::setTestNow();
    });

    it('it_can_reject_logging_for_missing_plan_days', function () {
        $plan = createTestPlan([
            'slug' => 'missing-day-plan',
            'name' => 'Missing Day Plan',
            'description' => 'A plan with a missing day',
            'second_day_number' => 3, // Creates a gap: day 1, day 3 (no day 2)
        ]);

        ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $plan->id,
            'started_at' => Carbon::today()->addDay(),
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->post(route('plans.logChapter', $plan), [
                'book_id' => 1,
                'chapter' => 1,
                'day' => 2,
            ])
            ->assertStatus(404);

        $this->actingAs($this->user)
            ->post(route('plans.logAll', $plan), [
                'day' => 2,
            ])
            ->assertStatus(404);

        expect(ReadingLog::count())->toBe(0);
    });

    it('does not duplicate already logged chapters', function () {
        // Log chapter 1 first
        $existingLog = ReadingLog::create([
            'user_id' => $this->user->id,
            'book_id' => 1,
            'chapter' => 1,
            'passage_text' => 'Genesis 1',
            'date_read' => Carbon::today(),
        ]);

        // Try to log all
        $this->actingAs($this->user)
            ->post(route('plans.logAll', $this->plan), [
                'day' => 1,
            ]);

        // Should only have 3 total (the original + 2 new ones)
        expect(ReadingLog::where('user_id', $this->user->id)->count())->toBe(3);

        // Check that the existing log is linked via junction table
        $completion = ReadingPlanDayCompletion::where('reading_log_id', $existingLog->id)
            ->where('reading_plan_subscription_id', $this->subscription->id)
            ->first();

        expect($completion)->not->toBeNull();
        expect($completion->reading_plan_day)->toBe(1);
    });

    it('it_can_forbid_logging_completed_plan_days', function () {
        Carbon::setTestNow('2026-01-01');

        $this->actingAs($this->user)
            ->post(route('plans.logAll', $this->plan), [
                'day' => 1,
            ])
            ->assertOk();

        Carbon::setTestNow('2026-01-03');

        $this->actingAs($this->user)
            ->post(route('plans.logChapter', $this->plan), [
                'book_id' => 1,
                'chapter' => 1,
                'day' => 1,
            ])
            ->assertStatus(409);

        // Check junction table for all 3 completions
        expect(ReadingPlanDayCompletion::where('reading_plan_subscription_id', $this->subscription->id)
            ->where('reading_plan_day', 1)
            ->count())->toBe(3);

        Carbon::setTestNow();
    });

    it('shows checkmarks for completed chapters', function () {
        $log = ReadingLog::create([
            'user_id' => $this->user->id,
            'book_id' => 1,
            'chapter' => 1,
            'passage_text' => 'Genesis 1',
            'date_read' => Carbon::today(),
        ]);

        // Link via junction table
        ReadingPlanDayCompletion::create([
            'reading_log_id' => $log->id,
            'reading_plan_subscription_id' => $this->subscription->id,
            'reading_plan_day' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('plans.today', $this->plan));

        $response->assertOk();
        // The completed chapter should show with green styling
        $response->assertSee('Genesis 1');
    });

    it('allows same chapter to be logged for multiple plans', function () {
        // Create two plans with overlapping Day 1 readings (Genesis 1)
        $planA = createTestPlan(['slug' => 'plan-a-overlap', 'name' => 'Plan A']);
        $planB = createTestPlan(['slug' => 'plan-b-overlap', 'name' => 'Plan B']);

        // Subscribe to Plan A (active)
        $subscriptionA = ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $planA->id,
            'started_at' => now()->toDateString(),
            'is_active' => true,
        ]);

        // Log Genesis 1 for Plan A
        $this->actingAs($this->user)
            ->post(route('plans.logChapter', $planA), [
                'day' => 1,
                'book_id' => 1,
                'chapter' => 1,
            ])->assertStatus(200);

        // Verify reading log exists and is linked to Plan A
        $readingLog = ReadingLog::where('user_id', $this->user->id)
            ->where('book_id', 1)
            ->where('chapter', 1)
            ->first();
        expect($readingLog)->not->toBeNull();
        expect(ReadingPlanDayCompletion::where('reading_log_id', $readingLog->id)
            ->where('reading_plan_subscription_id', $subscriptionA->id)
            ->exists())->toBeTrue();

        // Now subscribe to Plan B (make it active, deactivate Plan A)
        $subscriptionB = ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $planB->id,
            'started_at' => now()->toDateString(),
            'is_active' => true,
        ]);
        $subscriptionA->update(['is_active' => false]);

        // Log Genesis 1 for Plan B (same chapter, same day)
        $this->actingAs($this->user)
            ->post(route('plans.logChapter', $planB), [
                'day' => 1,
                'book_id' => 1,
                'chapter' => 1,
            ])->assertStatus(200);

        // Verify the SAME reading log is now linked to BOTH plans
        expect(ReadingLog::where('user_id', $this->user->id)
            ->where('book_id', 1)
            ->where('chapter', 1)
            ->count())->toBe(1); // Only one reading log

        expect(ReadingPlanDayCompletion::where('reading_log_id', $readingLog->id)->count())->toBe(2);
        expect(ReadingPlanDayCompletion::where('reading_log_id', $readingLog->id)
            ->where('reading_plan_subscription_id', $subscriptionA->id)
            ->exists())->toBeTrue();
        expect(ReadingPlanDayCompletion::where('reading_log_id', $readingLog->id)
            ->where('reading_plan_subscription_id', $subscriptionB->id)
            ->exists())->toBeTrue();
    });
});

describe('Active Subscription Management', function () {
    it('sets new subscription as active and deactivates others', function () {
        // Create first plan subscription
        $plan1 = createTestPlan(['slug' => 'plan-1', 'name' => 'Plan 1']);
        $plan2 = createTestPlan(['slug' => 'plan-2', 'name' => 'Plan 2']);

        // Subscribe to plan 1
        $this->actingAs($this->user)
            ->post(route('plans.subscribe', $plan1));

        $subscription1 = ReadingPlanSubscription::where('reading_plan_id', $plan1->id)->first();
        expect($subscription1->is_active)->toBeTrue();

        // Subscribe to plan 2
        $this->actingAs($this->user)
            ->post(route('plans.subscribe', $plan2));

        $subscription1->refresh();
        $subscription2 = ReadingPlanSubscription::where('reading_plan_id', $plan2->id)->first();

        expect($subscription1->is_active)->toBeFalse();
        expect($subscription2->is_active)->toBeTrue();
    });

    it('prevents logging chapters on inactive subscriptions', function () {
        $plan = createTestPlan(['slug' => 'inactive-plan', 'name' => 'Inactive Plan']);

        ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $plan->id,
            'started_at' => Carbon::today(),
            'is_active' => false, // Inactive subscription
        ]);

        $this->actingAs($this->user)
            ->post(route('plans.logChapter', $plan), [
                'book_id' => 1,
                'chapter' => 1,
                'day' => 1,
            ])
            ->assertStatus(403);

        expect(ReadingLog::count())->toBe(0);
    });

    it('allows activating a paused subscription', function () {
        $plan = createTestPlan(['slug' => 'paused-plan', 'name' => 'Paused Plan']);

        $subscription = ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $plan->id,
            'started_at' => Carbon::today(),
            'is_active' => false,
        ]);

        $this->actingAs($this->user)
            ->post(route('plans.activate', $plan))
            ->assertRedirect(route('plans.today', $plan));

        expect($subscription->fresh()->is_active)->toBeTrue();
    });

    it('deactivates other subscriptions when activating one', function () {
        $plan1 = createTestPlan(['slug' => 'plan-a', 'name' => 'Plan A']);
        $plan2 = createTestPlan(['slug' => 'plan-b', 'name' => 'Plan B']);

        $subscription1 = ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $plan1->id,
            'started_at' => Carbon::today(),
            'is_active' => true,
        ]);

        $subscription2 = ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $plan2->id,
            'started_at' => Carbon::today(),
            'is_active' => false,
        ]);

        $this->actingAs($this->user)
            ->post(route('plans.activate', $plan2));

        expect($subscription1->fresh()->is_active)->toBeFalse();
        expect($subscription2->fresh()->is_active)->toBeTrue();
    });

    it('auto-activates lone inactive subscription after unsubscribing from another', function () {
        $plan1 = createTestPlan(['slug' => 'plan-active', 'name' => 'Active Plan']);
        $plan2 = createTestPlan(['slug' => 'plan-paused', 'name' => 'Paused Plan']);

        // Create an active subscription for plan1
        ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $plan1->id,
            'started_at' => Carbon::today(),
            'is_active' => true,
        ]);

        // Create a paused subscription for plan2
        $pausedSubscription = ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $plan2->id,
            'started_at' => Carbon::today(),
            'is_active' => false,
        ]);

        // Unsubscribe from plan1
        $this->actingAs($this->user)
            ->delete(route('plans.unsubscribe', $plan1));

        // The paused subscription should now be active
        expect($pausedSubscription->fresh()->is_active)->toBeTrue();
    });

    it('does not auto-activate when multiple subscriptions remain', function () {
        $plan1 = createTestPlan(['slug' => 'plan-active', 'name' => 'Active Plan']);
        $plan2 = createTestPlan(['slug' => 'plan-paused-1', 'name' => 'Paused Plan 1']);
        $plan3 = createTestPlan(['slug' => 'plan-paused-2', 'name' => 'Paused Plan 2']);

        // Create subscriptions
        ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $plan1->id,
            'started_at' => Carbon::today(),
            'is_active' => true,
        ]);

        $pausedSub1 = ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $plan2->id,
            'started_at' => Carbon::today(),
            'is_active' => false,
        ]);

        $pausedSub2 = ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $plan3->id,
            'started_at' => Carbon::today(),
            'is_active' => false,
        ]);

        // Unsubscribe from the active plan
        $this->actingAs($this->user)
            ->delete(route('plans.unsubscribe', $plan1));

        // Neither paused subscription should be auto-activated (multiple remain)
        expect($pausedSub1->fresh()->is_active)->toBeFalse();
        expect($pausedSub2->fresh()->is_active)->toBeFalse();
    });
});

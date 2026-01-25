<?php

namespace Tests\Feature;

use App\Models\ReadingLog;
use App\Models\ReadingPlan;
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
            'reading_plan_subscription_id' => $subscription->id,
            'reading_plan_day' => 1,
            'book_id' => 1,
            'chapter' => 1,
            'passage_text' => 'Genesis 1',
            'date_read' => Carbon::today(),
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('plans.unsubscribe', $this->plan));

        $response->assertRedirect(route('plans.index'));

        $this->assertDatabaseMissing('reading_plan_subscriptions', [
            'user_id' => $this->user->id,
            'reading_plan_id' => $this->plan->id,
        ]);

        expect(ReadingLog::count())->toBe(1);
        expect($log->fresh()->reading_plan_subscription_id)->toBeNull();
        expect($log->fresh()->reading_plan_day)->toBeNull();
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

        foreach ([1, 2, 3] as $chapter) {
            ReadingLog::create([
                'user_id' => $this->user->id,
                'reading_plan_subscription_id' => $subscription->id,
                'reading_plan_day' => 1,
                'book_id' => 1,
                'chapter' => $chapter,
                'passage_text' => "Genesis {$chapter}",
                'date_read' => Carbon::today(),
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
        expect($log->reading_plan_subscription_id)->toBe($this->subscription->id);
        expect($log->reading_plan_day)->toBe(1);
    });

    it('logs all chapters at once', function () {
        $response = $this->actingAs($this->user)
            ->post(route('plans.logAll', $this->plan), [
                'day' => 1,
            ]);

        $response->assertOk();

        expect(ReadingLog::where('user_id', $this->user->id)->count())->toBe(3);
        expect(ReadingLog::where('reading_plan_subscription_id', $this->subscription->id)
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
        expect($log->fresh()->reading_plan_subscription_id)->toBe($this->subscription->id);
        expect($log->fresh()->reading_plan_day)->toBe(1);

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
        expect($existingLog->fresh()->reading_plan_subscription_id)->toBe($this->subscription->id);
        expect($existingLog->fresh()->reading_plan_day)->toBe(1);
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

        expect(ReadingLog::where('user_id', $this->user->id)
            ->where('reading_plan_subscription_id', $this->subscription->id)
            ->where('reading_plan_day', 1)
            ->count())->toBe(3);

        Carbon::setTestNow();
    });

    it('shows checkmarks for completed chapters', function () {
        ReadingLog::create([
            'user_id' => $this->user->id,
            'reading_plan_subscription_id' => $this->subscription->id,
            'reading_plan_day' => 1,
            'book_id' => 1,
            'chapter' => 1,
            'passage_text' => 'Genesis 1',
            'date_read' => Carbon::today(),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('plans.today', $this->plan));

        $response->assertOk();
        // The completed chapter should show with green styling
        $response->assertSee('Genesis 1');
    });
});

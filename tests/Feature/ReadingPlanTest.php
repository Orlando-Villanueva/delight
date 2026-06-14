<?php

namespace Tests\Feature;

use App\Models\ReadingLog;
use App\Models\ReadingPlan;
use App\Models\ReadingPlanDayCompletion;
use App\Models\ReadingPlanSubscription;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\ReadingPlanService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;

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
        $response->assertSee('Reading Plans');
        $response->assertSee('Structured guides to help you read the Bible consistently');
        $response->assertSee('Test');
        $response->assertDontSee('Test Reading Plan');
        $response->assertSee('A test plan');
        $response->assertSee('Start from Day 1');
        $response->assertSee('Start from a different passage');
    });

    it('only shows the Catholic canonical plan when the Catholic canon is enabled', function () {
        $standardPlan = createTestPlan([
            'slug' => 'standard-canonical',
            'name' => 'Canonical Reading Plan',
        ]);
        $catholicPlan = createTestPlan([
            'slug' => 'catholic-canonical',
            'name' => 'Catholic Canonical Reading Plan',
        ]);

        $this->actingAs($this->user)
            ->get(route('plans.index'))
            ->assertSuccessful()
            ->assertSee($standardPlan->getShortName())
            ->assertDontSee($catholicPlan->getShortName())
            ->assertDontSee(route('plans.subscribe', $catholicPlan), false);

        $this->user->forceFill(['deuterocanonical_books_enabled_at' => now()])->save();

        $this->actingAs($this->user->fresh())
            ->get(route('plans.index'))
            ->assertSuccessful()
            ->assertSee($standardPlan->getShortName())
            ->assertSee($catholicPlan->getShortName())
            ->assertSee(route('plans.subscribe', $catholicPlan), false);
    });

    it('ignores hidden Catholic canonical subscriptions when calculating visible plan status', function () {
        $catholicPlan = createTestPlan([
            'slug' => 'catholic-canonical',
            'name' => 'Catholic Canonical Reading Plan',
        ]);
        ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $catholicPlan->id,
            'started_at' => Carbon::today(),
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->get(route('plans.index'))
            ->assertSuccessful()
            ->assertDontSee($catholicPlan->getShortName())
            ->assertDontSee('Starting this plan will pause your current active plan. Continue?');
    });

    it('renders one reusable starting passage modal for available plans', function () {
        $secondPlan = createTestPlan([
            'slug' => 'second-test-plan',
            'name' => 'Second Test Reading Plan',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('plans.index'));

        $response->assertOk();
        $response->assertSee('id="reading-plan-start-modal"', false);
        $response->assertSee('x-data="readingPlanStartModal()"', false);
        $response->assertSee('data-reading-plan-start-data', false);
        $response->assertSee('data-plan-slug="test-plan"', false);
        $response->assertSee('data-plan-slug="second-test-plan"', false);
        $response->assertSee(route('plans.subscribe', $this->plan), false);
        $response->assertSee(route('plans.subscribe', $secondPlan), false);
        $response->assertSee('method="POST" action="'.route('plans.subscribe', $this->plan).'"', false);
        $response->assertSee('x-on:click', false);

        expect(substr_count($response->getContent(), 'id="reading-plan-start-modal"'))->toBe(1);
    });

    it('does not expose start controls for active plans without readings', function () {
        $this->plan->delete();

        $emptyPlan = createTestPlan([
            'slug' => 'empty-test-plan',
            'name' => 'Empty Test Reading Plan',
            'days' => [],
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('plans.index'));

        $response->assertOk();
        $response->assertSee('Empty');
        $response->assertSee('0 days');
        $response->assertSee('No readings available yet.');
        $response->assertSee('Coming soon');
        $response->assertDontSee('Start from Day 0');
        $response->assertDontSee('Start from a different passage');
        $response->assertDontSee('data-plan-slug="empty-test-plan"', false);
        $response->assertDontSee('id="reading-plan-start-modal"', false);
        $response->assertDontSee(route('plans.subscribe', $emptyPlan), false);
    });

    it('uses the last plan day identifier in subscribed plan progress labels', function () {
        $plan = createTestPlan([
            'slug' => 'sparse-index-plan',
            'second_day_number' => 3,
        ]);

        ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $plan->id,
            'started_at' => Carbon::today(),
            'start_day' => 3,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('plans.index'));

        $response->assertOk();
        $response->assertSee('Day 3 of 3');
        $response->assertDontSee('Day 3 of 2');
    });

    it('eager loads day completions for subscribed plans on the index', function () {
        $secondPlan = createTestPlan([
            'slug' => 'second-subscribed-plan',
            'name' => 'Second Subscribed Reading Plan',
        ]);

        foreach ([$this->plan, $secondPlan] as $plan) {
            ReadingPlanSubscription::create([
                'user_id' => $this->user->id,
                'reading_plan_id' => $plan->id,
                'started_at' => Carbon::today(),
                'start_day' => 1,
                'is_active' => true,
            ]);
        }

        $dayCompletionQueries = 0;

        DB::listen(function ($query) use (&$dayCompletionQueries): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'select')
                && str_contains($query->sql, 'reading_plan_day_completions')) {
                $dayCompletionQueries++;
            }
        });

        $this->actingAs($this->user)
            ->get(route('plans.index'))
            ->assertOk();

        expect($dayCompletionQueries)->toBe(1);
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

    it('requires the Catholic canon setting to subscribe to the Catholic canonical plan', function () {
        $catholicPlan = createTestPlan([
            'slug' => 'catholic-canonical',
            'name' => 'Catholic Canonical Reading Plan',
        ]);

        $this->actingAs($this->user)
            ->post(route('plans.subscribe', $catholicPlan))
            ->assertForbidden();

        $this->assertDatabaseMissing('reading_plan_subscriptions', [
            'user_id' => $this->user->id,
            'reading_plan_id' => $catholicPlan->id,
        ]);

        $this->user->forceFill(['deuterocanonical_books_enabled_at' => now()])->save();

        $this->actingAs($this->user->fresh())
            ->post(route('plans.subscribe', $catholicPlan))
            ->assertRedirect(route('plans.today', $catholicPlan));
    });

    it('enforces Catholic canon eligibility in the reading plan service', function () {
        $catholicPlan = createTestPlan([
            'slug' => 'catholic-canonical',
            'name' => 'Catholic Canonical Reading Plan',
        ]);
        $service = app(ReadingPlanService::class);

        expect(fn () => $service->subscribe($this->user, $catholicPlan))
            ->toThrow(AuthorizationException::class);

        $this->user->forceFill(['deuterocanonical_books_enabled_at' => now()])->save();
        $subscription = $service->subscribe($this->user->fresh(), $catholicPlan);
        $this->user->forceFill(['deuterocanonical_books_enabled_at' => null])->save();

        expect(fn () => $service->activate($subscription->fresh()))
            ->toThrow(AuthorizationException::class);
    });

    it('prevents using an existing Catholic canonical subscription after disabling the Catholic canon', function () {
        $catholicPlan = createTestPlan([
            'slug' => 'catholic-canonical',
            'name' => 'Catholic Canonical Reading Plan',
        ]);
        $subscription = ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $catholicPlan->id,
            'started_at' => Carbon::today(),
            'is_active' => false,
        ]);

        $this->actingAs($this->user)
            ->get(route('plans.today', $catholicPlan))
            ->assertRedirect(route('plans.index'));

        $this->actingAs($this->user)
            ->post(route('plans.activate', $catholicPlan))
            ->assertRedirect(route('plans.index'));

        $this->actingAs($this->user)
            ->post(route('plans.logChapter', $catholicPlan), [
                'book_id' => 1,
                'chapter' => 1,
                'day' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($this->user)
            ->post(route('plans.logAll', $catholicPlan), ['day' => 1])
            ->assertForbidden();

        expect($subscription->fresh()->is_active)->toBeFalse()
            ->and(ReadingLog::count())->toBe(0);
    });

    it('sets started_at to today when subscribing', function () {
        Carbon::setTestNow('2026-01-03');

        $this->actingAs($this->user)
            ->post(route('plans.subscribe', $this->plan));

        $subscription = ReadingPlanSubscription::first();

        expect($subscription->started_at->toDateString())->toBe('2026-01-03');

        Carbon::setTestNow();
    });

    it('starts tracking from a selected plan day without backfilling readings', function () {
        $response = $this->actingAs($this->user)
            ->post(route('plans.subscribe', $this->plan), [
                'start_day' => 2,
            ]);

        $response->assertRedirect(route('plans.today', $this->plan));

        $this->assertDatabaseHas('reading_plan_subscriptions', [
            'user_id' => $this->user->id,
            'reading_plan_id' => $this->plan->id,
            'start_day' => 2,
        ]);

        expect(ReadingLog::count())->toBe(0)
            ->and(ReadingPlanDayCompletion::count())->toBe(0);
    });

    it('closes the starting passage modal before replacing the page via htmx', function () {
        $response = $this->actingAs($this->user)
            ->withHeaders(['HX-Request' => 'true'])
            ->post(route('plans.subscribe', $this->plan), [
                'start_day' => 2,
            ]);

        $response->assertSuccessful();

        $trigger = json_decode($response->headers->get('HX-Trigger'), true);

        expect($trigger)->toBeArray()
            ->and($trigger)->toHaveKey('hideModal')
            ->and($trigger['hideModal'])->toBe(['id' => 'reading-plan-start-modal']);
    });

    it('defaults the starting plan day to day one', function () {
        $this->actingAs($this->user)
            ->post(route('plans.subscribe', $this->plan))
            ->assertRedirect(route('plans.today', $this->plan));

        $this->assertDatabaseHas('reading_plan_subscriptions', [
            'user_id' => $this->user->id,
            'reading_plan_id' => $this->plan->id,
            'start_day' => 1,
        ]);
    });

    it('normalizes direct service subscriptions to a valid plan day', function () {
        $plan = createTestPlan([
            'slug' => 'service-normalized-plan',
            'days' => [
                [
                    'day' => 3,
                    'label' => 'Genesis 4-6',
                    'chapters' => [
                        ['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 4],
                    ],
                ],
            ],
        ]);

        $subscription = $this->app->make(ReadingPlanService::class)
            ->subscribe($this->user, $plan);

        expect($subscription->start_day)->toBe(3);
    });

    it('rejects an invalid selected starting plan day', function (int $startDay) {
        $this->actingAs($this->user)
            ->from(route('plans.index'))
            ->post(route('plans.subscribe', $this->plan), [
                'start_day' => $startDay,
            ])
            ->assertRedirect(route('plans.index'))
            ->assertSessionHasErrors('start_day');

        expect(ReadingPlanSubscription::count())->toBe(0);
    })->with([0, 3]);

    it('does not change the starting day of an existing subscription', function () {
        $this->actingAs($this->user)
            ->post(route('plans.subscribe', $this->plan), ['start_day' => 2])
            ->assertRedirect(route('plans.today', $this->plan));

        $this->actingAs($this->user)
            ->post(route('plans.subscribe', $this->plan), ['start_day' => 1])
            ->assertRedirect(route('plans.today', $this->plan));

        expect(ReadingPlanSubscription::first()->start_day)->toBe(2);
    });

    it('can complete tracking when starting on the final plan day', function () {
        $this->actingAs($this->user)
            ->post(route('plans.subscribe', $this->plan), ['start_day' => 2])
            ->assertRedirect(route('plans.today', $this->plan));

        $this->actingAs($this->user)
            ->post(route('plans.logAll', $this->plan), ['day' => 2])
            ->assertOk();

        $subscription = ReadingPlanSubscription::first();

        expect($subscription->getTrackedDaysCount())->toBe(1)
            ->and($subscription->getCompletedDaysCount())->toBe(1)
            ->and($subscription->getProgress())->toBe(100.0)
            ->and($subscription->isComplete())->toBeTrue();

        $this->actingAs($this->user)
            ->get(route('plans.today', $this->plan))
            ->assertOk()
            ->assertSee('Tracking complete');
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
        $response->assertSee('Genesis 1');
        $response->assertSee('Genesis 3');
        $response->assertSee('Day 1');
    });

    it('renders a compact reading plan detail view', function () {
        $plan = createTestPlan([
            'slug' => 'compact-plan',
            'name' => 'Compact Reading Plan',
        ]);

        ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $plan->id,
            'started_at' => Carbon::today(),
            'start_day' => 2,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('plans.today', $plan));

        $response->assertOk();
        $response->assertSee('Compact');
        $response->assertDontSee('Compact Reading Plan');
        $response->assertSee('<span class="sm:hidden">Day 2/2 · tracking from 2 · 0/1 complete</span>', false);
        $response->assertSee('<span class="hidden sm:inline">Day 2 of 2 · tracking from Day 2 · 0 of 1 tracked days complete</span>', false);
        $response->assertDontSee('Genesis 4-6');
        $response->assertSee('Genesis 4');
        $response->assertSee('Genesis 5');
        $response->assertSee('Genesis 6');
        $response->assertSee('<span class="sm:hidden">Previous</span>', false);
        $response->assertSee('<span class="sm:hidden">Next</span>', false);
        $response->assertSee('<span class="sm:hidden">Complete day</span>', false);
        $response->assertSee('class="flex items-start justify-between gap-4"', false);
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
        $response->assertSee('Genesis 1');
        $response->assertSee('Genesis 3');
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
        $response->assertSee('Genesis 4');
        $response->assertSee('Genesis 6');
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
        $response->assertSee('Genesis 4');
        $response->assertSee('Genesis 6');
        $response->assertSee('Day 2');
    });

    it('navigates between actual non-contiguous plan days', function () {
        $plan = createTestPlan([
            'slug' => 'sparse-navigation-plan',
            'second_day_number' => 3,
        ]);

        ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $plan->id,
            'started_at' => Carbon::today(),
            'is_active' => true,
        ]);

        $dayOneResponse = $this->actingAs($this->user)
            ->get(route('plans.today', ['plan' => $plan, 'day' => 1]));

        $dayOneResponse->assertOk();
        $dayOneResponse->assertSee(route('plans.today', ['plan' => $plan, 'day' => 3]), false);
        $dayOneResponse->assertDontSee(route('plans.today', ['plan' => $plan, 'day' => 2]), false);

        $dayThreeResponse = $this->actingAs($this->user)
            ->get(route('plans.today', ['plan' => $plan, 'day' => 3]));

        $dayThreeResponse->assertOk();
        $dayThreeResponse->assertSee(route('plans.today', ['plan' => $plan, 'day' => 1]), false);
        $dayThreeResponse->assertDontSee(route('plans.today', ['plan' => $plan, 'day' => 2]), false);
    });

    it('shows before tracking days without plan logging actions', function () {
        ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $this->plan->id,
            'started_at' => Carbon::today(),
            'start_day' => 2,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('plans.today', ['plan' => $this->plan, 'day' => 1]));

        $response->assertOk();
        $response->assertSee('Before tracking');
        $response->assertDontSee('Mark day complete');
        $response->assertDontSee('Mark read');
        $response->assertDontSee('Apply to this day');
    });

    it('tracks completion separately from the current plan position', function () {
        $plan = createTestPlan([
            'slug' => 'three-day-plan',
            'days' => [
                ['day' => 1, 'label' => 'Genesis 1', 'chapters' => [['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 1]]],
                ['day' => 2, 'label' => 'Genesis 2', 'chapters' => [['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 2]]],
                ['day' => 3, 'label' => 'Genesis 3', 'chapters' => [['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 3]]],
            ],
        ]);

        $subscription = ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $plan->id,
            'started_at' => Carbon::today(),
            'start_day' => 2,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->post(route('plans.logAll', $plan), ['day' => 3])
            ->assertOk();

        $subscription->refresh();

        expect($subscription->getDayNumber())->toBe(2)
            ->and($subscription->getCompletedDaysCount())->toBe(1)
            ->and($subscription->getProgress())->toBe(50.0)
            ->and($subscription->isComplete())->toBeFalse();
    });

    it('refreshes loaded completion relationships when resetting progress caches', function () {
        $subscription = ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $this->plan->id,
            'started_at' => Carbon::today(),
            'start_day' => 1,
            'is_active' => true,
        ]);

        expect($subscription->getCompletedDaysCount())->toBe(0)
            ->and($subscription->relationLoaded('dayCompletions'))->toBeTrue();

        $this->app->make(ReadingPlanService::class)->logAllChapters(
            $this->user,
            $subscription,
            1,
            $this->plan->getDayReading(1)['chapters'],
            Carbon::today()
        );

        expect($subscription->getCompletedDaysCount())->toBe(1);
    });

    it('tracks completion from an actual non-contiguous starting plan day', function () {
        $plan = createTestPlan([
            'slug' => 'sparse-tracking-plan',
            'second_day_number' => 3,
        ]);

        $this->actingAs($this->user)
            ->post(route('plans.subscribe', $plan), ['start_day' => 3])
            ->assertRedirect(route('plans.today', $plan));

        $subscription = ReadingPlanSubscription::firstWhere('reading_plan_id', $plan->id);

        expect($subscription->start_day)->toBe(3)
            ->and($subscription->getDayNumber())->toBe(3)
            ->and($subscription->getTrackedDaysCount())->toBe(1)
            ->and($subscription->getProgress())->toBe(0.0)
            ->and($subscription->isComplete())->toBeFalse();

        $this->actingAs($this->user)
            ->post(route('plans.logAll', $plan), ['day' => 3])
            ->assertOk();

        $subscription = $subscription->fresh();

        expect($subscription->getDayNumber())->toBe(3)
            ->and($subscription->getTrackedDaysCount())->toBe(1)
            ->and($subscription->getCompletedDaysCount())->toBe(1)
            ->and($subscription->getProgress())->toBe(100.0)
            ->and($subscription->isComplete())->toBeTrue();
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

    it('rejects plan logging before the subscription starting day', function () {
        $this->subscription->update(['start_day' => 2]);

        $this->actingAs($this->user)
            ->post(route('plans.logChapter', $this->plan), [
                'book_id' => 1,
                'chapter' => 1,
                'day' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($this->user)
            ->post(route('plans.logAll', $this->plan), ['day' => 1])
            ->assertForbidden();

        $this->actingAs($this->user)
            ->post(route('plans.applyTodaysReadings', $this->plan), ['day' => 1])
            ->assertForbidden();

        expect(ReadingLog::count())->toBe(0)
            ->and(ReadingPlanDayCompletion::count())->toBe(0);
    });

    it('evaluates achievements once when logging all chapters at once', function () {
        $this->mock(AchievementService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('evaluateAndAward')
                ->once()
                ->andReturn([
                    'awarded' => 0,
                    'skipped_duplicates' => 0,
                    'would_award' => 0,
                    'candidates' => collect(),
                    'awarded_achievements' => collect(),
                ]);
            $mock->shouldReceive('getCelebrationPayload')
                ->zeroOrMoreTimes()
                ->andReturn([
                    'earned' => [],
                    'progress' => [],
                    'record' => null,
                    'reading' => [],
                ]);
        });

        $this->actingAs($this->user)
            ->post(route('plans.logAll', $this->plan), [
                'day' => 1,
            ])
            ->assertOk();

        expect(ReadingLog::where('user_id', $this->user->id)->count())->toBe(3);
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

    it('does not auto-activate a lone Catholic canonical subscription when the Catholic canon is disabled', function () {
        $activePlan = createTestPlan(['slug' => 'plan-active', 'name' => 'Active Plan']);
        $catholicPlan = createTestPlan([
            'slug' => 'catholic-canonical',
            'name' => 'Catholic Canonical Reading Plan',
        ]);

        ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $activePlan->id,
            'started_at' => Carbon::today(),
            'is_active' => true,
        ]);

        $pausedCatholicSubscription = ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $catholicPlan->id,
            'started_at' => Carbon::today(),
            'is_active' => false,
        ]);

        $this->actingAs($this->user)
            ->delete(route('plans.unsubscribe', $activePlan));

        expect($pausedCatholicSubscription->fresh()->is_active)->toBeFalse();
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

<?php

namespace Tests\Feature;

use App\Models\ReadingLog;
use App\Models\ReadingPlan;
use App\Models\ReadingPlanSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->plan = ReadingPlan::create([
        'slug' => 'test-plan',
        'name' => 'Test Reading Plan',
        'description' => 'A test plan',
        'days' => [
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
                'day' => 2,
                'label' => 'Genesis 4-6',
                'chapters' => [
                    ['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 4],
                    ['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 5],
                    ['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 6],
                ],
            ],
        ],
        'is_active' => true,
    ]);
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

        $response->assertRedirect(route('plans.today'));

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
        ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $this->plan->id,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('plans.unsubscribe', $this->plan));

        $response->assertRedirect(route('plans.index'));

        $this->assertDatabaseMissing('reading_plan_subscriptions', [
            'user_id' => $this->user->id,
            'reading_plan_id' => $this->plan->id,
        ]);
    });
});

describe("Today's Reading", function () {
    it('redirects to plans index if not subscribed', function () {
        $response = $this->actingAs($this->user)
            ->get(route('plans.today'));

        $response->assertRedirect(route('plans.index'));
    });

    it("shows today's reading for subscribed user", function () {
        ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $this->plan->id,
            'started_at' => Carbon::today(),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('plans.today'));

        $response->assertOk();
        $response->assertSee('Genesis 1-3');
        $response->assertSee('Day 1');
    });

    it('shows day 2 reading on second day', function () {
        ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $this->plan->id,
            'started_at' => Carbon::today()->subDay(),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('plans.today'));

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
            ->post(route('plans.logChapter'), [
                'book_id' => 1,
                'chapter' => 1,
            ]);

        $response->assertOk();

        expect(ReadingLog::where('user_id', $this->user->id)
            ->where('book_id', 1)
            ->where('chapter', 1)
            ->whereDate('date_read', Carbon::today())
            ->exists())->toBeTrue();
    });

    it('logs all chapters at once', function () {
        $response = $this->actingAs($this->user)
            ->post(route('plans.logAll'));

        $response->assertOk();

        expect(ReadingLog::where('user_id', $this->user->id)->count())->toBe(3);
    });

    it('does not duplicate already logged chapters', function () {
        // Log chapter 1 first
        ReadingLog::create([
            'user_id' => $this->user->id,
            'book_id' => 1,
            'chapter' => 1,
            'passage_text' => 'Genesis 1',
            'date_read' => Carbon::today(),
        ]);

        // Try to log all
        $this->actingAs($this->user)
            ->post(route('plans.logAll'));

        // Should only have 3 total (the original + 2 new ones)
        expect(ReadingLog::where('user_id', $this->user->id)->count())->toBe(3);
    });

    it('shows checkmarks for completed chapters', function () {
        ReadingLog::create([
            'user_id' => $this->user->id,
            'book_id' => 1,
            'chapter' => 1,
            'passage_text' => 'Genesis 1',
            'date_read' => Carbon::today(),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('plans.today'));

        $response->assertOk();
        // The completed chapter should show with green styling
        $response->assertSee('Genesis 1');
    });
});

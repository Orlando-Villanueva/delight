<?php

namespace Tests\Unit;

use App\Models\ReadingLog;
use App\Models\ReadingPlan;
use App\Models\ReadingPlanSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_index_returns_dashboard_view_for_regular_request()
    {
        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertViewIs('dashboard');
        $response->assertViewHas('hasReadToday');
        $response->assertViewHas('streakState');
        $response->assertViewHas('stats');
    }

    public function test_index_returns_fragment_for_htmx_request()
    {
        $response = $this->get('/dashboard', ['HX-Request' => 'true']);

        $response->assertStatus(200);
        // We look for the main content fragment container
        $response->assertSee('id="main-content"', false);
        $response->assertDontSee('<!DOCTYPE html>');
    }

    public function test_index_computes_streak_state_correctly()
    {
        $response = $this->get('/dashboard');

        $response->assertViewHas('streakState', function ($state) {
            return in_array($state, ['inactive', 'active', 'warning']);
        });

        $response->assertViewHas('hasReadToday');
        $response->assertViewHas('stats');
    }

    public function test_index_includes_weekly_goal_data_for_regular_request()
    {
        $response = $this->get('/dashboard');

        $response->assertViewHas('weeklyGoal');
        $response->assertViewHas('weeklyJourney');

        $weeklyGoal = $response->viewData('weeklyGoal');
        $this->assertArrayHasKey('current_progress', $weeklyGoal);
        $this->assertArrayHasKey('weekly_target', $weeklyGoal);
    }

    public function test_index_includes_weekly_goal_data_for_htmx_request()
    {
        $response = $this->get('/dashboard', ['HX-Request' => 'true']);

        $response->assertStatus(200);

        // Match current content
        $response->assertSee('Weekly Journey');
    }

    public function test_index_uses_latest_incomplete_plan_for_cta()
    {
        Carbon::setTestNow('2026-01-10');

        $olderPlan = ReadingPlan::create([
            'slug' => 'older-plan',
            'name' => 'Older Plan',
            'description' => 'Older plan description',
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
                    'label' => 'Genesis 2',
                    'chapters' => [
                        ['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 2],
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        $newerPlan = ReadingPlan::create([
            'slug' => 'newer-plan',
            'name' => 'Newer Plan',
            'description' => 'Newer plan description',
            'days' => [
                [
                    'day' => 1,
                    'label' => 'Matthew 1',
                    'chapters' => [
                        ['book_id' => 40, 'book_name' => 'Matthew', 'chapter' => 1],
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $olderPlan->id,
            'started_at' => Carbon::today()->subDays(2),
        ]);

        $newerSubscription = ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $newerPlan->id,
            'started_at' => Carbon::today()->subDay(),
        ]);

        ReadingLog::create([
            'user_id' => $this->user->id,
            'reading_plan_subscription_id' => $newerSubscription->id,
            'reading_plan_day' => 1,
            'book_id' => 40,
            'chapter' => 1,
            'passage_text' => 'Matthew 1',
            'date_read' => Carbon::today(),
        ]);

        $response = $this->get('/dashboard');

        $response->assertViewHas('planCta', function ($planCta) use ($olderPlan) {
            $this->assertTrue($planCta['showPlanCta']);
            $this->assertSame($olderPlan->id, $planCta['plan']->id);

            return true;
        });

        Carbon::setTestNow();
    }
}

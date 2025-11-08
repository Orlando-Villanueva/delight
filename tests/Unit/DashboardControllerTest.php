<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardController;
use App\Models\User;
use App\Services\ReadingFormService;
use App\Services\StreakStateService;
use App\Services\UserStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private DashboardController $controller;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->controller = new DashboardController(
            app(ReadingFormService::class),
            app(UserStatisticsService::class),
            app(StreakStateService::class)
        );
    }

    public function test_index_returns_dashboard_view_for_regular_request()
    {
        $request = Request::create('/dashboard', 'GET');

        $response = $this->controller->index($request);

        $this->assertEquals('dashboard', $response->getName());
        $this->assertArrayHasKey('hasReadToday', $response->getData());
        $this->assertArrayHasKey('streakState', $response->getData());
        $this->assertArrayHasKey('stats', $response->getData());
    }

    public function test_index_returns_partial_view_for_htmx_request()
    {
        $request = Request::create('/dashboard', 'GET');
        $request->headers->set('HX-Request', 'true');

        $response = $this->controller->index($request);

        $this->assertEquals('partials.dashboard-page', $response->getName());
        $this->assertArrayHasKey('hasReadToday', $response->getData());
        $this->assertArrayHasKey('streakState', $response->getData());
        $this->assertArrayHasKey('stats', $response->getData());
    }

    public function test_index_computes_streak_state_correctly()
    {
        $request = Request::create('/dashboard', 'GET');

        $response = $this->controller->index($request);
        $data = $response->getData();

        // Verify streak state is one of the valid states
        $this->assertContains($data['streakState'], ['inactive', 'active', 'warning']);

        // Verify hasReadToday is boolean
        $this->assertIsBool($data['hasReadToday']);

        // Verify stats is an array with expected structure
        $this->assertIsArray($data['stats']);
        $this->assertArrayHasKey('streaks', $data['stats']);
        $this->assertArrayHasKey('current_streak', $data['stats']['streaks']);
    }

    public function test_index_includes_weekly_goal_data_for_regular_request()
    {
        $request = Request::create('/dashboard', 'GET');

        $response = $this->controller->index($request);
        $data = $response->getData();

        // Verify weeklyGoal is available as a separate variable
        $this->assertArrayHasKey('weeklyGoal', $data);
        $this->assertIsArray($data['weeklyGoal']);
        $this->assertArrayHasKey('weeklyJourney', $data);
        $this->assertIsArray($data['weeklyJourney']);

        // Verify weekly goal data structure
        $weeklyGoal = $data['weeklyGoal'];
        $this->assertArrayHasKey('current_progress', $weeklyGoal);
        $this->assertArrayHasKey('weekly_target', $weeklyGoal);
        $this->assertArrayHasKey('week_start', $weeklyGoal);
        $this->assertArrayHasKey('week_end', $weeklyGoal);
        $this->assertArrayHasKey('is_goal_achieved', $weeklyGoal);
        $this->assertArrayHasKey('progress_percentage', $weeklyGoal);
        $this->assertArrayHasKey('message', $weeklyGoal);
        $this->assertArrayHasKey('journey', $weeklyGoal);

        // Verify weekly goal is also available in stats
        $this->assertArrayHasKey('weekly_goal', $data['stats']);
        $this->assertArrayHasKey('weekly_journey', $data['stats']);
        $this->assertEquals($data['stats']['weekly_goal'], $data['weeklyGoal']);
        $this->assertEquals($data['stats']['weekly_journey'], $data['weeklyJourney']);
    }

    public function test_index_includes_weekly_goal_data_for_htmx_request()
    {
        $request = Request::create('/dashboard', 'GET');
        $request->headers->set('HX-Request', 'true');

        $response = $this->controller->index($request);
        $data = $response->getData();

        // Verify weeklyGoal is available as a separate variable for HTMX requests too
        $this->assertArrayHasKey('weeklyGoal', $data);
        $this->assertIsArray($data['weeklyGoal']);
        $this->assertArrayHasKey('weeklyJourney', $data);
        $this->assertIsArray($data['weeklyJourney']);

        // Verify weekly goal data structure
        $weeklyGoal = $data['weeklyGoal'];
        $this->assertArrayHasKey('current_progress', $weeklyGoal);
        $this->assertArrayHasKey('weekly_target', $weeklyGoal);
        $this->assertIsInt($weeklyGoal['current_progress']);
        $this->assertIsInt($weeklyGoal['weekly_target']);
        $this->assertIsBool($weeklyGoal['is_goal_achieved']);
    }
}

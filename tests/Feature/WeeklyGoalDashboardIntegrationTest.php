<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyGoalDashboardIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_includes_weekly_goal_data_in_response()
    {
        // Create and authenticate a user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Make a request to the dashboard
        $response = $this->get('/dashboard');

        // Assert the response is successful
        $response->assertStatus(200);

        // Assert the view has the weekly goal and journey data
        $response->assertViewHas('weeklyGoal');
        $response->assertViewHas('weeklyJourney');

        // Get the view data
        $viewData = $response->viewData('weeklyGoal');
        $journeyData = $response->viewData('weeklyJourney');

        // Assert the weekly goal data structure
        $this->assertIsArray($viewData);
        $this->assertArrayHasKey('current_progress', $viewData);
        $this->assertArrayHasKey('weekly_target', $viewData);
        $this->assertArrayHasKey('week_start', $viewData);
        $this->assertArrayHasKey('week_end', $viewData);
        $this->assertArrayHasKey('is_goal_achieved', $viewData);
        $this->assertArrayHasKey('progress_percentage', $viewData);
        $this->assertArrayHasKey('message', $viewData);

        // Assert data types
        $this->assertIsInt($viewData['current_progress']);
        $this->assertIsInt($viewData['weekly_target']);
        $this->assertIsString($viewData['week_start']);
        $this->assertIsString($viewData['week_end']);
        $this->assertIsBool($viewData['is_goal_achieved']);
        $this->assertIsNumeric($viewData['progress_percentage']);
        $this->assertIsString($viewData['message']);
        $this->assertArrayHasKey('journey', $viewData);
        $this->assertIsArray($viewData['journey']);

        // Assert default values
        $this->assertEquals(4, $viewData['weekly_target']);
        $this->assertGreaterThanOrEqual(0, $viewData['current_progress']);
        $this->assertLessThanOrEqual(100, $viewData['progress_percentage']);

        // Assert journey data mirrors the dedicated payload
        $this->assertEquals($journeyData, $viewData['journey']);
        $this->assertArrayHasKey('currentProgress', $journeyData);
        $this->assertArrayHasKey('days', $journeyData);
        $this->assertArrayHasKey('weekRangeText', $journeyData);
        $this->assertArrayHasKey('weeklyTarget', $journeyData);
        $this->assertArrayHasKey('ctaEnabled', $journeyData);
        $this->assertCount(7, $journeyData['days']);
    }

    public function test_htmx_dashboard_request_includes_weekly_goal_data()
    {
        // Create and authenticate a user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Make an HTMX request to the dashboard
        $response = $this->get('/dashboard', [
            'HX-Request' => 'true',
        ]);

        // Assert the response is successful
        $response->assertStatus(200);

        // Assert the view has the weekly goal data
        $response->assertViewHas('weeklyGoal');
        $response->assertViewHas('weeklyJourney');

        // Get the view data
        $viewData = $response->viewData('weeklyGoal');
        $journeyData = $response->viewData('weeklyJourney');

        // Assert the weekly goal data structure is present
        $this->assertIsArray($viewData);
        $this->assertArrayHasKey('current_progress', $viewData);
        $this->assertArrayHasKey('weekly_target', $viewData);
        $this->assertEquals(4, $viewData['weekly_target']);
        $this->assertEquals($viewData['journey'], $journeyData);
    }

    public function test_weekly_goal_data_matches_stats_weekly_goal()
    {
        // Create and authenticate a user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Make a request to the dashboard
        $response = $this->get('/dashboard');

        // Assert the response is successful
        $response->assertStatus(200);

        // Get both weekly goal and stats data
        $weeklyGoal = $response->viewData('weeklyGoal');
        $stats = $response->viewData('stats');
        $weeklyJourney = $response->viewData('weeklyJourney');

        // Assert that weeklyGoal matches stats['weekly_goal']
        $this->assertEquals($stats['weekly_goal'], $weeklyGoal);
        $this->assertEquals($stats['weekly_journey'], $weeklyJourney);
        $this->assertEquals($weeklyGoal['journey'], $stats['weekly_journey']);
    }

    public function test_weekly_goal_cache_invalidation_on_reading_log_creation()
    {
        // Create and authenticate a user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Cache some initial weekly goal data
        $weekStart = now()->startOfWeek(\Carbon\Carbon::SUNDAY)->toDateString();
        $cacheKey = "user_weekly_goal_v2_{$user->id}_{$weekStart}";
        cache()->put($cacheKey, ['test_data' => 'should_be_cleared'], 3600);

        // Verify cache exists
        $this->assertTrue(cache()->has($cacheKey));

        // Create a reading log (which should trigger cache invalidation)
        $response = $this->post('/logs', [
            'book_id' => 1,
            'start_chapter' => '1',
            'date_read' => today()->toDateString(),
            'notes_text' => 'Test passage',
        ]);

        // Assert the reading log was created successfully (returns HTMX view)
        $response->assertStatus(200);

        // Verify the weekly goal cache was invalidated
        $this->assertFalse(cache()->has($cacheKey));
    }
}

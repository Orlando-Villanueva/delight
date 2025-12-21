<?php

namespace Tests\Unit;

use App\Models\User;
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
}

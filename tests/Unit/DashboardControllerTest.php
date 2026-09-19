<?php

namespace Tests\Unit;

use App\Models\ReadingLog;
use App\Models\ReadingPlan;
use App\Models\ReadingPlanSubscription;
use App\Models\User;
use App\Services\StreakStateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private const LOG_TODAY_LABEL = 'Log today';

    private const STREAK_PASSAGE_TEXT = 'Psalms 1';

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

    public function test_index_renders_next_milestone_for_htmx_request()
    {
        $response = $this->get('/dashboard', ['HX-Request' => 'true']);

        $response->assertStatus(200);

        // Match current content
        $response->assertSee('Next Milestone');
        $response->assertDontSee('Weekly Journey');
    }

    public function test_index_renders_pending_streak_status_before_warning_time()
    {
        Carbon::setTestNow('2026-05-16 14:00:00');

        ReadingLog::factory()->create([
            'user_id' => $this->user->id,
            'book_id' => 19,
            'chapter' => 1,
            'passage_text' => self::STREAK_PASSAGE_TEXT,
            'date_read' => today()->subDay(),
        ]);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Not read today');
        $response->assertDontSee('Keep your streak active.');
        $response->assertSee(self::LOG_TODAY_LABEL);
        $response->assertSee('href="'.route('logs.create').'"', false);
        $response->assertSee('hx-push-url="true"', false);
        $response->assertDontSee('Streak at risk');

        Carbon::setTestNow();
    }

    public function test_index_renders_danger_streak_status_after_warning_time()
    {
        Carbon::setTestNow('2026-05-16 18:00:00');

        ReadingLog::factory()->create([
            'user_id' => $this->user->id,
            'book_id' => 19,
            'chapter' => 1,
            'passage_text' => self::STREAK_PASSAGE_TEXT,
            'date_read' => today()->subDay(),
        ]);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Streak at risk');
        $response->assertSee('text-accent-700 dark:text-accent-300', false);
        $response->assertDontSee('Keep your 1-day streak.');
        $response->assertSee(self::LOG_TODAY_LABEL);

        Carbon::setTestNow();
    }

    public function test_index_uses_the_account_timezone_for_the_warning_threshold()
    {
        $this->user->forceFill(['reading_timezone' => 'Asia/Tokyo'])->save();
        $this->travelTo(Carbon::parse('2026-05-16 09:00:00', 'UTC'));

        ReadingLog::factory()->create([
            'user_id' => $this->user->id,
            'book_id' => 19,
            'chapter' => 1,
            'passage_text' => self::STREAK_PASSAGE_TEXT,
            'date_read' => '2026-05-15',
        ]);

        $this->get('/dashboard')
            ->assertStatus(200)
            ->assertSee('Streak at risk');

        $this->travelBack();
    }

    public function test_index_uses_the_account_local_date_for_warning_state_cache()
    {
        $this->user->forceFill(['reading_timezone' => 'Pacific/Honolulu'])->save();
        $this->travelTo(Carbon::parse('2026-05-17 04:00:00', 'UTC'));

        ReadingLog::factory()->create([
            'user_id' => $this->user->id,
            'book_id' => 19,
            'chapter' => 1,
            'passage_text' => self::STREAK_PASSAGE_TEXT,
            'date_read' => '2026-05-15',
        ]);

        $this->get('/dashboard')
            ->assertStatus(200)
            ->assertSee('Streak at risk');

        expect(Cache::has("warning_state_{$this->user->id}_2026-05-16"))->toBeTrue()
            ->and(Cache::has("warning_state_{$this->user->id}_2026-05-17"))->toBeFalse();

        $this->travelBack();
    }

    public function test_index_uses_the_account_timezone_for_the_recap_card()
    {
        $this->user->forceFill(['reading_timezone' => 'Asia/Tokyo'])->save();
        $this->travelTo(Carbon::parse('2025-12-01 04:30:00', 'UTC'));

        $response = $this->get('/dashboard');

        $response->assertStatus(200)
            ->assertViewHas('showRecapCard', true)
            ->assertViewHas('recapCardYear', 2025);

        $this->travelBack();
    }

    public function test_acknowledgment_uses_the_account_local_warning_date_near_midnight()
    {
        $this->user->forceFill(['reading_timezone' => 'Pacific/Honolulu'])->save();
        $this->travelTo(Carbon::parse('2026-05-17 09:59:00', 'UTC'));
        $accountNow = now('Pacific/Honolulu');
        Cache::put("warning_state_{$this->user->id}_{$accountNow->toDateString()}", true, $accountNow->copy()->endOfDay());

        $payload = app(StreakStateService::class)->getMessagePayload(
            currentStreak: 5,
            state: 'active',
            longestStreak: 0,
            hasReadToday: true,
            currentTime: $accountNow,
        );

        expect([
            'Well done! You\'ve read today!',
            'Great job staying consistent!',
            'Your streak is safe for today!',
            'Another day of progress!',
        ])->toContain($payload['message']);

        $this->travelBack();
    }

    public function test_index_renders_streak_status_for_htmx_request()
    {
        Carbon::setTestNow('2026-05-16 14:00:00');

        ReadingLog::factory()->create([
            'user_id' => $this->user->id,
            'book_id' => 19,
            'chapter' => 1,
            'passage_text' => self::STREAK_PASSAGE_TEXT,
            'date_read' => today()->subDay(),
        ]);

        $response = $this->get('/dashboard', ['HX-Request' => 'true']);

        $response->assertStatus(200);
        $response->assertSee('Not read today');
        $response->assertSee(self::LOG_TODAY_LABEL);
        $response->assertDontSee('<!DOCTYPE html>');

        Carbon::setTestNow();
    }

    public function test_index_uses_active_plan_for_cta()
    {
        Carbon::setTestNow('2026-01-10');

        $inactivePlan = ReadingPlan::create([
            'slug' => 'inactive-plan',
            'name' => 'Inactive Plan',
            'description' => 'Inactive plan description',
            'days' => [
                [
                    'day' => 1,
                    'label' => 'Genesis 1',
                    'chapters' => [
                        ['book_id' => 1, 'book_name' => 'Genesis', 'chapter' => 1],
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        $activePlan = ReadingPlan::create([
            'slug' => 'active-plan',
            'name' => 'Active Plan',
            'description' => 'Active plan description',
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

        // Inactive subscription (started earlier, explicitly inactive)
        ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $inactivePlan->id,
            'started_at' => Carbon::today()->subDays(2),
            'is_active' => false,
        ]);

        // Active subscription
        ReadingPlanSubscription::create([
            'user_id' => $this->user->id,
            'reading_plan_id' => $activePlan->id,
            'started_at' => Carbon::today()->subDay(),
            'is_active' => true,
        ]);

        $response = $this->get('/dashboard');

        $response->assertViewHas('planCta', function ($planCta) use ($activePlan) {
            $this->assertTrue($planCta['showPlanCta']);
            $this->assertSame($activePlan->id, $planCta['plan']->id);
            $this->assertSame('Matthew 1', $planCta['planLabel']);

            return true;
        });

        Carbon::setTestNow();
    }
}

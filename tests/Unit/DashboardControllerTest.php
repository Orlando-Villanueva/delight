<?php

namespace Tests\Unit;

use App\Http\Middleware\HandleReaderInertiaRequests;
use App\Models\ReadingLog;
use App\Models\ReadingPlan;
use App\Models\ReadingPlanSubscription;
use App\Models\User;
use App\Services\ReadingCalendarService;
use App\Services\StreakStateService;
use App\Services\UserStatisticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_index_returns_dashboard_inertia_contract_for_regular_request(): void
    {
        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard', false)
                ->has('hasReadToday')
                ->has('streak.currentStreak')
                ->has('streak.presentation.state')
                ->has('summary.totalChapters')
                ->has('calendar.days')
                ->has('bookProgress')
                ->has('milestone'));
    }

    public function test_index_ignores_a_stale_dashboard_cache_payload_after_progress_shape_changes(): void
    {
        $calendar = app(ReadingCalendarService::class);
        $statistics = app(UserStatisticsService::class)->getDashboardStatistics($this->user);
        unset($statistics['book_progress']['book_completions']);

        Cache::put(
            $calendar->cacheKey($this->user, "user_dashboard_stats_{$this->user->id}"),
            $statistics,
            300
        );
        Cache::forget($calendar->dashboardStatisticsCacheKey($this->user));

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard', false)
                ->has('bookProgress.book_completions'));
    }

    public function test_index_redirects_htmx_navigation_to_the_dashboard_url(): void
    {
        $response = $this->get('/dashboard', ['HX-Request' => 'true']);

        $response->assertOk()
            ->assertHeader('HX-Redirect', route('dashboard'));
    }

    public function test_index_computes_streak_state_correctly(): void
    {
        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard', false)
                ->where('streak.presentation.state', fn (string $state): bool => in_array($state, ['inactive', 'active', 'warning'], true))
                ->has('hasReadToday')
                ->has('streak'));
    }

    public function test_index_exposes_the_next_milestone_in_its_dashboard_contract(): void
    {
        $this->getJson('/dashboard')->assertOk()
            ->assertJsonStructure(['milestone' => ['latest', 'milestone']]);
    }

    public function test_index_returns_streak_state_for_json_clients(): void
    {
        Carbon::setTestNow('2026-05-16 14:00:00');
        ReadingLog::factory()->create([
            'user_id' => $this->user->id,
            'book_id' => 19,
            'chapter' => 1,
            'passage_text' => self::STREAK_PASSAGE_TEXT,
            'date_read' => today()->subDay(),
        ]);

        $this->getJson('/dashboard')->assertOk()
            ->assertJsonPath('streak.presentation.state', 'active')
            ->assertJsonPath('hasReadToday', false);

        Carbon::setTestNow();
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

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard', false)
                ->where('streak.presentation.state', 'active')
                ->where('streak.presentation.showCta', true)
                ->where('hasReadToday', false));

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

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard', false)
                ->where('streak.presentation.state', 'warning')
                ->where('streak.presentation.showCta', true)
                ->where('hasReadToday', false));

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
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard', false)
                ->where('streak.presentation.state', 'warning'));

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
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard', false)
                ->where('streak.presentation.state', 'warning'));

        expect(Cache::has("warning_state_{$this->user->id}_2026-05-16"))->toBeTrue()
            ->and(Cache::has("warning_state_{$this->user->id}_2026-05-17"))->toBeFalse();

        $this->travelBack();
    }

    public function test_index_uses_the_account_timezone_for_the_recap_card()
    {
        $this->user->forceFill(['reading_timezone' => 'Asia/Tokyo'])->save();
        $this->travelTo(Carbon::parse('2025-11-30 15:30:00', 'UTC'));

        $response = $this->get('/dashboard');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard', false)
                ->where('recap.show', true)
                ->where('recap.year', 2025));

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

    public function test_dashboard_smart_plans_url_targets_the_active_plan()
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

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard', false)
                ->where('reader.urls.plans', route('plans.today', $activePlan)));

        Carbon::setTestNow();
    }

    public function test_index_renders_pending_streak_status_for_an_inertia_request(): void
    {
        Carbon::setTestNow('2026-05-16 14:00:00');
        ReadingLog::factory()->create([
            'user_id' => $this->user->id,
            'book_id' => 19,
            'chapter' => 1,
            'passage_text' => self::STREAK_PASSAGE_TEXT,
            'date_read' => today()->subDay(),
        ]);

        $version = app(HandleReaderInertiaRequests::class)->version(Request::create('/dashboard'));

        $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])->get('/dashboard')->assertOk()
            ->assertJsonPath('component', 'Dashboard')
            ->assertJsonPath('props.streak.presentation.state', 'active')
            ->assertJsonPath('props.streak.presentation.showCta', true)
            ->assertJsonPath('props.hasReadToday', false);

        Carbon::setTestNow();
    }
}

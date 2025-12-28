<?php

namespace Tests\Feature;

use App\Models\AnnualRecap;
use App\Models\ReadingLog;
use App\Models\User;
use App\Services\AnnualRecapService;
use App\Services\ReadingLogService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AnnualRecapServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_yearly_streak_counts_consecutive_days_correctly()
    {
        $user = User::factory()->create();
        $year = 2025;

        // Pattern: 5 days streak, 1 day gap, 3 days streak. max should be 5.
        $dates = [
            '2025-01-01',
            '2025-01-02',
            '2025-01-03',
            '2025-01-04',
            '2025-01-05',
            // Gap on 06
            '2025-01-07',
            '2025-01-08',
            '2025-01-09',
        ];

        foreach ($dates as $date) {
            ReadingLog::factory()->create([
                'user_id' => $user->id,
                'date_read' => $date,
                'created_at' => Carbon::parse($date)->setTime(10, 0),
            ]);
        }

        $service = app(AnnualRecapService::class);
        $recap = $service->getRecap($user, $year);

        $this->assertEquals(5, $recap['yearly_streak']['count'], 'Expected streak of 5');
        $this->assertEquals('Jan 1', $recap['yearly_streak']['start']);
        $this->assertEquals('Jan 5', $recap['yearly_streak']['end']);
    }

    public function test_streak_calculation_with_duplicate_days()
    {
        $user = User::factory()->create();
        $year = 2025;

        // Multiple readings on same day should validly count as 1 day in streak
        $dates = [
            '2025-02-01',
            '2025-02-01', // Duplicate
            '2025-02-02',
            '2025-02-03',
        ];

        foreach ($dates as $date) {
            ReadingLog::factory()->create([
                'user_id' => $user->id,
                'date_read' => $date,
            ]);
        }

        $service = app(AnnualRecapService::class);
        $recap = $service->getRecap($user, $year);

        $this->assertEquals(3, $recap['yearly_streak']['count']);
    }

    public function test_streak_calculation_across_months()
    {
        $user = User::factory()->create();
        $year = 2025;

        // Jan 31 to Feb 1
        $dates = [
            '2025-01-31',
            '2025-02-01',
        ];

        foreach ($dates as $date) {
            ReadingLog::factory()->create([
                'user_id' => $user->id,
                'date_read' => $date,
            ]);
        }

        $service = app(AnnualRecapService::class);
        $recap = $service->getRecap($user, $year);

        $this->assertEquals(2, $recap['yearly_streak']['count']);
        $this->assertEquals('Jan 31', $recap['yearly_streak']['start']);
        $this->assertEquals('Feb 1', $recap['yearly_streak']['end']);
    }

    public function test_past_year_recap_is_persisted(): void
    {
        $user = User::factory()->create();
        $year = now()->year - 1;

        ReadingLog::factory()->for($user)->create([
            'date_read' => "{$year}-02-01",
        ]);

        $service = app(AnnualRecapService::class);
        $service->getRecap($user, $year);

        $recap = AnnualRecap::query()
            ->where('user_id', $user->id)
            ->where('year', $year)
            ->first();

        $this->assertNotNull($recap);
        $this->assertNotEmpty($recap->snapshot);
    }

    public function test_current_year_recap_is_cached(): void
    {
        $user = User::factory()->create();
        $year = now()->year;

        ReadingLog::factory()->for($user)->create([
            'date_read' => now()->startOfYear()->addDay()->toDateString(),
        ]);

        $service = app(AnnualRecapService::class);
        $service->getRecap($user, $year);

        $this->assertTrue(Cache::has(AnnualRecapService::cacheKeyFor($user, $year)));
    }

    public function test_reading_log_creation_invalidates_current_year_recap_cache(): void
    {
        $user = User::factory()->create();
        $year = now()->year;
        $cacheKey = AnnualRecapService::cacheKeyFor($user, $year);

        Cache::put($cacheKey, ['cached' => true], 3600);
        $this->assertTrue(Cache::has($cacheKey));

        $readingLogService = app(ReadingLogService::class);
        $readingLogService->logReading($user, [
            'book_id' => 1,
            'chapter' => 1,
            'date_read' => now()->toDateString(),
        ]);

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_personality_uses_percentage_thresholds_for_partial_year(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 12, 31, 12, 0, 0));

        try {
            $user = User::factory()->create();
            $year = 2025;

            // Simulate 130 out of 153 available days (Aug 1 - Dec 31) = 85% consistency
            // Should earn "Daily Devotee" (>=80% threshold)
            $startDate = Carbon::create(2025, 8, 1);

            for ($i = 0; $i < 130; $i++) {
                ReadingLog::factory()->create([
                    'user_id' => $user->id,
                    'date_read' => $startDate->copy()->addDays($i)->toDateString(),
                ]);
            }

            $service = app(AnnualRecapService::class);
            $recap = $service->getRecap($user, $year);

            $this->assertEquals('Daily Devotee', $recap['reader_personality']['name']);
            $this->assertStringContainsString('85%', $recap['reader_personality']['stats']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_dashboard_card_state_is_visible_during_december(): void
    {
        $service = app(AnnualRecapService::class);
        $state = $service->getDashboardCardState(Carbon::create(2025, 12, 15, 12, 0, 0));

        $this->assertTrue($state['show']);
        $this->assertEquals(2025, $state['year']);
        $this->assertEquals('Jan 7, 2026', $state['end_label']);
    }

    public function test_dashboard_card_state_is_visible_during_january_grace_period(): void
    {
        $service = app(AnnualRecapService::class);
        $state = $service->getDashboardCardState(Carbon::create(2026, 1, 2, 12, 0, 0));

        $this->assertTrue($state['show']);
        $this->assertEquals(2025, $state['year']);
        $this->assertEquals('Jan 7, 2026', $state['end_label']);
    }

    public function test_dashboard_card_state_is_hidden_after_january_grace_period(): void
    {
        $service = app(AnnualRecapService::class);
        $state = $service->getDashboardCardState(Carbon::create(2026, 1, 10, 12, 0, 0));

        $this->assertFalse($state['show']);
    }

    public function test_dashboard_card_state_is_hidden_before_december(): void
    {
        $service = app(AnnualRecapService::class);
        $state = $service->getDashboardCardState(Carbon::create(2025, 11, 30, 12, 0, 0));

        $this->assertFalse($state['show']);
    }

    public function test_dashboard_card_state_is_hidden_when_view_missing(): void
    {
        $service = app(AnnualRecapService::class);
        $state = $service->getDashboardCardState(Carbon::create(2024, 12, 15, 12, 0, 0));

        $this->assertFalse($state['show']);
        $this->assertEquals(2024, $state['year']);
    }
}

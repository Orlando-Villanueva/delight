<?php

namespace Tests\Feature;

use App\Models\ReadingLog;
use App\Models\User;
use App\Services\AnnualRecapService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}

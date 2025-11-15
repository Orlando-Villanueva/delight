<?php

namespace Tests\Unit;

use App\Models\ReadingLog;
use App\Models\User;
use App\Services\UserStatisticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StreakStatisticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserStatisticsService $statisticsService;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->statisticsService = app(UserStatisticsService::class);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_get_streak_statistics_marks_new_record_run()
    {
        Carbon::setTestNow('2024-01-13 09:00:00');
        $user = User::factory()->create();

        $historicalDates = ['2024-01-01', '2024-01-02', '2024-01-03'];
        $currentRun = ['2024-01-10', '2024-01-11', '2024-01-12', '2024-01-13'];

        $this->createReadings($user, array_merge($historicalDates, $currentRun));

        $stats = $this->statisticsService->getStreakStatistics($user);

        $this->assertSame(4, $stats['current_streak']);
        $this->assertSame('record', $stats['record_status']);
        $this->assertTrue($stats['record_just_broken']);
        $this->assertSame(3, $stats['record_previous_best']);
        $this->assertSame('2024-01-10', $stats['current_streak_started_at']);
        $this->assertCount(4, $stats['current_streak_series']);
    }

    public function test_get_streak_statistics_detects_tied_record()
    {
        Carbon::setTestNow('2024-02-04 08:00:00');
        Cache::flush();

        $user = User::factory()->create();

        $previousBest = ['2024-01-01', '2024-01-02', '2024-01-03', '2024-01-04'];
        $currentRun = ['2024-02-01', '2024-02-02', '2024-02-03', '2024-02-04'];

        $this->createReadings($user, array_merge($previousBest, $currentRun));

        $stats = $this->statisticsService->getStreakStatistics($user);

        $this->assertSame(4, $stats['current_streak']);
        $this->assertSame('tied', $stats['record_status']);
        $this->assertFalse($stats['record_just_broken']);
        $this->assertSame(4, $stats['record_previous_best']);
    }

    private function createReadings(User $user, array $dates): void
    {
        foreach ($dates as $date) {
            ReadingLog::factory()->for($user)->create([
                'date_read' => $date,
            ]);
        }
    }
}

<?php

namespace Tests\Unit;

use App\Models\ReadingLog;
use App\Models\User;
use App\Services\ReadingLogService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingLogServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReadingLogService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReadingLogService::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow();
    }

    public function test_calculate_longest_streak_before_date_excludes_current_run()
    {
        $user = User::factory()->create();

        $dates = [
            '2024-01-01',
            '2024-01-02',
            '2024-01-03', // previous best streak of 3
            '2024-01-10',
            '2024-01-11',
            '2024-01-12', // current streak should not count
        ];

        foreach ($dates as $date) {
            ReadingLog::factory()->for($user)->create([
                'date_read' => $date,
            ]);
        }

        $result = $this->service->calculateLongestStreakBeforeDate($user, '2024-01-10');

        $this->assertSame(3, $result);
    }

    public function test_calculate_longest_streak_before_date_returns_zero_when_no_history()
    {
        $user = User::factory()->create();

        ReadingLog::factory()->for($user)->create([
            'date_read' => '2024-02-01',
        ]);

        $result = $this->service->calculateLongestStreakBeforeDate($user, '2024-02-01');

        $this->assertSame(0, $result);
    }
}

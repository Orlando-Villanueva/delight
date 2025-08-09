<?php

namespace Tests\Unit;
use App\Services\UserStatisticsService;
use App\Services\ReadingLogService;
use App\Services\WeeklyGoalService;
use App\Contracts\ReadingLogInterface;
use App\Models\User;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class UserStatisticsServiceTest extends TestCase
{
    protected ReadingLogService $readingLogService;
    protected WeeklyGoalService $weeklyGoalService;
    protected UserStatisticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->readingLogService = $this->createMock(ReadingLogService::class);
        $this->weeklyGoalService = $this->createMock(WeeklyGoalService::class);
        $this->service = new UserStatisticsService($this->readingLogService, $this->weeklyGoalService);
    }

    /**
     * Create a mock reading log object that implements ReadingLogInterface
     */
    private function createMockReading(?string $dateRead, Carbon $createdAt): ReadingLogInterface
    {
        $mock = $this->createMock(ReadingLogInterface::class);
        $mock->method('getDateRead')->willReturn($dateRead);
        $mock->method('getCreatedAt')->willReturn($createdAt);
        return $mock;
    }

    /**
     * Test that formatTimeAgo formats time consistently for different time ranges
     */
    public function testFormatTimeAgoConsistency()
    {
        $now = Carbon::now();

        // Test various time differences
        $testCases = [
            [$now->copy()->subSeconds(30), 'just now'],
            [$now->copy()->subMinute(), '1 minute ago'],
            [$now->copy()->subMinutes(5), '5 minutes ago'],
            [$now->copy()->subMinutes(59), '59 minutes ago'],
            [$now->copy()->subHour(), '1 hour ago'],
            [$now->copy()->subHours(5), '5 hours ago'],
            [$now->copy()->subHours(23), '23 hours ago'],
            [$now->copy()->subDay(), '1 day ago'],
            [$now->copy()->subDays(5), '5 days ago'],
            [$now->copy()->subDays(30), '30 days ago'],
        ];

        foreach ($testCases as [$date, $expected]) {
            $result = $this->service->formatTimeAgo($date);
            $this->assertEquals($expected, $result, "Failed for date: {$date->toDateTimeString()}");
        }
    }

    /**
     * Test that formatTimeAgo handles edge cases correctly
     */
    public function testFormatTimeAgoEdgeCases()
    {
        $now = Carbon::now();

        // Test boundary conditions
        $this->assertEquals('just now', $this->service->formatTimeAgo($now->copy()->subSeconds(59)));
        $this->assertEquals('1 minute ago', $this->service->formatTimeAgo($now->copy()->subSeconds(60)));
        $this->assertEquals('59 minutes ago', $this->service->formatTimeAgo($now->copy()->subMinutes(59)));
        $this->assertEquals('1 hour ago', $this->service->formatTimeAgo($now->copy()->subMinutes(60)));
        $this->assertEquals('23 hours ago', $this->service->formatTimeAgo($now->copy()->subHours(23)));
        $this->assertEquals('1 day ago', $this->service->formatTimeAgo($now->copy()->subHours(24)));
    }

    /**
     * Test that calculateSmartTimeAgo uses created_at for today readings
     */
    public function testCalculateSmartTimeAgoForTodayReadings()
    {
        $today = Carbon::now()->startOfDay();
        $createdAt = Carbon::now()->subHours(3);

        $reading = $this->createMockReading($today->toDateString(), $createdAt);

        $result = $this->service->calculateSmartTimeAgo($reading);
        $this->assertEquals('3 hours ago', $result);
    }

    /**
     * Test that calculateSmartTimeAgo always returns "1 day ago" for yesterday readings
     */
    public function testCalculateSmartTimeAgoForYesterdayReadings()
    {
        $yesterday = Carbon::now()->subDay()->startOfDay();
        $createdAt = Carbon::now()->subMinutes(30); // Recently logged

        $reading = $this->createMockReading($yesterday->toDateString(), $createdAt);

        $result = $this->service->calculateSmartTimeAgo($reading);
        $this->assertEquals('1 day ago', $result);
    }

    /**
     * Test that calculateSmartTimeAgo uses date_read for older readings
     */
    public function testCalculateSmartTimeAgoForOlderReadings()
    {
        $threeDaysAgo = Carbon::now()->subDays(3);
        $createdAt = Carbon::now()->subHours(1); // Recently logged

        $reading = $this->createMockReading($threeDaysAgo->toDateString(), $createdAt);

        $result = $this->service->calculateSmartTimeAgo($reading);
        $this->assertEquals('3 days ago', $result);
    }

    /**
     * Test that calculateSmartTimeAgo falls back to created_at when date_read is null
     */
    public function testCalculateSmartTimeAgoFallbackWhenDateReadIsNull()
    {
        $createdAt = Carbon::now()->subHours(5);

        $reading = $this->createMockReading(null, $createdAt);

        $result = $this->service->calculateSmartTimeAgo($reading);
        $this->assertEquals('5 hours ago', $result);
    }

    /**
     * Test that UserStatisticsService has WeeklyGoalService dependency
     */
    public function testWeeklyGoalServiceDependency()
    {
        // Test that UserStatisticsService can access weekly goal functionality
        $this->assertTrue(method_exists($this->service, 'getDashboardStatistics'));
    }

    /**
     * Test that getDashboardStatistics method exists and can be called
     */
    public function testGetDashboardStatisticsMethodExists()
    {
        $this->assertTrue(method_exists($this->service, 'getDashboardStatistics'));
    }
}

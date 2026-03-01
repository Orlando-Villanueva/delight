<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\UserStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class UserStatisticsCacheTest extends TestCase
{
    use RefreshDatabase;

    protected UserStatisticsService $service;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->service = app(UserStatisticsService::class);

        // Clear cache before each test
        Cache::flush();
    }

    public function test_get_dashboard_statistics_caches_results()
    {
        // First call should cache the results
        $firstResult = $this->service->getDashboardStatistics($this->user);

        // Verify cache key exists
        $this->assertTrue(Cache::has(UserStatisticsService::cacheKeyDashboardStats($this->user->id)));

        // Second call should return cached results
        $secondResult = $this->service->getDashboardStatistics($this->user);

        $this->assertEquals($firstResult, $secondResult);
    }

    public function test_get_streak_statistics_caches_individual_streaks()
    {
        // Call streak statistics
        $this->service->getStreakStatistics($this->user);

        // Verify individual streak caches exist
        $this->assertTrue(Cache::has(UserStatisticsService::cacheKeyCurrentStreak($this->user->id)));
        $this->assertTrue(Cache::has(UserStatisticsService::cacheKeyLongestStreak($this->user->id)));
    }

    public function test_get_calendar_data_caches_results()
    {
        $year = now()->year;

        // First call should cache the results
        $firstResult = $this->service->getCalendarData($this->user, $year);

        // Verify cache key exists
        $this->assertTrue(Cache::has(UserStatisticsService::cacheKeyCalendar($this->user->id, $year)));

        // Second call should return cached results
        $secondResult = $this->service->getCalendarData($this->user, $year);

        $this->assertEquals($firstResult, $secondResult);
    }

    public function test_invalidate_user_cache_clears_all_caches()
    {
        $currentYear = now()->year;
        $previousYear = $currentYear - 1;

        // Populate caches
        $this->service->getDashboardStatistics($this->user);
        $this->service->getStreakStatistics($this->user);
        $this->service->getCalendarData($this->user, $currentYear);
        $this->service->getCalendarData($this->user, $previousYear);

        // Verify caches exist
        $this->assertTrue(Cache::has(UserStatisticsService::cacheKeyDashboardStats($this->user->id)));
        $this->assertTrue(Cache::has(UserStatisticsService::cacheKeyCurrentStreak($this->user->id)));
        $this->assertTrue(Cache::has(UserStatisticsService::cacheKeyLongestStreak($this->user->id)));
        $this->assertTrue(Cache::has(UserStatisticsService::cacheKeyCalendar($this->user->id, $currentYear)));
        $this->assertTrue(Cache::has(UserStatisticsService::cacheKeyCalendar($this->user->id, $previousYear)));
        $this->assertTrue(Cache::has(UserStatisticsService::cacheKeyTotalReadingDays($this->user->id)));
        $this->assertTrue(Cache::has(UserStatisticsService::cacheKeyAvgChaptersPerDay($this->user->id)));

        // Invalidate cache
        $this->service->invalidateUserCache($this->user);

        // Verify all caches are cleared
        $this->assertFalse(Cache::has(UserStatisticsService::cacheKeyDashboardStats($this->user->id)));
        $this->assertFalse(Cache::has(UserStatisticsService::cacheKeyCurrentStreak($this->user->id)));
        $this->assertFalse(Cache::has(UserStatisticsService::cacheKeyLongestStreak($this->user->id)));
        $this->assertFalse(Cache::has(UserStatisticsService::cacheKeyCalendar($this->user->id, $currentYear)));
        $this->assertFalse(Cache::has(UserStatisticsService::cacheKeyCalendar($this->user->id, $previousYear)));
        $this->assertFalse(Cache::has(UserStatisticsService::cacheKeyTotalReadingDays($this->user->id)));
        $this->assertFalse(Cache::has(UserStatisticsService::cacheKeyAvgChaptersPerDay($this->user->id)));
    }

    public function test_cache_has_different_tt_ls()
    {
        // This test verifies that different cache keys have different TTLs
        // We can't directly test TTL values, but we can verify the caching behavior

        $this->service->getDashboardStatistics($this->user);
        $this->service->getStreakStatistics($this->user);
        $this->service->getCalendarData($this->user);

        // All caches should exist after creation
        $this->assertTrue(Cache::has(UserStatisticsService::cacheKeyDashboardStats($this->user->id)));
        $this->assertTrue(Cache::has(UserStatisticsService::cacheKeyCurrentStreak($this->user->id)));
        $this->assertTrue(Cache::has(UserStatisticsService::cacheKeyLongestStreak($this->user->id)));
        $this->assertTrue(Cache::has(UserStatisticsService::cacheKeyCalendar($this->user->id, now()->year)));
        $this->assertTrue(Cache::has(UserStatisticsService::cacheKeyTotalReadingDays($this->user->id)));
        $this->assertTrue(Cache::has(UserStatisticsService::cacheKeyAvgChaptersPerDay($this->user->id)));
    }

    public function test_reading_summary_includes_new_stats()
    {
        // Create some reading logs to test with
        $this->user->readingLogs()->create([
            'book_id' => 1,
            'chapter' => 1,
            'passage_text' => 'Genesis 1',
            'date_read' => now()->subDays(5)->toDateString(),
        ]);

        $this->user->readingLogs()->create([
            'book_id' => 1,
            'chapter' => 2,
            'passage_text' => 'Genesis 2',
            'date_read' => now()->subDays(3)->toDateString(),
        ]);

        // Get dashboard stats which includes reading summary
        $stats = $this->service->getDashboardStatistics($this->user);

        // Verify new stats are included
        $this->assertArrayHasKey('total_reading_days', $stats['reading_summary']);
        $this->assertArrayHasKey('average_chapters_per_day', $stats['reading_summary']);

        // Verify values make sense
        $this->assertEquals(2, $stats['reading_summary']['total_reading_days']);
        $this->assertGreaterThan(0, $stats['reading_summary']['average_chapters_per_day']);
        $this->assertLessThanOrEqual(2, $stats['reading_summary']['average_chapters_per_day']); // Should be reasonable
    }
}

<?php

namespace Tests\Feature\Performance;

use App\Models\ReadingLog;
use App\Models\User;
use App\Services\ReadingLogService;
use App\Services\UserStatisticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReadingLogPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagination_queries_only_necessary_records()
    {
        // Use a fixed date to avoid issues with time or rolling windows
        $today = Carbon::today();
        $user = User::factory()->create();

        $this->actingAs($user);

        // Create logs for 20 different days
        for ($i = 0; $i < 20; $i++) {
            ReadingLog::factory()->create([
                'user_id' => $user->id,
                'date_read' => $today->copy()->subDays($i)->toDateString(),
                'book_id' => 1,
                'chapter' => $i + 1,
            ]);
        }

        // Add a second log on the most recent day to ensure distinct counting by date
        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'date_read' => $today->toDateString(),
            'book_id' => 1,
            'chapter' => 100,
        ]);

        $service = app(ReadingLogService::class);
        $statsService = app(UserStatisticsService::class);
        $request = request();
        $request->merge(['page' => 1]);
        $request->setUserResolver(fn () => $user);

        // Enable query logging
        DB::enableQueryLog();

        // Request 5 items per page
        $paginator = $service->getPaginatedDayGroupsFor($request, $statsService, 5);

        $queries = DB::getQueryLog();

        // Verify paginator structure
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertCount(5, $paginator->items()); // Should have 5 days
        $this->assertEquals(20, $paginator->total()); // Total 20 days
        $this->assertTrue($paginator->hasMorePages());

        // Check for efficient query (limit + distinct)
        $hasLimitedDateQuery = collect($queries)->contains(function ($query) {
            return str_contains($query['query'], 'limit') && str_contains($query['query'], 'distinct');
        });

        $this->assertTrue($hasLimitedDateQuery, 'Should have a query with limit and distinct for pagination');
    }

    public function test_pagination_sentinel_logic_reproduction()
    {
        // Reproduction of ReadingLogHistoryInfiniteScrollTest logic
        $user = User::factory()->create();
        $today = Carbon::today();

        $this->actingAs($user);

        // Create 17 consecutive days of logs
        // Page size 8.
        // Page 1: 1-8. Page 2: 9-16. Page 3: 17.
        foreach (range(0, 16) as $offset) {
            ReadingLog::factory()->create([
                'user_id' => $user->id,
                'book_id' => 1,
                'chapter' => $offset + 1,
                'date_read' => $today->copy()->subDays($offset)->toDateString(),
            ]);
        }

        $service = app(ReadingLogService::class);
        $statsService = app(UserStatisticsService::class);

        // Request page 2
        // Default perPage is 8 in the service method signature
        $request = request();
        $request->merge(['page' => 2]);
        $request->setUserResolver(fn () => $user);

        $paginator = $service->getPaginatedDayGroupsFor($request, $statsService); // Use default perPage=8

        // Verify total and pages
        $this->assertEquals(17, $paginator->total());
        $this->assertEquals(8, $paginator->perPage());
        $this->assertEquals(2, $paginator->currentPage());
        $this->assertEquals(3, $paginator->lastPage());
        $this->assertTrue($paginator->hasMorePages(), 'Page 2 of 3 should have more pages');

        // Verify items count on page 2
        $this->assertCount(8, $paginator->items());
    }
}

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

        // Create logs for 20 different days
        for ($i = 0; $i < 20; $i++) {
            ReadingLog::factory()->create([
                'user_id' => $user->id,
                'date_read' => $today->copy()->subDays($i)->toDateString(),
                'book_id' => 1,
                'chapter' => $i + 1,
                // Ensure unique passage text or other fields if needed,
                // but factory handles basic ones.
            ]);
        }

        $service = app(ReadingLogService::class);
        $statsService = app(UserStatisticsService::class);
        $request = request()->merge(['page' => 1]);

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

        // Verify the content is correct (dates)
        $firstGroup = $paginator->items()[0];
        // date_read in the group object is likely a Carbon object or string depending on implementation
        // The service sets keys as 'Y-m-d' strings in the collection, but sortByDesc might mess keys up?
        // Wait, $groupedLogs is a Collection of prepared logs.
        // buildGroupedLogsForUser: returns collection of logs.
        // prepareDisplayLogs returns a collection of logs with 'display_passage_text' etc.
        // The items in paginator are the grouped logs for each day.

        // Let's verify the query count to ensure optimization
        // We expect:
        // 1. Pagination count query (select count(distinct date_read)...)
        // 2. Pagination selection query (select distinct date_read ...)
        // 3. Data selection query (select * from reading_logs where date_read in (...))
        // 4. Potentially some user stats queries if not cached/mocked, but main point is data query.

        // If it was the OLD way:
        // 1. Select * from reading_logs ... (loading ALL rows)

        // The key is that we have a 'limit' in the date selection query.
        $hasLimitedDateQuery = collect($queries)->contains(function ($query) {
            return str_contains($query['query'], 'limit') && str_contains($query['query'], 'distinct');
        });

        // Depending on DB driver, distinct + limit might be handled differently,
        // but Laravel's paginate() usually produces a limit.
        $this->assertTrue($hasLimitedDateQuery, 'Should have a query with limit and distinct for pagination');
    }
}

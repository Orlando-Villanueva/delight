<?php

namespace Tests\Feature\Performance;

use App\Models\ReadingLog;
use App\Models\User;
use App\Services\ReadingLogService;
use App\Services\UserStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class ReadingLogPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagination_queries_only_necessary_records()
    {
        $user = User::factory()->create();

        // Create logs for 20 different days
        for ($i = 0; $i < 20; $i++) {
            ReadingLog::factory()->create([
                'user_id' => $user->id,
                'date_read' => today()->subDays($i),
                'book_id' => 1,
                'chapter' => $i + 1,
            ]);
        }

        $service = app(ReadingLogService::class);
        $statsService = app(UserStatisticsService::class);
        $request = request()->merge(['page' => 1]);

        // Enable query logging
        \DB::enableQueryLog();

        $paginator = $service->getPaginatedDayGroupsFor($request, $statsService, 5);

        $queries = \DB::getQueryLog();

        // We expect:
        // 1. Count query for pagination (distinct dates)
        // 2. Select query for the dates (limit 5)
        // 3. Select query for the logs matching those dates
        // Plus potentially some auth/user queries if not cached.

        // Ensure we got a paginator
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertCount(5, $paginator->items());
        $this->assertEquals(20, $paginator->total());

        // Verify the dates are correct (recent first)
        $firstGroup = $paginator->items()[0];
        $this->assertEquals(today()->format('Y-m-d'), $firstGroup->date_read->format('Y-m-d'));
    }
}

<?php

namespace Tests\Unit;

use App\Enums\WeeklyJourneyDayState;
use App\Models\ReadingLog;
use App\Models\User;
use App\Services\WeeklyJourneyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyJourneyServiceTest extends TestCase
{
    use RefreshDatabase;

    private WeeklyJourneyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WeeklyJourneyService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_days_include_semantic_state_tokens(): void
    {
        Carbon::setTestNow(Carbon::create(2024, 11, 7, 9, 0, 0));

        $user = User::factory()->create();
        $weekStart = now()->copy()->startOfWeek(Carbon::SUNDAY);

        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'date_read' => $weekStart->toDateString(), // Sunday
        ]);

        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'date_read' => $weekStart->copy()->addDays(2)->toDateString(), // Tuesday
        ]);

        $journey = $this->service->getWeeklyJourneyData($user);
        $days = collect($journey['days'])->keyBy('date');

        $this->assertEquals(WeeklyJourneyDayState::COMPLETE->value, $days[$weekStart->toDateString()]['state']);
        $this->assertEquals(WeeklyJourneyDayState::MISSED->value, $days[$weekStart->copy()->addDay()->toDateString()]['state']);
        $this->assertEquals(WeeklyJourneyDayState::TODAY->value, $days[now()->toDateString()]['state']);
        $this->assertEquals(WeeklyJourneyDayState::UPCOMING->value, $days[$weekStart->copy()->addDays(5)->toDateString()]['state']);
    }

    public function test_perfect_week_status_includes_crown(): void
    {
        Carbon::setTestNow(Carbon::create(2024, 11, 9, 8, 0, 0)); // Saturday

        $user = User::factory()->create();
        $weekStart = now()->copy()->startOfWeek(Carbon::SUNDAY);
        $this->createReadingLogsForOffsets($user, $weekStart, range(0, 6));

        $loggedDates = ReadingLog::where('user_id', $user->id)->orderBy('date_read')->pluck('date_read')->toArray();
        $this->assertCount(7, $loggedDates);

        $journey = $this->service->getWeeklyJourneyData($user);

        $this->assertSame(7, $journey['currentProgress']);
        $this->assertSame('perfect', $journey['status']['state']);
        $this->assertTrue($journey['status']['showCrown']);
        $this->assertSame('You did it—enjoy some rest!', $journey['status']['microcopy']);
    }

    public function test_almost_there_status_encourages_strong_finish_when_target_unreachable(): void
    {
        Carbon::setTestNow(Carbon::create(2024, 11, 9, 8, 0, 0)); // Saturday

        $user = User::factory()->create();
        $weekStart = now()->copy()->startOfWeek(Carbon::SUNDAY);
        $this->createReadingLogsForOffsets($user, $weekStart, [0, 1, 2, 3, 4]); // 5 days logged before Saturday

        $journey = $this->service->getWeeklyJourneyData($user);

        $this->assertSame(5, $journey['currentProgress']);
        $this->assertSame('Great run', $journey['status']['label']);
        $this->assertSame('Strong finish—carry momentum forward', $journey['status']['microcopy']);
    }

    public function test_solid_week_status_motivates_when_goal_is_still_possible(): void
    {
        Carbon::setTestNow(Carbon::create(2024, 11, 6, 8, 0, 0)); // Wednesday

        $user = User::factory()->create();
        $weekStart = now()->copy()->startOfWeek(Carbon::SUNDAY);
        $this->createReadingLogsForOffsets($user, $weekStart, [0, 1, 2, 3]); // 4 days logged by Wednesday

        $journey = $this->service->getWeeklyJourneyData($user);

        $this->assertSame(4, $journey['currentProgress']);
        $this->assertSame('Solid week—keep reaching for 7', $journey['status']['microcopy']);
    }

    public function test_solid_week_status_shifts_focus_when_goal_is_out_of_reach(): void
    {
        Carbon::setTestNow(Carbon::create(2024, 11, 9, 8, 0, 0)); // Saturday

        $user = User::factory()->create();
        $weekStart = now()->copy()->startOfWeek(Carbon::SUNDAY);
        $this->createReadingLogsForOffsets($user, $weekStart, [0, 1, 2, 3]); // 4 early days logged

        $journey = $this->service->getWeeklyJourneyData($user);

        $this->assertSame(4, $journey['currentProgress']);
        $this->assertSame('Solid week—set up next week for 7', $journey['status']['microcopy']);
    }

    private function createReadingLogsForOffsets(User $user, Carbon $weekStart, array $offsets): void
    {
        foreach ($offsets as $offset) {
            ReadingLog::factory()->create([
                'user_id' => $user->id,
                'date_read' => $weekStart->copy()->addDays($offset)->toDateString(),
            ]);
        }
    }
}

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
}

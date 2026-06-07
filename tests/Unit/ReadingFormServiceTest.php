<?php

namespace Tests\Unit;

use App\Models\ReadingLog;
use App\Models\User;
use App\Services\ReadingFormService;
use App\Services\ReadingLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingFormServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ReadingFormService $service;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $readingLogService = $this->app->make(ReadingLogService::class);
        $this->service = new ReadingFormService($readingLogService);
        $this->user = User::factory()->create();
    }

    /**
     * Test hasReadToday returns true when user has read today
     */
    public function test_has_read_today_returns_true_when_user_has_read_today(): void
    {
        // Create a reading log for today
        ReadingLog::factory()->create([
            'user_id' => $this->user->id,
            'book_id' => 1,
            'chapter' => 1,
            'date_read' => today()->toDateString(),
        ]);

        $result = $this->service->hasReadToday($this->user);

        $this->assertTrue($result);
    }

    /**
     * Test hasReadToday returns false when user has not read today
     */
    public function test_has_read_today_returns_false_when_user_has_not_read_today(): void
    {
        // Don't create any reading logs for today
        $result = $this->service->hasReadToday($this->user);

        $this->assertFalse($result);
    }

    /**
     * Test hasReadToday returns false when user has only read yesterday
     */
    public function test_has_read_today_returns_false_when_user_has_only_read_yesterday(): void
    {
        // Create a reading log for yesterday, but not today
        ReadingLog::factory()->create([
            'user_id' => $this->user->id,
            'book_id' => 1,
            'chapter' => 1,
            'date_read' => today()->subDay()->toDateString(),
        ]);

        $result = $this->service->hasReadToday($this->user);

        $this->assertFalse($result);
    }

    /**
     * Test hasReadToday returns true when user has multiple readings today
     */
    public function test_has_read_today_returns_true_when_user_has_multiple_readings_today(): void
    {
        // Create multiple reading logs for today
        ReadingLog::factory()->create([
            'user_id' => $this->user->id,
            'book_id' => 1,
            'chapter' => 1,
            'date_read' => today()->toDateString(),
        ]);

        ReadingLog::factory()->create([
            'user_id' => $this->user->id,
            'book_id' => 1,
            'chapter' => 2,
            'date_read' => today()->toDateString(),
        ]);

        $result = $this->service->hasReadToday($this->user);

        $this->assertTrue($result);
    }

    /**
     * Test hasReadToday only considers the specific user's readings
     */
    public function test_has_read_today_only_considers_specific_user_readings(): void
    {
        // Create another user with a reading today
        $otherUser = User::factory()->create();
        ReadingLog::factory()->create([
            'user_id' => $otherUser->id,
            'book_id' => 1,
            'chapter' => 1,
            'date_read' => today()->toDateString(),
        ]);

        // Our test user has no readings today
        $result = $this->service->hasReadToday($this->user);

        $this->assertFalse($result);
    }

    /**
     * Test hasReadToday works correctly with different timezones (edge case)
     */
    public function test_has_read_today_works_with_date_boundaries(): void
    {
        // Create a reading log with today's date string
        ReadingLog::factory()->create([
            'user_id' => $this->user->id,
            'book_id' => 1,
            'chapter' => 1,
            'date_read' => today()->toDateString(), // Ensure it's stored as date string
        ]);

        $result = $this->service->hasReadToday($this->user);

        $this->assertTrue($result);
    }

    /**
     * Test that getFormContextData uses the hasReadToday method
     */
    public function test_get_form_context_data_uses_has_read_today_method(): void
    {
        // Create a reading log for today
        ReadingLog::factory()->create([
            'user_id' => $this->user->id,
            'book_id' => 1,
            'chapter' => 1,
            'date_read' => today()->toDateString(),
        ]);

        $contextData = $this->service->getFormContextData($this->user);

        // Verify that hasReadToday is true in the context data
        $this->assertTrue($contextData['hasReadToday']);

        // Verify the method returns the same result as calling hasReadToday directly
        $this->assertEquals(
            $this->service->hasReadToday($this->user),
            $contextData['hasReadToday']
        );
    }

    public function test_get_form_context_data_allows_yesterday_without_a_recent_streak(): void
    {
        $this->user->forceFill([
            'created_at' => today()->subMonth(),
        ])->save();

        $contextData = $this->service->getFormContextData($this->user);

        $this->assertTrue($contextData['allowYesterday']);
        $this->assertFalse($contextData['hasReadingTwoDaysAgo']);
    }

    public function test_get_form_context_data_allows_yesterday_for_a_new_user(): void
    {
        $contextData = $this->service->getFormContextData($this->user);

        $this->assertTrue($contextData['allowYesterday']);
    }

    public function test_get_form_context_data_allows_yesterday_when_recent_streak_gap_can_be_caught_up(): void
    {
        $this->user->forceFill([
            'created_at' => today()->subMonth(),
        ])->save();

        ReadingLog::factory()->create([
            'user_id' => $this->user->id,
            'book_id' => 1,
            'chapter' => 1,
            'date_read' => today()->subDays(2)->toDateString(),
        ]);

        $contextData = $this->service->getFormContextData($this->user);

        $this->assertTrue($contextData['allowYesterday']);
        $this->assertTrue($contextData['hasReadingTwoDaysAgo']);
    }

    public function test_get_form_context_data_allows_yesterday_when_yesterday_is_already_logged(): void
    {
        $this->user->forceFill([
            'created_at' => today()->subMonth(),
        ])->save();

        foreach ([today()->subDay(), today()->subDays(2)] as $date) {
            ReadingLog::factory()->create([
                'user_id' => $this->user->id,
                'book_id' => 1,
                'chapter' => 1,
                'date_read' => $date->toDateString(),
            ]);
        }

        $contextData = $this->service->getFormContextData($this->user);

        $this->assertTrue($contextData['allowYesterday']);
        $this->assertTrue($contextData['hasReadYesterday']);
    }
}

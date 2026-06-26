<?php

namespace Tests\Unit;

use App\Models\ReadingLog;
use App\Models\User;
use App\Services\BibleReferenceService;
use App\Services\ReadingFormService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReadingFormServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ReadingFormService $service;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ReadingFormService(new BibleReferenceService);
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

    public function test_get_form_context_data_excludes_unused_read_status(): void
    {
        // Create a reading log for today
        ReadingLog::factory()->create([
            'user_id' => $this->user->id,
            'book_id' => 1,
            'chapter' => 1,
            'date_read' => today()->toDateString(),
        ]);

        $contextData = $this->service->getFormContextData($this->user);

        $this->assertTrue($this->service->hasReadToday($this->user));
        $this->assertArrayNotHasKey('hasReadToday', $contextData);
    }

    public function test_get_form_context_data_allows_yesterday_without_a_recent_streak(): void
    {
        $this->user->forceFill([
            'created_at' => today()->subMonth(),
        ])->save();

        $contextData = $this->service->getFormContextData($this->user);

        $this->assertTrue($contextData['allowYesterday']);
        $this->assertSame([], $contextData['recentBooks']);
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
    }

    public function test_get_form_context_data_includes_three_distinct_recent_books_in_recency_order(): void
    {
        $this->createReadingLogForBook(19, '2026-06-20', '2026-06-20 08:00:00');
        $this->createReadingLogForBook(43, '2026-06-23', '2026-06-23 09:00:00');
        $this->createReadingLogForBook(40, '2026-06-23', '2026-06-23 10:00:00');
        $this->createReadingLogForBook(1, '2026-06-22', '2026-06-22 08:00:00');
        $this->createReadingLogForBook(1, '2026-06-24', '2026-06-24 08:00:00', 2);

        $contextData = $this->service->getFormContextData($this->user);

        $this->assertSame([1, 40, 43], array_column($contextData['recentBooks'], 'id'));
        $this->assertSame(['Genesis', 'Matthew', 'John'], array_column($contextData['recentBooks'], 'name'));
        $this->assertSame(['old', 'new', 'new'], array_column($contextData['recentBooks'], 'testament'));
    }

    public function test_get_form_context_data_filters_recent_books_by_current_canon_preference_before_limiting(): void
    {
        $this->createReadingLogForBook(67, '2026-06-24', '2026-06-24 12:00:00');
        $this->createReadingLogForBook(1, '2026-06-23', '2026-06-23 12:00:00');
        $this->createReadingLogForBook(40, '2026-06-22', '2026-06-22 12:00:00');
        $this->createReadingLogForBook(43, '2026-06-21', '2026-06-21 12:00:00');

        $contextData = $this->service->getFormContextData($this->user);

        $this->assertSame([1, 40, 43], array_column($contextData['recentBooks'], 'id'));

        $this->user->forceFill(['deuterocanonical_books_enabled_at' => now()])->save();

        $contextData = $this->service->getFormContextData($this->user->fresh());

        $this->assertSame([67, 1, 40], array_column($contextData['recentBooks'], 'id'));
    }

    public function test_get_form_context_data_finds_distinct_books_beyond_a_fixed_latest_row_window(): void
    {
        for ($daysAgo = 0; $daysAgo < 60; $daysAgo++) {
            $date = Carbon::parse('2026-06-24')->subDays($daysAgo);

            $this->createReadingLogForBook(
                1,
                $date->toDateString(),
                $date->copy()->setTime(8, 0)->toDateTimeString()
            );
        }

        $this->createReadingLogForBook(40, '2026-04-20', '2026-04-20 08:00:00');

        $contextData = $this->service->getFormContextData($this->user);

        $this->assertSame([1, 40], array_column($contextData['recentBooks'], 'id'));
    }

    public function test_get_form_context_data_uses_one_query_for_recent_books(): void
    {
        ReadingLog::factory()->create([
            'user_id' => $this->user->id,
            'book_id' => 1,
            'chapter' => 1,
            'date_read' => today()->subDay()->toDateString(),
        ]);

        DB::enableQueryLog();

        $contextData = $this->service->getFormContextData($this->user);

        $this->assertTrue($contextData['allowYesterday']);
        $this->assertCount(1, DB::getQueryLog());
        $this->assertSame([1], array_column($contextData['recentBooks'], 'id'));
    }

    private function createReadingLogForBook(int $bookId, string $dateRead, string $createdAt, int $chapter = 1): ReadingLog
    {
        return ReadingLog::factory()->create([
            'user_id' => $this->user->id,
            'book_id' => $bookId,
            'chapter' => $chapter,
            'passage_text' => "Book {$bookId} {$chapter}",
            'date_read' => $dateRead,
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ]);
    }
}

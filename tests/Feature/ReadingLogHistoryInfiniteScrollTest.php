<?php

namespace Tests\Feature;

use App\Models\ReadingLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingLogHistoryInfiniteScrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_older_history_uses_an_exclusive_date_cursor_and_returns_next_url(): void
    {
        $user = User::factory()->create();
        foreach (range(0, 16) as $offset) {
            ReadingLog::factory()->create([
                'user_id' => $user->id,
                'book_id' => 1,
                'chapter' => $offset + 1,
                'passage_text' => 'Genesis '.($offset + 1),
                'date_read' => Carbon::today()->subDays($offset)->toDateString(),
                'created_at' => Carbon::today()->subDays($offset)->setTime(8, 0),
                'updated_at' => Carbon::today()->subDays($offset)->setTime(8, 0),
            ]);
        }

        $firstPage = $this->actingAs($user)->get(route('logs.index'));
        $firstPage->assertInertia(fn ($page) => $page
            ->component('History')
            ->has('days', 8)
            ->where('hasLogs', true)
            ->where('days.7.date', Carbon::today()->subDays(7)->toDateString())
            ->where('nextUrl', route('logs.older', ['before' => Carbon::today()->subDays(7)->toDateString()]))
        );

        $older = $this->get(route('logs.older', ['before' => Carbon::today()->subDays(7)->toDateString()]));
        $older->assertOk()
            ->assertJsonPath('days.0.date', Carbon::today()->subDays(8)->toDateString())
            ->assertJsonPath('days.7.date', Carbon::today()->subDays(15)->toDateString())
            ->assertJsonPath('hasLogs', true)
            ->assertJsonPath('nextUrl', route('logs.older', ['before' => Carbon::today()->subDays(15)->toDateString()]));
    }

    public function test_older_history_rejects_invalid_cursor_dates(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('logs.older', ['before' => 'not-a-date']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('before');
    }

    public function test_cursor_does_not_skip_days_after_the_cursor_day_is_deleted(): void
    {
        $user = User::factory()->create();
        foreach (range(0, 16) as $offset) {
            ReadingLog::factory()->for($user)->create([
                'book_id' => 1, 'chapter' => $offset + 1,
                'date_read' => today()->subDays($offset)->toDateString(),
            ]);
        }
        $cursorDate = today()->subDays(7)->toDateString();
        $log = $user->readingLogs()->whereDate('date_read', $cursorDate)->firstOrFail();
        $this->actingAs($user)->deleteJson(route('logs.destroy', $log))
            ->assertOk()->assertJsonPath('day', null)->assertJsonPath('hasLogs', true);

        $this->getJson(route('logs.older', ['before' => $cursorDate]))
            ->assertOk()->assertJsonCount(8, 'days')
            ->assertJsonPath('days.0.date', today()->subDays(8)->toDateString())
            ->assertJsonPath('days.7.date', today()->subDays(15)->toDateString());
    }

    public function test_history_is_empty_for_new_readers_and_older_pages_are_owned(): void
    {
        $user = User::factory()->create();
        ReadingLog::factory()->create(['date_read' => today()->subDay()->toDateString()]);
        $this->getJson(route('logs.older', ['before' => today()->toDateString()]))->assertUnauthorized();
        $this->actingAs($user)->get(route('logs.index'))->assertInertia(fn ($page) => $page
            ->component('History')->where('days', [])->where('hasLogs', false)->where('nextUrl', null)
        );
        $this->getJson(route('logs.older', ['before' => today()->toDateString()]))
            ->assertOk()->assertJsonPath('days', [])->assertJsonPath('hasLogs', false)
            ->assertHeader('Cache-Control', 'no-store, private');
    }
}

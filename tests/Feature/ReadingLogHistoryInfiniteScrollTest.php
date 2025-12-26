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

    public function test_infinite_scroll_response_returns_timeline_markup(): void
    {
        $user = User::factory()->create();

        // Create 17 consecutive days of logs (per-page limit is 8 days)
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

        $response = $this->actingAs($user)
            ->withHeaders(['HX-Request' => 'true'])
            ->get(route('logs.index', ['page' => 2]));

        $response->assertOk();
        $response->assertSee('id="reading-day-', false);
        $response->assertSee('class="ms-6"', false);
        $response->assertSee('id="infinite-scroll-sentinel"', false);
        $response->assertSee('hx-target="this"', false);
        $response->assertSee('hx-swap="outerHTML"', false);
        $response->assertSee('hx-swap-oob="beforeend"', false);
    }
}

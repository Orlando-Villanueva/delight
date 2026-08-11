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

    public function test_chapter_count_badge_is_shown_beside_multi_chapter_passage_only(): void
    {
        $user = User::factory()->create();
        $loggedAt = Carbon::today()->setTime(8, 0);

        foreach ([1, 2] as $chapter) {
            ReadingLog::factory()->create([
                'user_id' => $user->id,
                'book_id' => 1,
                'chapter' => $chapter,
                'passage_text' => 'Genesis '.$chapter,
                'date_read' => $loggedAt->toDateString(),
                'created_at' => $loggedAt,
                'updated_at' => $loggedAt,
            ]);
        }

        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'book_id' => 2,
            'chapter' => 1,
            'passage_text' => 'Exodus 1',
            'date_read' => $loggedAt->copy()->subDay()->toDateString(),
            'created_at' => $loggedAt->copy()->subDay(),
            'updated_at' => $loggedAt->copy()->subDay(),
        ]);

        $response = $this->actingAs($user)->get(route('logs.index'));

        $response->assertOk();
        $response->assertSee('aria-label="2 chapters"', false);
        $response->assertDontSee('aria-label="1 chapter"', false);

        expect($response->getContent())
            ->toMatch('/<h3[^>]*>.*?aria-label="2 chapters".*?<\/h3>/s')
            ->not->toMatch('/<div class="mb-4">\s*<time[^>]*>.*?<\/time>\s*<span/s');
    }
}

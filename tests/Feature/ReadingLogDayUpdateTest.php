<?php

namespace Tests\Feature;

use App\Models\ReadingLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingLogDayUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_day_remains_when_deleting_single_entry(): void
    {
        $user = User::factory()->create();

        $log1 = ReadingLog::factory()->create([
            'user_id' => $user->id,
            'book_id' => 1,
            'chapter' => 1,
            'passage_text' => 'Genesis 1',
            'date_read' => today(),
        ]);

        $log2 = ReadingLog::factory()->create([
            'user_id' => $user->id,
            'book_id' => 1,
            'chapter' => 2,
            'passage_text' => 'Genesis 2',
            'date_read' => today(),
        ]);

        $response = $this->actingAs($user)
            ->withHeaders(['HX-Request' => 'true'])
            ->delete(route('logs.destroy', $log1));

        $response->assertSuccessful();
        $date = today()->format('Y-m-d');
        $this->assertEquals(1, ReadingLog::count(), 'One log should remain after deletion');
        $direct = $user->readingLogs()->whereDate('date_read', $date)->get();
        $this->assertTrue($direct->isNotEmpty(), 'Direct query should return remaining logs');
        $response->assertSee('Genesis 2', false);
        $response->assertSee('id="reading-day-'.today()->format('Y-m-d').'"', false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\ReadingLog;
use App\Models\User;
use App\Services\ReadingLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditReadingLogNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_reading_log_note_via_htmx(): void
    {
        $user = User::factory()->create();

        $log = ReadingLog::factory()->create([
            'user_id' => $user->id,
            'book_id' => 1,
            'chapter' => 1,
            'passage_text' => 'Genesis 1',
            'date_read' => today(),
            'notes_text' => 'Original note',
        ]);

        $response = $this->actingAs($user)
            ->withHeaders(['HX-Request' => 'true'])
            ->patch(route('logs.notes.update', $log), [
                'notes_text' => 'Updated note text',
                'log_ids' => [$log->id],
            ]);

        $response->assertSuccessful();
        $response->assertViewIs('partials.reading-log-update-response');

        $this->assertDatabaseHas('reading_logs', [
            'id' => $log->id,
            'notes_text' => 'Updated note text',
        ]);

        $trigger = json_decode($response->headers->get('HX-Trigger'), true);

        $this->assertIsArray($trigger);
        $this->assertArrayHasKey('hideModal', $trigger);
        $this->assertSame(['id' => "edit-note-{$log->id}"], $trigger['hideModal']);
    }

    public function test_user_can_clear_reading_log_note(): void
    {
        $user = User::factory()->create();

        $log = ReadingLog::factory()->create([
            'user_id' => $user->id,
            'book_id' => 1,
            'chapter' => 1,
            'passage_text' => 'Genesis 1',
            'date_read' => today(),
            'notes_text' => 'Original note',
        ]);

        $response = $this->actingAs($user)
            ->withHeaders(['HX-Request' => 'true'])
            ->patch(route('logs.notes.update', $log), [
                'notes_text' => '   ',
                'log_ids' => [$log->id],
            ]);

        $response->assertSuccessful();

        $this->assertDatabaseHas('reading_logs', [
            'id' => $log->id,
            'notes_text' => null,
        ]);
    }

    public function test_user_cannot_update_another_users_reading_log_note(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $log = ReadingLog::factory()->create([
            'user_id' => $owner->id,
            'book_id' => 1,
            'chapter' => 1,
            'passage_text' => 'Genesis 1',
            'date_read' => today(),
            'notes_text' => 'Original note',
        ]);

        $this->actingAs($otherUser)
            ->patch(route('logs.notes.update', $log), [
                'notes_text' => 'Not allowed',
            ])
            ->assertForbidden();
    }

    public function test_updating_note_for_multi_chapter_reading_updates_all_logs(): void
    {
        $user = User::factory()->create();
        $readingLogService = app(ReadingLogService::class);

        $firstLog = $readingLogService->logReading($user, [
            'book_id' => 1,
            'chapters' => [1, 2],
            'passage_text' => 'Genesis 1-2',
            'date_read' => today()->toDateString(),
            'notes_text' => 'Original note',
        ]);

        $logIds = ReadingLog::where('user_id', $user->id)
            ->orderBy('chapter')
            ->pluck('id')
            ->all();

        $response = $this->actingAs($user)
            ->withHeaders(['HX-Request' => 'true'])
            ->patch(route('logs.notes.update', $firstLog), [
                'notes_text' => 'Updated for range',
                'log_ids' => $logIds,
            ]);

        $response->assertSuccessful();

        foreach ($logIds as $logId) {
            $this->assertDatabaseHas('reading_logs', [
                'id' => $logId,
                'notes_text' => 'Updated for range',
            ]);
        }
    }
}

<?php

use App\Models\ReadingLog;
use App\Models\User;
use App\Services\ReadingLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->readingLogService = app(ReadingLogService::class);

    $this->makeReadingLog = function (User $user, array $overrides = []): ReadingLog {
        return ReadingLog::factory()->create(array_merge([
            'user_id' => $user->id,
            'book_id' => 1,
            'chapter' => 1,
            'passage_text' => 'Genesis 1',
            'date_read' => today(),
            'notes_text' => 'Original note',
        ], $overrides));
    };
});

it('allows a user to update a reading log note via htmx', function () {
    $user = User::factory()->create();
    $log = ($this->makeReadingLog)($user);

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

    expect($trigger)->toBeArray()
        ->and($trigger)->toHaveKey('hideModal')
        ->and($trigger['hideModal'])->toBe(['id' => "edit-note-{$log->id}"]);
});

it('allows a user to clear a reading log note', function () {
    $user = User::factory()->create();
    $log = ($this->makeReadingLog)($user);

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
});

it('blocks a user from updating another user reading log note', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $log = ($this->makeReadingLog)($owner);

    $this->actingAs($otherUser)
        ->patch(route('logs.notes.update', $log), [
            'notes_text' => 'Not allowed',
        ])
        ->assertForbidden();
});

it('updates all logs when editing notes for a multi chapter reading', function () {
    $user = User::factory()->create();

    $firstLog = $this->readingLogService->logReading($user, [
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
});

it('rejects log ids that do not belong to the user', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $ownerLog = ($this->makeReadingLog)($owner);

    $otherUserLog = ($this->makeReadingLog)($otherUser, [
        'chapter' => 2,
        'passage_text' => 'Genesis 2',
        'notes_text' => 'Other note',
    ]);

    $response = $this->actingAs($owner)
        ->withHeaders(['HX-Request' => 'true'])
        ->patch(route('logs.notes.update', $ownerLog), [
            'notes_text' => 'Updated note text',
            'log_ids' => [$ownerLog->id, $otherUserLog->id],
        ]);

    $response->assertStatus(422);
    $response->assertViewIs('components.modals.partials.edit-reading-note-form');
    $response->assertHeader('HX-Retarget', "#edit-note-form-container-{$ownerLog->id}");

    $this->assertDatabaseHas('reading_logs', [
        'id' => $ownerLog->id,
        'notes_text' => 'Original note',
    ]);

    $this->assertDatabaseHas('reading_logs', [
        'id' => $otherUserLog->id,
        'notes_text' => 'Other note',
    ]);
});

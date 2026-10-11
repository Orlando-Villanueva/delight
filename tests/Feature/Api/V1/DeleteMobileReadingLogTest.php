<?php

use App\Models\BookProgress;
use App\Models\ReadingLog;
use App\Models\User;
use App\Services\ReadingLogService;

it('requires authentication, mobile ability, and ownership to remove a record', function () {
    $log = ReadingLog::factory()->create();
    $endpoint = '/api/v1/reading-logs/'.$log->id;

    $this->deleteJson($endpoint)->assertUnauthorized();
    $this->withToken($log->user->createToken('test', ['reporting'])->plainTextToken)
        ->deleteJson($endpoint)->assertForbidden();
    $other = User::factory()->create();
    $this->withToken($other->createToken('test', ['mobile'])->plainTextToken)
        ->deleteJson($endpoint)->assertForbidden();

    $this->assertModelExists($log);
});

it('removes only the selected chapter, recalculates progress, and regroups history', function () {
    $user = User::factory()->create();
    app(ReadingLogService::class)->logReading($user, [
        'book_id' => 43, 'chapters' => [1, 2, 3], 'date_read' => today()->toDateString(), 'notes_text' => 'Note',
    ]);
    $logs = $user->readingLogs()->orderBy('chapter')->get();
    $achievement = $user->achievements()->firstOrFail();
    $token = $user->createToken('test', ['mobile'])->plainTextToken;

    $this->withToken($token)->deleteJson('/api/v1/reading-logs/'.$logs[1]->id)->assertNoContent();

    $this->assertModelMissing($logs[1]);
    $this->assertModelExists($logs[0]);
    $this->assertModelExists($logs[2]);
    $this->assertModelExists($achievement);
    expect($user->bookProgress()->where('book_id', 43)->first()->chapters_read)->toBe([1, 3]);
    $this->withToken($token)->getJson('/api/v1/reading-logs')
        ->assertOk()->assertJsonCount(2, 'data.0.groups')
        ->assertJsonPath('data.0.groups.0.records.0.id', $logs[0]->id)
        ->assertJsonPath('data.0.groups.1.records.0.id', $logs[2]->id);
});

it('preserves progress while another reading of the same chapter remains', function () {
    $user = User::factory()->create();
    $service = app(ReadingLogService::class);
    $first = $service->logReading($user, [
        'book_id' => 43, 'chapter' => 1, 'date_read' => today()->subDay()->toDateString(),
    ]);
    $second = $service->logReading($user, [
        'book_id' => 43, 'chapter' => 1, 'date_read' => today()->toDateString(),
    ]);
    $token = $user->createToken('test', ['mobile'])->plainTextToken;

    $this->withToken($token)->deleteJson('/api/v1/reading-logs/'.$first->id)->assertNoContent();

    $this->assertModelMissing($first);
    $this->assertModelExists($second);
    expect($user->bookProgress()->where('book_id', 43)->first()->chapters_read)->toBe([1]);
    $this->withToken($token)->deleteJson('/api/v1/reading-logs/'.$second->id)->assertNoContent();
    expect($user->bookProgress()->where('book_id', 43)->first()->chapters_read)->toBe([]);
    $this->withToken($token)->deleteJson('/api/v1/reading-logs/'.$second->id)->assertNotFound();
});

it('rolls back record deletion if updating book progress fails', function () {
    $user = User::factory()->create();
    $log = app(ReadingLogService::class)->logReading($user, [
        'book_id' => 43, 'chapter' => 1, 'date_read' => today()->toDateString(),
    ]);
    BookProgress::updating(fn () => throw new RuntimeException('Progress update failed'));
    $this->withoutExceptionHandling();

    try {
        expect(fn () => $this->withToken($user->createToken('test', ['mobile'])->plainTextToken)
            ->deleteJson('/api/v1/reading-logs/'.$log->id))->toThrow(RuntimeException::class, 'Progress update failed');
        $this->assertModelExists($log);
        expect($user->bookProgress()->where('book_id', 43)->first()->chapters_read)->toBe([1]);
    } finally {
        BookProgress::flushEventListeners();
    }
});

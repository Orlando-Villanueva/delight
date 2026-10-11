<?php

use App\Models\BookProgress;
use App\Models\ReadingLog;
use App\Models\User;
use App\Services\ReadingLogService;

it('requires authentication, mobile ability and ownership for batch removal', function () {
    $log = ReadingLog::factory()->create();
    $endpoint = '/api/v1/reading-logs/'.$log->id.'/chapters';
    $payload = ['group_ids' => [$log->id], 'log_ids' => [$log->id]];
    $this->deleteJson($endpoint, $payload)->assertUnauthorized();
    $this->withToken($log->user->createToken('test', ['reporting'])->plainTextToken)
        ->deleteJson($endpoint, $payload)->assertForbidden();
    $this->withToken(User::factory()->create()->createToken('test', ['mobile'])->plainTextToken)
        ->deleteJson($endpoint, $payload)->assertForbidden();
    $this->assertModelExists($log);
});

it('deletes a subset atomically and regroups the remaining records', function () {
    $user = User::factory()->create();
    app(ReadingLogService::class)->logReading($user, [
        'book_id' => 43, 'chapters' => [1, 2, 3, 4, 5], 'date_read' => today()->toDateString(),
    ]);
    $logs = $user->readingLogs()->orderBy('chapter')->get();
    $achievement = $user->achievements()->firstOrFail();
    $token = $user->createToken('test', ['mobile'])->plainTextToken;
    $this->withToken($token)->deleteJson('/api/v1/reading-logs/'.$logs[0]->id.'/chapters', [
        'group_ids' => $logs->pluck('id')->all(), 'log_ids' => [$logs[1]->id, $logs[3]->id],
    ])->assertNoContent();
    expect($user->readingLogs()->orderBy('chapter')->pluck('chapter')->all())->toBe([1, 3, 5]);
    expect($user->bookProgress()->where('book_id', 43)->first()->chapters_read)->toBe([1, 3, 5]);
    $this->assertModelExists($achievement);
    $this->withToken($token)->getJson('/api/v1/reading-logs')->assertOk()->assertJsonCount(3, 'data.0.groups');
});

it('removes the whole group while preserving progress from another date', function () {
    $user = User::factory()->create();
    $service = app(ReadingLogService::class);
    $other = $service->logReading($user, [
        'book_id' => 43, 'chapter' => 1, 'date_read' => today()->subDay()->toDateString(),
    ]);
    $service->logReading($user, [
        'book_id' => 43, 'chapters' => [1, 2, 3], 'date_read' => today()->toDateString(),
    ]);
    $ids = $user->readingLogs()->whereDate('date_read', today())->orderBy('chapter')->pluck('id')->all();
    $this->withToken($user->createToken('test', ['mobile'])->plainTextToken)
        ->deleteJson('/api/v1/reading-logs/'.$ids[0].'/chapters', ['group_ids' => $ids, 'log_ids' => $ids])
        ->assertNoContent();
    expect($user->readingLogs()->pluck('id')->all())->toBe([$other->id]);
    expect($user->bookProgress()->where('book_id', 43)->first()->chapters_read)->toBe([1]);
});

it('rejects invalid selections without changing records', function (array $selection) {
    $log = ReadingLog::factory()->create();
    $selection = array_map(fn ($id) => $id === 'id' ? $log->id : $id, $selection);
    $this->withToken($log->user->createToken('test', ['mobile'])->plainTextToken)
        ->deleteJson('/api/v1/reading-logs/'.$log->id.'/chapters', [
            'group_ids' => [$log->id], 'log_ids' => $selection,
        ])->assertUnprocessable();
    $this->assertModelExists($log);
})->with([[[]], [['id', 'id']], [[0]], [['bad']]]);

it('rejects stale, foreign and cross-group IDs without partial deletion', function () {
    $user = User::factory()->create();
    app(ReadingLogService::class)->logReading($user, [
        'book_id' => 43, 'chapters' => [1, 2, 3], 'date_read' => today()->toDateString(),
    ]);
    $logs = $user->readingLogs()->orderBy('chapter')->get();
    $other = ReadingLog::factory()->create(['user_id' => $user->id, 'book_id' => 42]);
    $foreign = ReadingLog::factory()->create();
    $token = $user->createToken('test', ['mobile'])->plainTextToken;
    $endpoint = '/api/v1/reading-logs/'.$logs[0]->id.'/chapters';
    $this->withToken($token)->deleteJson($endpoint, [
        'group_ids' => [$logs[0]->id, $logs[1]->id], 'log_ids' => [$logs[1]->id],
    ])->assertConflict();
    $this->withToken($token)->deleteJson($endpoint, [
        'group_ids' => $logs->pluck('id')->all(), 'log_ids' => [$logs[1]->id, $other->id],
    ])->assertConflict();
    $this->withToken($token)->deleteJson($endpoint, [
        'group_ids' => [$logs[0]->id, $foreign->id], 'log_ids' => [$logs[0]->id],
    ])->assertNotFound();
    $logs[2]->delete();
    $this->withToken($token)->deleteJson($endpoint, [
        'group_ids' => $logs->pluck('id')->all(), 'log_ids' => [$logs[1]->id],
    ])->assertNotFound();
    $this->assertModelExists($logs[0]);
    $this->assertModelExists($logs[1]);
    $this->assertModelExists($other);
    $this->assertModelExists($foreign);
});

it('rolls back the entire selection when a later progress update fails', function () {
    $user = User::factory()->create();
    app(ReadingLogService::class)->logReading($user, [
        'book_id' => 43, 'chapters' => [1, 2, 3], 'date_read' => today()->toDateString(),
    ]);
    $ids = $user->readingLogs()->orderBy('chapter')->pluck('id')->all();
    $updates = 0;
    BookProgress::updating(function () use (&$updates) {
        if (++$updates === 2) {
            throw new RuntimeException('Progress update failed');
        }
    });
    $this->withoutExceptionHandling();
    try {
        expect(fn () => $this->withToken($user->createToken('test', ['mobile'])->plainTextToken)
            ->deleteJson('/api/v1/reading-logs/'.$ids[0].'/chapters', [
                'group_ids' => $ids, 'log_ids' => [$ids[0], $ids[1]],
            ]))->toThrow(RuntimeException::class, 'Progress update failed');
        expect($user->readingLogs()->orderBy('chapter')->pluck('id')->all())->toBe($ids);
        expect($user->bookProgress()->where('book_id', 43)->first()->chapters_read)->toBe([1, 2, 3]);
    } finally {
        BookProgress::flushEventListeners();
    }
});

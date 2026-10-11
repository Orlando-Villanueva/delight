<?php

use App\Models\BookProgress;
use App\Models\ReadingLog;
use App\Models\User;
use App\Services\ReadingLogService;

it('renders the group identity and selected-ID fields in the web modal', function () {
    $user = User::factory()->create();
    app(ReadingLogService::class)->logReading($user, [
        'book_id' => 43, 'chapters' => [1, 2, 3], 'date_read' => today()->toDateString(),
    ]);
    $logs = $user->readingLogs()->orderBy('chapter')->get();
    $this->actingAs($user)->get(route('logs.index'))->assertOk()
        ->assertSee(route('logs.chapters.destroy', $logs[0]), false)
        ->assertSee('name="group_ids[]"', false)
        ->assertSee('name="log_ids[]"', false)
        ->assertSee('x-on:htmx:after-request="afterRequest($event)"', false)
        ->assertSee('role="alert"', false)
        ->assertDontSee('<button type="submit" data-modal-hide=', false);
});

it('requires authentication and ownership for web chapter selection', function () {
    $log = ReadingLog::factory()->create();
    $payload = ['group_ids' => [$log->id], 'log_ids' => [$log->id]];
    $endpoint = route('logs.chapters.destroy', $log);
    $this->delete($endpoint, $payload)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->deleteJson($endpoint, $payload)->assertForbidden();
    $this->assertModelExists($log);
});

it('rejects malformed web selections instead of coercing or skipping them', function (mixed $selection) {
    $log = ReadingLog::factory()->create();
    $this->actingAs($log->user)->withHeaders(['HX-Request' => 'true'])
        ->deleteJson(route('logs.chapters.destroy', $log), [
            'group_ids' => [$log->id], 'log_ids' => $selection,
        ])->assertUnprocessable()->assertJsonValidationErrors('log_ids');
    $this->assertModelExists($log);
})->with([[[]], ['bad'], [array_fill(0, 201, 1)]]);

it('rejects mixed-group and stale web selections without deleting any selected records', function () {
    $user = User::factory()->create();
    app(ReadingLogService::class)->logReading($user, [
        'book_id' => 43, 'chapters' => [1, 2, 3], 'date_read' => today()->toDateString(),
    ]);
    $logs = $user->readingLogs()->orderBy('chapter')->get();
    $foreign = ReadingLog::factory()->create();
    $endpoint = route('logs.chapters.destroy', $logs[0]);
    $this->actingAs($user)->withHeaders(['HX-Request' => 'true'])->deleteJson($endpoint, [
        'group_ids' => [$logs[0]->id, $logs[1]->id], 'log_ids' => [$logs[1]->id],
    ])->assertConflict();
    $this->deleteJson($endpoint, [
        'group_ids' => $logs->pluck('id')->all(), 'log_ids' => [$logs[1]->id, $foreign->id],
    ])->assertConflict();
    $this->deleteJson($endpoint, [
        'group_ids' => [$logs[0]->id, $foreign->id], 'log_ids' => [$logs[0]->id],
    ])->assertNotFound();
    foreach ($logs as $log) {
        $this->assertModelExists($log);
    }
    $this->assertModelExists($foreign);
});

it('returns a regrouped day fragment after partial web deletion', function () {
    $user = User::factory()->create();
    app(ReadingLogService::class)->logReading($user, [
        'book_id' => 43, 'chapters' => [1, 2, 3, 4, 5], 'date_read' => today()->toDateString(),
    ]);
    $logs = $user->readingLogs()->orderBy('chapter')->get();
    $response = $this->actingAs($user)->withHeaders(['HX-Request' => 'true'])
        ->deleteJson(route('logs.chapters.destroy', $logs[0]), [
            'group_ids' => $logs->pluck('id')->all(), 'log_ids' => [$logs[1]->id, $logs[3]->id],
        ]);
    $response->assertOk()->assertViewIs('partials.reading-log-update-response')
        ->assertHeader('HX-Trigger', json_encode(['hideModal' => ['id' => "delete-chapters-{$logs[0]->id}"]]))
        ->assertViewHas('primaryDate', today()->toDateString())
        ->assertSee('John 1')->assertSee('John 3')->assertSee('John 5');
    expect($user->readingLogs()->orderBy('chapter')->pluck('chapter')->all())->toBe([1, 3, 5]);
    expect($user->bookProgress()->where('book_id', 43)->first()->chapters_read)->toBe([1, 3, 5]);
});

it('keeps the redirect and removes the whole group for non-HTMX requests', function () {
    $user = User::factory()->create();
    app(ReadingLogService::class)->logReading($user, [
        'book_id' => 43, 'chapters' => [1, 2], 'date_read' => today()->toDateString(),
    ]);
    $ids = $user->readingLogs()->orderBy('chapter')->pluck('id')->all();
    $this->actingAs($user)->delete(route('logs.chapters.destroy', $ids[0]), [
        'group_ids' => $ids, 'log_ids' => $ids,
    ])->assertRedirect(route('logs.index'));
    expect($user->readingLogs()->count())->toBe(0);
    expect($user->bookProgress()->where('book_id', 43)->first()->chapters_read)->toBe([]);
});

it('rolls back earlier web deletions if a later progress update fails', function () {
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
        expect(fn () => $this->actingAs($user)->deleteJson(route('logs.chapters.destroy', $ids[0]), [
            'group_ids' => $ids, 'log_ids' => [$ids[0], $ids[1]],
        ]))->toThrow(RuntimeException::class, 'Progress update failed');
        expect($user->readingLogs()->orderBy('chapter')->pluck('id')->all())->toBe($ids);
        expect($user->bookProgress()->where('book_id', 43)->first()->chapters_read)->toBe([1, 2, 3]);
    } finally {
        BookProgress::flushEventListeners();
    }
});

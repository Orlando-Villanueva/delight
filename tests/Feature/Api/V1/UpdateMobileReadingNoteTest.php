<?php

use App\Models\ReadingLog;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->logs = collect([1, 2])->map(fn (int $chapter) => ReadingLog::factory()->create([
        'user_id' => $this->user->id, 'book_id' => 43, 'chapter' => $chapter,
        'date_read' => '2026-08-08', 'created_at' => '2026-08-08 12:00:00',
        'notes_text' => 'Original',
    ]));
    $this->endpoint = '/api/v1/reading-logs/'.$this->logs->first()->id.'/note';
    $this->payload = ['log_ids' => $this->logs->pluck('id')->all(), 'notes_text' => 'Updated'];
});

it('requires authentication, mobile ability, and primary record ownership', function () {
    $this->patchJson($this->endpoint, $this->payload)->assertUnauthorized();
    $this->withToken($this->user->createToken('test', ['reporting'])->plainTextToken)
        ->patchJson($this->endpoint, $this->payload)->assertForbidden();
    $other = User::factory()->create();
    $this->withToken($other->createToken('test', ['mobile'])->plainTextToken)
        ->patchJson($this->endpoint, $this->payload)->assertForbidden();
    expect($this->logs->first()->fresh()->notes_text)->toBe('Original');
});

it('updates the full group and changes only notes', function () {
    $before = $this->logs->map(fn ($log) => $log->only(['book_id', 'chapter', 'date_read', 'created_at', 'passage_text']));
    $this->withToken($this->user->createToken('test', ['mobile'])->plainTextToken)
        ->patchJson($this->endpoint, [...$this->payload, 'notes_text' => '  Updated  ', 'chapter' => 99])
        ->assertNoContent();
    foreach ($this->logs as $index => $log) {
        expect($log->fresh()->notes_text)->toBe('Updated');
        expect($log->fresh()->only(array_keys($before[$index])))->toEqual($before[$index]);
    }
});

it('clears every note in the group', function () {
    $this->withToken($this->user->createToken('test', ['mobile'])->plainTextToken)
        ->patchJson($this->endpoint, [...$this->payload, 'notes_text' => null])->assertNoContent();
    foreach ($this->logs as $log) {
        expect($log->fresh()->notes_text)->toBeNull();
    }
});

it('rejects stale group membership without a partial update', function () {
    $this->withToken($this->user->createToken('test', ['mobile'])->plainTextToken)
        ->patchJson($this->endpoint, [...$this->payload, 'log_ids' => [$this->logs->first()->id]])
        ->assertConflict();
    expect($this->logs->last()->fresh()->notes_text)->toBe('Original');
    expect($this->logs->first()->fresh()->notes_text)->toBe('Original');
});

it('rejects missing and foreign records without updating surviving records', function () {
    $foreign = ReadingLog::factory()->create(['date_read' => '2026-08-08']);
    $token = $this->user->createToken('test', ['mobile'])->plainTextToken;
    foreach ([$foreign->id, 999999] as $id) {
        $this->withToken($token)->patchJson($this->endpoint, [
            ...$this->payload, 'log_ids' => [...$this->payload['log_ids'], $id],
        ])->assertNotFound();
    }
    expect($this->logs->first()->fresh()->notes_text)->toBe('Original');
    expect($foreign->fresh()->notes_text)->toBe($foreign->notes_text);
});

it('requires explicit note input and validates its limit and distinct IDs', function () {
    $token = $this->user->createToken('test', ['mobile'])->plainTextToken;
    $this->withToken($token)->patchJson($this->endpoint, ['log_ids' => $this->payload['log_ids']])
        ->assertUnprocessable()->assertJsonValidationErrors('notes_text');
    $this->withToken($token)->patchJson($this->endpoint, [...$this->payload, 'notes_text' => str_repeat('a', 1001)])
        ->assertUnprocessable()->assertJsonValidationErrors('notes_text');
    $this->withToken($token)->patchJson($this->endpoint, [
        ...$this->payload, 'log_ids' => [$this->logs->first()->id, $this->logs->first()->id],
    ])->assertUnprocessable()->assertJsonValidationErrors('log_ids.0');
});

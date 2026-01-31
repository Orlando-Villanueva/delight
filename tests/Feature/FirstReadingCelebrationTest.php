<?php

use App\Models\ReadingLog;
use App\Models\User;

it('shows celebration for first reading', function () {
    $user = User::factory()->create();

    $readingData = [
        'book_id' => 43, // John
        'start_chapter' => 1,
        'date_read' => today()->toDateString(),
    ];

    $response = $this->actingAs($user)
        ->withHeaders(['HX-Request' => 'true'])
        ->post('/logs', $readingData);

    $response->assertStatus(200)
        ->assertSee('data-is-first-reading')
        ->assertSee('1 down, 365 to go');
});

it('does not show celebration for subsequent readings', function () {
    $user = User::factory()->create();
    ReadingLog::factory()->for($user)->create([
        'book_id' => 1, // Genesis
        'chapter' => 1,
        'date_read' => now(),
    ]);

    $readingData = [
        'book_id' => 43, // John
        'start_chapter' => 1,
        'date_read' => today()->toDateString(),
    ];

    $response = $this->actingAs($user)
        ->withHeaders(['HX-Request' => 'true'])
        ->post('/logs', $readingData);

    $response->assertStatus(200)
        ->assertDontSee("You've started! 1 down, 365 to go");
});

it('does not re-celebrate if user deletes and logs again', function () {
    $user = User::factory()->create([
        'celebrated_first_reading_at' => now(),
    ]);

    $readingData = [
        'book_id' => 43, // John
        'start_chapter' => 1,
        'date_read' => today()->toDateString(),
    ];

    $response = $this->actingAs($user)
        ->withHeaders(['HX-Request' => 'true'])
        ->post('/logs', $readingData);

    $response->assertStatus(200)
        ->assertDontSee("You've started! 1 down, 365 to go");
});

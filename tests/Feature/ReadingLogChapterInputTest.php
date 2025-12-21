<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ReadingLogChapterInputTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test that validation fails if only end_chapter is provided.
     */
    public function test_reading_log_creation_fails_with_only_end_chapter(): void
    {
        $user = User::factory()->create();

        $readingData = [
            'book_id' => 1, // Genesis
            // Missing start_chapter
            'end_chapter' => '3',
            'date_read' => today()->toDateString(),
        ];

        $response = $this->actingAs($user)->post('/logs', $readingData);

        // Assert validation error - controller passes errors to view, not session
        $response->assertViewHas('errors');
        $errors = $response->viewData('errors');
        $this->assertTrue($errors->has('start_chapter'));

        // Ensure no log was created
        $this->assertDatabaseCount('reading_logs', 0);
    }

    /**
     * Test that explicit single chapter input creates a single log entry.
     */
    public function test_explicit_single_chapter_input(): void
    {
        $user = User::factory()->create();

        $readingData = [
            'book_id' => 1, // Genesis
            'start_chapter' => '5',
            // No end_chapter
            'date_read' => today()->toDateString(),
        ];

        $response = $this->actingAs($user)->post('/logs', $readingData);
        $response->assertStatus(200); // Success

        // Should be exactly one log
        $this->assertDatabaseCount('reading_logs', 1);

        $this->assertDatabaseHas('reading_logs', [
            'user_id' => $user->id,
            'book_id' => 1,
            'chapter' => 5,
        ]);
    }

    /**
     * Test that explicit range input creates multiple log entries.
     */
    public function test_explicit_range_input_creates_multiple_entries(): void
    {
        $user = User::factory()->create();

        $readingData = [
            'book_id' => 1, // Genesis
            'start_chapter' => '10',
            'end_chapter' => '12',
            'date_read' => today()->toDateString(),
        ];

        $response = $this->actingAs($user)->post('/logs', $readingData);
        $response->assertStatus(200); // Success

        // Should be 3 logs (10, 11, 12)
        $this->assertDatabaseCount('reading_logs', 3);

        $this->assertDatabaseHas('reading_logs', ['book_id' => 1, 'chapter' => 10]);
        $this->assertDatabaseHas('reading_logs', ['book_id' => 1, 'chapter' => 11]);
        $this->assertDatabaseHas('reading_logs', ['book_id' => 1, 'chapter' => 12]);
    }

    /**
     * Test that range input where start equals end is treated as single chapter.
     */
    public function test_range_input_start_equals_end_is_single_chapter(): void
    {
        $user = User::factory()->create();

        $readingData = [
            'book_id' => 1, // Genesis
            'start_chapter' => '7',
            'end_chapter' => '7',
            'date_read' => today()->toDateString(),
        ];

        $response = $this->actingAs($user)->post('/logs', $readingData);
        $response->assertStatus(200);

        // Should be exactly one log
        $this->assertDatabaseCount('reading_logs', 1);

        $this->assertDatabaseHas('reading_logs', [
            'book_id' => 1,
            'chapter' => 7,
        ]);
    }

    /**
     * Test that validation fails if start chapter is greater than end chapter.
     */
    public function test_range_input_fails_if_start_greater_than_end(): void
    {
        $user = User::factory()->create();

        $readingData = [
            'book_id' => 1, // Genesis
            'start_chapter' => '20',
            'end_chapter' => '18', // Inverted
            'date_read' => today()->toDateString(),
        ];

        $response = $this->actingAs($user)->post('/logs', $readingData);

        // Assert validation error
        $response->assertViewHas('errors');
        $errors = $response->viewData('errors');
        $this->assertTrue($errors->has('start_chapter'));
        $this->assertEquals(
            'Invalid chapter range for the selected book.',
            $errors->first('start_chapter')
        );

        // Ensure no log was created
        $this->assertDatabaseCount('reading_logs', 0);
    }
}

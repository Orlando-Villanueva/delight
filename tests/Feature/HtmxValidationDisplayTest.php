<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HtmxValidationDisplayTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that HTMX success response passes message as variable, not session flash.
     */
    public function test_htmx_success_uses_variable_not_flash()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logs', [
            'book_id' => 1, // Genesis
            'start_chapter' => '1',
            'date_read' => today()->toDateString(),
        ], ['HX-Request' => 'true']);

        $response->assertStatus(200);

        // For first reading, we show celebration UI instead of normal success message
        $response->assertSee('data-is-first-reading');
        $response->assertSee('1 down, 365 to go');

        // Assert the session does NOT have the 'success' key flashed for the next request
        $this->assertFalse(session()->has('success'), 'Session should not have success key flashed for HTMX requests');
    }

    /**
     * Test that subsequent HTMX failure does not show success message.
     */
    public function test_htmx_validation_failure_clears_success_message()
    {
        $user = User::factory()->create();

        // 1. Successful Request
        $this->actingAs($user)->post('/logs', [
            'book_id' => 1, // Genesis
            'start_chapter' => '1',
            'date_read' => today()->toDateString(),
        ], ['HX-Request' => 'true']);

        // 2. Failed Request (Invalid Range)
        $response = $this->actingAs($user)->post('/logs', [
            'book_id' => 1, // Genesis
            'start_chapter' => '10',
            'end_chapter' => '5', // Invalid range
            'date_read' => today()->toDateString(),
        ], ['HX-Request' => 'true']);

        $response->assertStatus(200); // HTMX returns 200 with error form

        // Assert error message is present in the HTML
        $response->assertSee('Invalid chapter range');

        // Assert success message is NOT present in the HTML
        $response->assertDontSee('Genesis 1 recorded');
        $response->assertDontSee('✅');
        $response->assertSee('Invalid chapter range');
    }
}

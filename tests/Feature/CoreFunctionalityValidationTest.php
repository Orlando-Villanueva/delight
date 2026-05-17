<?php

namespace Tests\Feature;

use App\Models\ReadingLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CoreFunctionalityValidationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test user registration flow
     */
    public function test_user_registration_flow(): void
    {
        // Test registration page loads
        $response = $this->get('/register');
        $response->assertStatus(200);
        $response->assertSee('Create account');
        $response->assertSee('Start your Bible reading journey today');

        // Test successful registration
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'SecureTestPassword2024!',
            'password_confirmation' => 'SecureTestPassword2024!',
        ];

        $response = $this->post('/register', $userData);
        $response->assertRedirect('/dashboard');

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Verify user is authenticated
        $this->assertAuthenticated();
    }

    /**
     * Test user login flow
     */
    public function test_user_login_flow(): void
    {
        // Create a user
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('SecureTestPassword2024!'),
        ]);

        // Test login page loads
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Welcome back');
        $response->assertSee('Continue your Bible reading journey');

        // Test successful login
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'SecureTestPassword2024!',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Test dashboard functionality
     */
    public function test_dashboard_functionality(): void
    {
        $user = User::factory()->create();

        // Create some reading logs for testing
        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'book_id' => 1, // Genesis
            'chapter' => 1,
            'passage_text' => 'Genesis 1',
            'date_read' => today(),
        ]);

        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'book_id' => 1, // Genesis
            'chapter' => 2,
            'passage_text' => 'Genesis 2',
            'date_read' => today()->subDay(),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);

        // Check for key dashboard components (new UI)
        $response->assertSee('Daily Streak');
        $response->assertSee('Days Read');
        $response->assertSee('Total Chapters');
        $response->assertSee('Bible Progress');
        $response->assertSee('Avg/Day');

        // Check for recent readings
        $response->assertSee('Recent Readings');
        $response->assertSee('Genesis 1');
        $response->assertSee('Genesis 2');
    }

    /**
     * Test dashboard includes hasReadToday data
     */
    public function test_dashboard_includes_has_read_today_data(): void
    {
        $user = User::factory()->create();

        // Test dashboard without reading today
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        // Create a reading log for today
        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'book_id' => 1, // Genesis
            'chapter' => 1,
            'passage_text' => 'Genesis 1',
            'date_read' => today(),
        ]);

        // Test dashboard with reading today
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        // Test HTMX request also includes the data
        $response = $this->actingAs($user)
            ->withHeaders(['HX-Request' => 'true'])
            ->get('/dashboard');
        $response->assertStatus(200);
    }

    /**
     * Test reading log creation functionality
     */
    public function test_reading_log_creation(): void
    {
        $user = User::factory()->create();

        // Test reading log form loads
        $response = $this->actingAs($user)->get('/logs/create');
        $response->assertStatus(200);
        $response->assertSee('Log Reading');

        // Test successful reading log creation
        $readingData = [
            'book_id' => 1, // Genesis
            'start_chapter' => '1',
            'date_read' => today()->toDateString(),
            'notes_text' => 'Great chapter about creation',
        ];

        $response = $this->actingAs($user)->post('/logs', $readingData);
        $response->assertStatus(200);
        $response->assertSee('Reading Logged Successfully!');

        // Verify reading log was created
        $this->assertDatabaseHas('reading_logs', [
            'user_id' => $user->id,
            'book_id' => 1,
            'chapter' => 1,
            'passage_text' => 'Genesis 1',
            'notes_text' => 'Great chapter about creation',
        ]);

        // Verify the date separately (since it might include time)
        $readingLog = ReadingLog::where('user_id', $user->id)
            ->where('book_id', 1)
            ->where('chapter', 1)
            ->first();
        $this->assertNotNull($readingLog);
        $this->assertEquals(today()->toDateString(), $readingLog->date_read->toDateString());
    }

    /**
     * Test reading log range functionality
     */
    public function test_reading_log_range_creation(): void
    {
        $user = User::factory()->create();

        // Test chapter range input
        $readingData = [
            'book_id' => 1, // Genesis
            'start_chapter' => '1',
            'end_chapter' => '3',
            'date_read' => today()->toDateString(),
            'notes_text' => 'Read multiple chapters',
        ];

        $response = $this->actingAs($user)->post('/logs', $readingData);
        $response->assertStatus(200);

        // Verify multiple reading logs were created
        $this->assertDatabaseHas('reading_logs', [
            'user_id' => $user->id,
            'book_id' => 1,
            'chapter' => 1,
        ]);

        $this->assertDatabaseHas('reading_logs', [
            'user_id' => $user->id,
            'book_id' => 1,
            'chapter' => 2,
        ]);

        $this->assertDatabaseHas('reading_logs', [
            'user_id' => $user->id,
            'book_id' => 1,
            'chapter' => 3,
        ]);
    }

    /**
     * Test reading history page
     */
    public function test_reading_history_functionality(): void
    {
        $user = User::factory()->create();

        // Create reading logs for different dates
        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'book_id' => 1,
            'chapter' => 1,
            'passage_text' => 'Genesis 1',
            'date_read' => today(),
        ]);

        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'book_id' => 2,
            'chapter' => 1,
            'passage_text' => 'Exodus 1',
            'date_read' => today()->subDays(2),
        ]);

        // Test the main logs page loads
        $response = $this->actingAs($user)->get('/logs');
        $response->assertStatus(200);

        // Test the logs page contains the reading logs
        $response->assertSee('Genesis 1');
        $response->assertSee('Exodus 1');
    }

    /**
     * Test HTMX functionality for dashboard updates
     */
    public function test_htmx_dashboard_updates(): void
    {
        $user = User::factory()->create();

        // Test HTMX request to dashboard
        $response = $this->actingAs($user)
            ->withHeaders(['HX-Request' => 'true'])
            ->get('/dashboard');

        $response->assertStatus(200);
        // Should return partial content, not full page
        $response->assertDontSee('<!DOCTYPE html>');
        $response->assertSee('Dashboard');
    }

    /**
     * Test mobile responsiveness by checking viewport meta tag and responsive classes
     */
    public function test_mobile_responsiveness_elements(): void
    {
        // Test authenticated pages first
        $user = User::factory()->create();

        // Test dashboard mobile responsiveness
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        // Check for viewport meta tag
        $response->assertSee('name="viewport"', false);
        $response->assertSee('width=device-width', false);

        // Check for responsive grid classes
        $response->assertSee('grid-cols-1', false);
        $response->assertSee('sm:grid-cols-2', false);
        $response->assertSee('2xl:grid-cols-[minmax(0,1.15fr)_minmax(0,1.15fr)_minmax(17.5rem,0.85fr)]', false);
        $response->assertSee('xl:grid-cols-4', false);

        // Check for mobile-specific elements
        $response->assertSee('lg:hidden', false); // Mobile navigation and FAB
        $response->assertSee('hidden xl:block', false); // Desktop sidebar

        // Test guest pages (logout first to clear authentication)
        auth()->logout();

        // Test login page mobile responsiveness (as guest)
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('px-4 sm:px-6 lg:px-8', false);
        $response->assertSee('max-w-md', false);

        // Test registration page mobile responsiveness (as guest)
        $response = $this->get('/register');
        $response->assertStatus(200);
        $response->assertSee('px-4 sm:px-6 lg:px-8', false);
        $response->assertSee('max-w-md', false);
    }

    /**
     * Test authentication middleware protection
     */
    public function test_authentication_protection(): void
    {
        // Test that protected routes redirect to login
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');

        $response = $this->get('/logs');
        $response->assertRedirect('/login');

        $response = $this->get('/logs/create');
        $response->assertRedirect('/login');
    }

    /**
     * Test validation errors are handled properly
     */
    public function test_validation_error_handling(): void
    {
        $user = User::factory()->create();

        // Test invalid reading log data
        $invalidData = [
            'book_id' => 999, // Invalid book ID
            'start_chapter' => 'invalid',
            'date_read' => 'invalid-date',
        ];

        $response = $this->actingAs($user)->post('/logs', $invalidData);
        $response->assertStatus(200); // Returns form with errors
        $response->assertSee('Log Reading'); // Form is redisplayed
    }

    /**
     * Test streak calculation functionality
     */
    public function test_streak_calculation(): void
    {
        $user = User::factory()->create();

        // Create consecutive reading logs
        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'book_id' => 1,
            'chapter' => 1,
            'date_read' => today(),
        ]);

        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'book_id' => 1,
            'chapter' => 2,
            'date_read' => today()->subDay(),
        ]);

        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'book_id' => 1,
            'chapter' => 3,
            'date_read' => today()->subDays(2),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        // Should show current streak
        $response->assertSee('Daily Streak');
        $response->assertSee('3'); // 3-day streak
    }

    /**
     * Test book progress tracking
     */
    public function test_book_progress_tracking(): void
    {
        $user = User::factory()->create();

        // Create reading logs for Genesis (50 chapters)
        for ($chapter = 1; $chapter <= 5; $chapter++) {
            ReadingLog::factory()->create([
                'user_id' => $user->id,
                'book_id' => 1, // Genesis
                'chapter' => $chapter,
                'date_read' => today()->subDays($chapter - 1),
            ]);
        }

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        // Should show book progress
        $response->assertSee('Bible Progress');
        // Genesis should show some progress (5/50 chapters = 10%)
        $response->assertSee('Genesis', false);
    }

    /**
     * Test current week reading day statistics in dashboard
